<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Integration\Laravel\Runtime;

use Illuminate\Container\Attributes\Singleton;
use Illuminate\Support\Arr;
use Pepperfm\Flashboard\Contracts\Actions\ActionContract;
use Pepperfm\Flashboard\Contracts\Pages\ComponentPageContract;
use Pepperfm\Flashboard\Contracts\Pages\CustomPageContract;
use Pepperfm\Flashboard\Contracts\Pages\PageDefinitionContract;
use Pepperfm\Flashboard\Contracts\Resources\Resource;
use Pepperfm\Flashboard\Core\Authorization\Visibility\ScreenAccessResolver;
use Pepperfm\Flashboard\Core\Extensions\ExtensionRegistry;
use Pepperfm\Flashboard\Core\Hooks\RuntimeHookDispatcher;
use Pepperfm\Flashboard\Core\Navigation\Builders\NavigationItem;
use Pepperfm\Flashboard\Core\Resources\ResourceSurfaceResolver;
use Pepperfm\Flashboard\Core\Runtime\Assemblers\ActionPayloadAssembler;
use Pepperfm\Flashboard\Core\Runtime\Assemblers\TablePayloadAssembler;
use Pepperfm\Flashboard\Core\Runtime\Context\RuntimeRequestContext;
use Pepperfm\Flashboard\Core\Runtime\Payloads\ScreenPayload;
use Pepperfm\Flashboard\Core\Runtime\Workspaces\WorkspacePayloadAssembler;
use Pepperfm\Flashboard\Integration\Laravel\Auth\PanelAuthenticator;
use Pepperfm\Flashboard\Integration\Laravel\DataSources\ResourceDetailDataSource;
use Pepperfm\Flashboard\Integration\Laravel\DataSources\ResourceFormDataSource;
use Pepperfm\Flashboard\Integration\Laravel\DataSources\ResourceListDataSource;

#[Singleton]
final readonly class LaravelScreenPayloadAssembler
{
    private const string COMPONENT_NAME_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9._:-]*$/';

    private const string COMPONENT_SCRIPT_TYPE_MODULE = 'module';

    private const string COMPONENT_SCRIPT_TYPE_CLASSIC = 'text/javascript';

    private const string COMPONENT_ASSET_SCHEME_PATTERN = '/^[A-Za-z][A-Za-z0-9+.-]*:/';

    public function __construct(
        private ActionPayloadAssembler $actionPayloadAssembler,
        private TablePayloadAssembler $tablePayloadAssembler,
        private ResourceListDataSource $resourceListDataSource,
        private ResourceFormDataSource $resourceFormDataSource,
        private ResourceDetailDataSource $resourceDetailDataSource,
        private WorkspacePayloadAssembler $workspacePayloadAssembler,
        private PageComponentContextFactory $pageComponentContextFactory,
        private ScreenAccessResolver $screenAccessResolver,
        private ResourceSurfaceResolver $resourceSurfaceResolver,
        private PanelAuthenticator $panelAuthenticator,
        private ExtensionRegistry $extensionRegistry,
        private RuntimeHookDispatcher $runtimeHookDispatcher,
    ) {
    }

    public function assemble(RuntimeRequestContext $context): ScreenPayload
    {
        $screen = $context->screen();
        $user = $this->panelAuthenticator->user();

        if ($screen->isPage()) {
            $pageClass = $screen->pageClass();
            if (!is_string($pageClass) || $pageClass === '') {
                throw new \InvalidArgumentException('Resolved page screen is missing a page class.');
            }

            return new ScreenPayload([
                'metadata' => $context->metadata()->toArray(),
                'page' => $this->buildPagePayload($pageClass),
                'resource' => null,
                'table' => null,
                'form' => null,
                'detail' => null,
                'actions' => [],
                'navigation_item' => null,
                'component' => $this->buildPageComponentPayload($pageClass, $context, $user),
                'workspace' => is_a($pageClass, CustomPageContract::class, true)
                    ? $this->workspacePayloadAssembler->assemble($pageClass, $context->metadata()->toArray())
                    : null,
            ]);
        }

        /** @var class-string<Resource>|null $resourceClass */
        $resourceClass = $screen->resourceClass();
        $resourcePage = (string) Arr::get($context->request()->route()->defaults, 'flashboard.resource_page', 'index');
        $record = $resourcePage === 'detail' || $resourcePage === 'edit'
            ? $resourceClass::resolveRecord($context->request()->route('record'))
            : null;

        if (!is_string($resourceClass) || $resourceClass === '') {
            throw new \InvalidArgumentException('Resolved resource screen is missing a resource class.');
        }

        $payload = [
            'metadata' => $context->metadata()->toArray(),
            'page' => null,
            'resource' => [
                'class' => $resourceClass,
                'key' => $resourceClass::key(),
                'name' => $resourceClass::name(),
                'page' => $resourcePage,
                'surfaces' => $this->resourceSurfaceResolver->availability($resourceClass, $user),
                'pages' => $this->resourceSurfaceResolver->pagePayloads($resourceClass, $user),
                'routes' => $this->resourceRoutes($resourceClass, $record),
            ],
            'table' => $resourcePage === 'index'
                ? array_merge(
                    $this->tablePayloadAssembler->assemble($resourceClass)->toArray(),
                    ['dataset' => $this->resourceListDataSource->resolve($resourceClass, $context->request())],
                )
                : $this->tablePayloadAssembler->assemble($resourceClass)->toArray(),
            'form' => in_array($resourcePage, ['create', 'edit'], true)
                ? $this->resourceFormDataSource->resolve($resourceClass, $record, $context->request())
                : null,
            'detail' => $resourcePage === 'detail'
                ? $this->resourceDetailDataSource->resolve($resourceClass, $record)
                : null,
            'actions' => $this->extensionRegistry->extendActions(
                $resourceClass,
                $this->assembleActions(
                    array_values(array_filter(
                        $this->resourceSurfaceResolver->actions($resourceClass),
                        fn(ActionContract $action): bool => $this->screenAccessResolver->canViewAction(
                            $resourceClass,
                            $action->key(),
                            $user,
                        ),
                    )),
                    $resourceClass,
                    $record?->getKey(),
                ),
            ),
            'navigation_item' => $resourceClass::navigationItem(
                NavigationItem::make($resourceClass::key()),
            )->toArray(),
            'relations' => $resourceClass::relations() !== [] && $record !== null
                ? $this->resourceDetailDataSource->resolve($resourceClass, $record, $resourcePage)['relations']
                : [],
            'workspace' => null,
        ];

        $extendedPayload = $this->extensionRegistry->extendPayload($resourceClass, $resourcePage, $payload);
        $this->runtimeHookDispatcher->dispatch($resourceClass, 'payload.assembled', [
            'page' => $resourcePage,
            'screen_key' => $context->screen()->key(),
        ]);

        return new ScreenPayload($extendedPayload);
    }

    /**
     * @param class-string<PageDefinitionContract> $pageClass
     *
     * @return array<string, mixed>
     */
    private function buildPagePayload(string $pageClass): array
    {
        return [
            'class' => $pageClass,
            'key' => $pageClass::key(),
            'title' => $pageClass::title(),
            'type' => $pageClass::type()->value,
            'uri' => $pageClass::uri(),
            'navigation_label' => $pageClass::navigationLabel(),
            'is_navigable' => $pageClass::isNavigable(),
            'middleware' => $pageClass::middleware(),
        ];
    }

    /**
     * @param class-string<PageDefinitionContract> $pageClass
     *
     * @return array{name: string, props: array<string, mixed>, assets: array{scripts: list<array{url: string, type: string}>, styles: list<array{url: string}>}}|null
     */
    private function buildPageComponentPayload(
        string $pageClass,
        RuntimeRequestContext $context,
        ?\Illuminate\Contracts\Auth\Authenticatable $user,
    ): ?array {
        if (!is_a($pageClass, ComponentPageContract::class, true)) {
            return null;
        }

        $component = $pageClass::component();
        if ($component === null) {
            return null;
        }

        $component = trim($component);
        if ($component === '' || preg_match(self::COMPONENT_NAME_PATTERN, $component) !== 1) {
            throw new \InvalidArgumentException("Custom page component name [{$component}] is invalid.");
        }

        $componentContext = $this->pageComponentContextFactory->make($context, $user);

        return [
            'name' => $component,
            'props' => $pageClass::componentProps($componentContext),
            'assets' => $this->normalizeComponentAssets($pageClass::componentAssets()),
        ];
    }

    /**
     * @param array<string, mixed> $assets
     *
     * @return array{scripts: list<array{url: string, type: string}>, styles: list<array{url: string}>}
     */
    private function normalizeComponentAssets(array $assets): array
    {
        return [
            'scripts' => $this->normalizeComponentAssetList((array) Arr::get($assets, 'scripts', []), 'script'),
            'styles' => $this->normalizeComponentAssetList((array) Arr::get($assets, 'styles', []), 'style'),
        ];
    }

    /**
     * @param array<mixed> $assets
     *
     * @return list<array{url: string, type: string}|array{url: string}>
     */
    private function normalizeComponentAssetList(array $assets, string $kind): array
    {
        $normalized = [];

        foreach ($assets as $asset) {
            $normalized[] = $this->normalizeComponentAsset($asset, $kind);
        }

        return $normalized;
    }

    /**
     * @return array{url: string, type: string}|array{url: string}
     */
    private function normalizeComponentAsset(mixed $asset, string $kind): array
    {
        if (is_string($asset)) {
            $url = $this->normalizeComponentAssetUrl($asset);

            return $kind === 'script'
                ? ['url' => $url, 'type' => self::COMPONENT_SCRIPT_TYPE_MODULE]
                : ['url' => $url];
        }

        if (!is_array($asset)) {
            throw new \InvalidArgumentException('Custom page component assets must be URL strings or descriptor arrays.');
        }

        $url = $this->normalizeComponentAssetUrl((string) Arr::get($asset, 'url', ''));
        if ($kind !== 'script') {
            return ['url' => $url];
        }

        $module = Arr::get($asset, 'module');
        $type = Arr::get($asset, 'type');

        if (is_bool($module)) {
            $type = $module ? self::COMPONENT_SCRIPT_TYPE_MODULE : self::COMPONENT_SCRIPT_TYPE_CLASSIC;
        }
        if (!is_string($type) || trim($type) === '') {
            $type = self::COMPONENT_SCRIPT_TYPE_MODULE;
        }

        return [
            'url' => $url,
            'type' => trim($type),
        ];
    }

    private function normalizeComponentAssetUrl(string $url): string
    {
        $url = trim($url);
        if (
            $url === ''
            || str_starts_with($url, '//')
            || (
                preg_match(self::COMPONENT_ASSET_SCHEME_PATTERN, $url) === 1
                && preg_match('/^https?:\/\//i', $url) !== 1
            )
        ) {
            throw new \InvalidArgumentException('Custom page component asset URLs must be non-empty HTTP or path URLs.');
        }

        return $url;
    }

    /**
     * @param list<ActionContract> $actions
     * @param class-string<Resource> $resourceClass
     *
     * @return list<array<string, mixed>>
     */
    private function assembleActions(array $actions, string $resourceClass, mixed $recordKey = null): array
    {
        return array_values(array_map(
            function (ActionContract $action) use ($resourceClass, $recordKey): array {
                $payload = $this->actionPayloadAssembler->assemble($action)->toArray();
                $payload['url'] = $recordKey === null
                    ? route(
                        config('flashboard.route_name_prefix', 'flashboard.') . 'resources.' . $resourceClass::key() . '.actions.index',
                        ['action' => $action->key()],
                    )
                    : route(
                        config('flashboard.route_name_prefix', 'flashboard.') . 'resources.' . $resourceClass::key() . '.actions.record',
                        ['record' => $recordKey, 'action' => $action->key()],
                    );
                $payload['method'] = 'post';

                return $payload;
            },
            $actions,
        ));
    }

    /**
     * @param class-string<Resource> $resourceClass
     *
     * @return array<string, string|null>
     */
    private function resourceRoutes(string $resourceClass, mixed $recordKey = null): array
    {
        $hasDetailSurface = $this->resourceSurfaceResolver->hasDetailSurfaceForResource($resourceClass);

        return [
            'index' => route(
                config('flashboard.route_name_prefix', 'flashboard.') . 'resources.' . $resourceClass::key() . '.index',
            ),
            'create' => route(
                config('flashboard.route_name_prefix', 'flashboard.') . 'resources.' . $resourceClass::key() . '.create',
            ),
            'detail' => $recordKey === null || !$hasDetailSurface
                ? null
                : route(
                    config('flashboard.route_name_prefix', 'flashboard.') . 'resources.' . $resourceClass::key() . '.detail',
                    ['record' => $recordKey],
                ),
            'edit' => $recordKey === null
                ? null
                : route(
                    config('flashboard.route_name_prefix', 'flashboard.') . 'resources.' . $resourceClass::key() . '.edit',
                    ['record' => $recordKey],
                ),
        ];
    }
}
