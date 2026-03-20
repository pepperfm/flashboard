<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Core\Runtime\Assemblers;

use Illuminate\Support\Arr;
use Pepperfm\Flashboard\Contracts\Actions\ActionContract;
use Pepperfm\Flashboard\Contracts\Pages\CustomPageContract;
use Pepperfm\Flashboard\Contracts\Pages\PageDefinitionContract;
use Pepperfm\Flashboard\Contracts\Resources\Resource;
use Pepperfm\Flashboard\Core\Authorization\Visibility\ScreenAccessResolver;
use Pepperfm\Flashboard\Core\Extensions\ExtensionRegistry;
use Pepperfm\Flashboard\Core\Hooks\RuntimeHookDispatcher;
use Pepperfm\Flashboard\Core\Navigation\Builders\NavigationItem;
use Pepperfm\Flashboard\Core\Runtime\Context\RuntimeRequestContext;
use Pepperfm\Flashboard\Core\Runtime\Payloads\ScreenPayload;
use Pepperfm\Flashboard\Core\Runtime\Screens\ScreenKind;
use Pepperfm\Flashboard\Core\Runtime\Workspaces\WorkspacePayloadAssembler;
use Pepperfm\Flashboard\Integration\Laravel\DataSources\ResourceDetailDataSource;
use Pepperfm\Flashboard\Integration\Laravel\DataSources\ResourceListDataSource;
use Pepperfm\Flashboard\Integration\Laravel\DataSources\ResourceFormDataSource;

final readonly class ScreenPayloadAssembler
{
    public function __construct(
        private ActionPayloadAssembler $actionPayloadAssembler,
        private TablePayloadAssembler $tablePayloadAssembler,
        private ResourceListDataSource $resourceListDataSource,
        private ResourceFormDataSource $resourceFormDataSource,
        private ResourceDetailDataSource $resourceDetailDataSource,
        private WorkspacePayloadAssembler $workspacePayloadAssembler,
        private ScreenAccessResolver $screenAccessResolver,
        private \Pepperfm\Flashboard\Integration\Laravel\Auth\PanelAuthenticator $panelAuthenticator,
        private ExtensionRegistry $extensionRegistry,
        private RuntimeHookDispatcher $runtimeHookDispatcher,
    ) {
    }

    public function assemble(RuntimeRequestContext $context): ScreenPayload
    {
        $screen = $context->screen();
        if ($screen->kind() === ScreenKind::Page) {
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
                'workspace' => is_a($pageClass, CustomPageContract::class, true)
                    ? $this->workspacePayloadAssembler->assemble($pageClass, $context->metadata()->toArray())
                    : null,
            ]);
        }

        $resourceClass = $screen->resourceClass();
        $resourcePage = (string) Arr::get($context->request()->route()->defaults, 'flashboard.resource_page', 'index');
        $record = $resourcePage === 'detail' || $resourcePage === 'edit'
            ? $resourceClass::resolveRecord($context->request()->route('record'))
            : null;
        $user = $this->panelAuthenticator->user();

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
                'routes' => $this->resourceRoutes($resourceClass, $record),
            ],
            'table' => $resourcePage === 'index'
                ? array_merge(
                    $this->tablePayloadAssembler->assemble($resourceClass)->toArray(),
                    ['dataset' => $this->resourceListDataSource->resolve($resourceClass, $context->request())],
                )
                : $this->tablePayloadAssembler->assemble($resourceClass)->toArray(),
            'form' => in_array($resourcePage, ['create', 'edit'], true)
                ? $this->resourceFormDataSource->resolve($resourceClass, $record)
                : null,
            'detail' => $resourcePage === 'detail'
                ? $this->resourceDetailDataSource->resolve($resourceClass, $record)
                : null,
            'actions' => $this->extensionRegistry->extendActions(
                $resourceClass,
                $this->assembleActions(
                    array_values(array_filter(
                        $resourceClass::actions(),
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
                ? $this->resourceDetailDataSource->resolve($resourceClass, $record)['relations']
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
     * @param list<ActionContract> $actions
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
                        config('flashboard.route_name_prefix', 'flashboard.')
                        . 'resources.' . $resourceClass::key() . '.actions.index',
                        ['action' => $action->key()],
                    )
                    : route(
                        config('flashboard.route_name_prefix', 'flashboard.')
                        . 'resources.' . $resourceClass::key() . '.actions.record',
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
        return [
            'index' => route(
                config('flashboard.route_name_prefix', 'flashboard.')
                . 'resources.' . $resourceClass::key() . '.index',
            ),
            'create' => route(
                config('flashboard.route_name_prefix', 'flashboard.')
                . 'resources.' . $resourceClass::key() . '.create',
            ),
            'detail' => $recordKey === null
                ? null
                : route(
                    config('flashboard.route_name_prefix', 'flashboard.')
                    . 'resources.' . $resourceClass::key() . '.detail',
                    ['record' => $recordKey],
                ),
            'edit' => $recordKey === null
                ? null
                : route(
                    config('flashboard.route_name_prefix', 'flashboard.')
                    . 'resources.' . $resourceClass::key() . '.edit',
                    ['record' => $recordKey],
                ),
        ];
    }
}
