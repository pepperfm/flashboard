<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Tests\Feature;

use Illuminate\Routing\Route;
use Pepperfm\Flashboard\Core\Authorization\Visibility\ScreenAccessResolver;
use Pepperfm\Flashboard\Core\Extensions\ExtensionRegistry;
use Pepperfm\Flashboard\Core\Hooks\RuntimeHookDispatcher;
use Pepperfm\Flashboard\Core\Panel\PanelConfig;
use Pepperfm\Flashboard\Core\Registry\ResourceRegistry;
use Pepperfm\Flashboard\Core\Relations\RelationPayloadFactory;
use Pepperfm\Flashboard\Core\Resources\ResourceSurfaceResolver;
use Pepperfm\Flashboard\Core\Runtime\Assemblers\ActionPayloadAssembler;
use Pepperfm\Flashboard\Core\Runtime\Assemblers\DetailPayloadAssembler;
use Pepperfm\Flashboard\Core\Runtime\Assemblers\FormPayloadAssembler;
use Pepperfm\Flashboard\Core\Runtime\Assemblers\TablePayloadAssembler;
use Pepperfm\Flashboard\Core\Runtime\Context\RuntimeRequestContext;
use Pepperfm\Flashboard\Core\Runtime\Metadata\RuntimeMetadata;
use Pepperfm\Flashboard\Core\Runtime\Screens\ResolvedScreen;
use Pepperfm\Flashboard\Core\Runtime\Screens\ScreenKind;
use Pepperfm\Flashboard\Core\Runtime\Workspaces\WorkspacePayloadAssembler;
use Pepperfm\Flashboard\Integration\Laravel\Auth\PanelAuthenticator;
use Pepperfm\Flashboard\Integration\Laravel\Auth\PolicyBridge;
use Pepperfm\Flashboard\Integration\Laravel\DataSources\ResourceDetailDataSource;
use Pepperfm\Flashboard\Integration\Laravel\DataSources\ResourceFormDataSource;
use Pepperfm\Flashboard\Integration\Laravel\DataSources\ResourceListDataSource;
use Pepperfm\Flashboard\Integration\Laravel\DataSources\ResourceRelationRecordsDataSource;
use Pepperfm\Flashboard\Integration\Laravel\Runtime\LaravelScreenPayloadAssembler;
use Pepperfm\Flashboard\Integration\Laravel\Runtime\PageComponentContextFactory;
use Pepperfm\Flashboard\Tests\Fixtures\Components\ComponentReviewQueuePage;
use Pepperfm\Flashboard\Tests\Fixtures\Components\ComponentWithoutAssetsPage;
use Pepperfm\Flashboard\Tests\Fixtures\Components\InvalidComponentAssetPage;
use Pepperfm\Flashboard\Tests\Fixtures\Components\InvalidComponentNamePage;
use Pepperfm\Flashboard\Tests\Fixtures\Components\UnsafeComponentAssetPage;
use Pepperfm\Flashboard\Tests\Fixtures\Flashboard\ReviewQueuePage;
use Pepperfm\Flashboard\Tests\TestCase;

final class CustomPageComponentPayloadTest extends TestCase
{
    public function test_custom_pages_default_to_workspace_only_rendering(): void
    {
        self::assertNull(ReviewQueuePage::component());
        self::assertSame([], ReviewQueuePage::componentProps(['query' => ['status' => 'open']]));
        self::assertSame([
            'scripts' => [],
            'styles' => [],
        ], ReviewQueuePage::componentAssets());
        self::assertSame([
            'key' => 'review-queue-page',
            'title' => 'Review Queue',
            'description' => null,
            'actions' => [],
            'context' => ['query' => ['status' => 'open']],
        ], ReviewQueuePage::workspace(['query' => ['status' => 'open']]));
    }

    public function test_page_component_context_contains_sanitized_runtime_inputs(): void
    {
        $request = \Illuminate\Http\Request::create(
            '/admin/queues/review/42',
            'GET',
            [
                'status' => 'open',
                'filters' => [
                    'priority' => 'high',
                ],
            ],
        );
        $route = new Route(['GET'], '/admin/queues/review/{record}', []);
        $route->defaults('flashboard.page', ReviewQueuePage::class);
        $route->bind($request);
        $request->setRouteResolver(static fn(): Route => $route);

        $context = $this->runtimeContext($request);
        $payload = new PageComponentContextFactory()->make($context, $this->authenticatedUser(123));

        self::assertSame('Review Queue Admin', $payload['metadata']['panel_name']);
        self::assertSame('open', $payload['query']['status']);
        self::assertSame(['priority' => 'high'], $payload['query']['filters']);
        self::assertSame('42', $payload['route_parameters']['record']);
        self::assertSame(ReviewQueuePage::class, $payload['page_class']);
        self::assertSame('review-queue-page', $payload['page_key']);
        self::assertSame(123, $payload['user_id']);
        self::assertArrayNotHasKey('headers', $payload);
        self::assertArrayNotHasKey('cookies', $payload);
        self::assertArrayNotHasKey('session', $payload);
    }

    public function test_component_page_payload_includes_component_props_and_assets(): void
    {
        $request = \Illuminate\Http\Request::create(
            '/admin/queues/component-review/42',
            'GET',
            ['status' => 'open'],
        );
        $route = new Route(['GET'], '/admin/queues/component-review/{record}', []);
        $route->defaults('flashboard.page', ComponentReviewQueuePage::class);
        $route->bind($request);
        $request->setRouteResolver(static fn(): Route => $route);

        $payload = $this->screenPayload(ComponentReviewQueuePage::class, $request);

        self::assertSame([
            'name' => 'review-queue',
            'props' => [
                'initial_filters' => ['status' => 'open'],
                'record' => '42',
                'screen' => 'component-review-queue-page',
            ],
            'assets' => [
                'scripts' => [
                    [
                        'url' => '/build/flashboard-pages.js',
                        'type' => 'module',
                    ],
                    [
                        'url' => '/build/flashboard-classic-page.js',
                        'type' => 'text/javascript',
                    ],
                ],
                'styles' => [
                    [
                        'url' => '/build/flashboard-pages.css',
                    ],
                ],
            ],
        ], $payload['component']);
        self::assertSame('Component Review Queue', $payload['page']['title']);
        self::assertSame('Component Review Queue', $payload['workspace']['title']);
    }

    public function test_workspace_only_page_payload_keeps_component_null(): void
    {
        $payload = $this->screenPayload(ReviewQueuePage::class);

        self::assertNull($payload['component']);
        self::assertSame('Review Queue', $payload['workspace']['title']);
        self::assertSame('Review Queue Admin', $payload['workspace']['context']['panel_name']);
        self::assertArrayNotHasKey('query', $payload['workspace']['context']);
    }

    public function test_component_page_payload_can_omit_assets(): void
    {
        $payload = $this->screenPayload(ComponentWithoutAssetsPage::class);

        self::assertSame([
            'name' => 'assetless-review',
            'props' => [],
            'assets' => [
                'scripts' => [],
                'styles' => [],
            ],
        ], $payload['component']);
    }

    public function test_invalid_component_name_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Custom page component name');

        $this->screenPayload(InvalidComponentNamePage::class);
    }

    public function test_invalid_component_asset_definition_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Custom page component asset URLs');

        $this->screenPayload(InvalidComponentAssetPage::class);
    }

    public function test_unsafe_component_asset_url_scheme_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Custom page component asset URLs');

        $this->screenPayload(UnsafeComponentAssetPage::class);
    }

    /**
     * @param class-string<\Pepperfm\Flashboard\Contracts\Pages\PageDefinitionContract> $pageClass
     *
     * @return array<string, mixed>
     */
    private function screenPayload(
        string $pageClass,
        ?\Illuminate\Http\Request $request = null,
    ): array {
        $request ??= $this->pageRequest($pageClass);

        return $this->screenPayloadAssembler()
            ->assemble($this->runtimeContext($request, $pageClass))
            ->toArray();
    }

    /**
     * @param class-string<\Pepperfm\Flashboard\Contracts\Pages\PageDefinitionContract> $pageClass
     */
    private function pageRequest(string $pageClass): \Illuminate\Http\Request
    {
        $request = \Illuminate\Http\Request::create('/admin/' . trim($pageClass::uri(), '/'), 'GET');
        $route = new Route(['GET'], '/' . trim($pageClass::uri(), '/'), []);
        $route->defaults('flashboard.page', $pageClass);
        $route->bind($request);
        $request->setRouteResolver(static fn(): Route => $route);

        return $request;
    }

    /**
     * @param class-string<\Pepperfm\Flashboard\Contracts\Pages\PageDefinitionContract> $pageClass
     */
    private function runtimeContext(\Illuminate\Http\Request $request, string $pageClass = ReviewQueuePage::class): RuntimeRequestContext
    {
        $panel = PanelConfig::fromArray([
            'name' => 'Review Queue Admin',
            'path' => 'admin',
            'route_name_prefix' => 'flashboard.',
        ]);
        $screen = new ResolvedScreen(
            kind: ScreenKind::Page,
            key: $pageClass::key(),
            routeName: 'flashboard.pages.' . $pageClass::key(),
            pageClass: $pageClass,
            resourceClass: null,
        );

        return new RuntimeRequestContext(
            request: $request,
            panel: $panel,
            screen: $screen,
            metadata: new RuntimeMetadata(
                panelName: $panel->name(),
                panelPath: $panel->path(),
                routeName: $screen->routeName(),
                screenKey: $screen->key(),
                screenKind: $screen->kind(),
                pageClass: $screen->pageClass(),
                resourceClass: $screen->resourceClass(),
            ),
        );
    }

    private function screenPayloadAssembler(): LaravelScreenPayloadAssembler
    {
        $screenAccessResolver = new ScreenAccessResolver(new PolicyBridge());
        $extensionRegistry = new ExtensionRegistry();
        $runtimeHookDispatcher = new RuntimeHookDispatcher();
        $tablePayloadAssembler = new TablePayloadAssembler();
        $authenticator = $this->authenticator();
        $resourceRegistry = new ResourceRegistry();
        $resourceSurfaceResolver = new ResourceSurfaceResolver($screenAccessResolver);
        $relationRecordsDataSource = new ResourceRelationRecordsDataSource(
            $authenticator,
            $screenAccessResolver,
            $extensionRegistry,
            $resourceRegistry,
            $resourceSurfaceResolver,
        );

        return new LaravelScreenPayloadAssembler(
            new ActionPayloadAssembler(),
            $tablePayloadAssembler,
            new ResourceListDataSource(
                $tablePayloadAssembler,
                $screenAccessResolver,
                $authenticator,
                $extensionRegistry,
                $runtimeHookDispatcher,
                $resourceSurfaceResolver,
            ),
            new ResourceFormDataSource(
                new FormPayloadAssembler(),
                $screenAccessResolver,
                $authenticator,
                $extensionRegistry,
                $resourceSurfaceResolver,
                $resourceRegistry,
            ),
            new ResourceDetailDataSource(
                new DetailPayloadAssembler(),
                $screenAccessResolver,
                $authenticator,
                new RelationPayloadFactory(),
                $relationRecordsDataSource,
                $extensionRegistry,
            ),
            new WorkspacePayloadAssembler(),
            new PageComponentContextFactory(),
            $screenAccessResolver,
            $resourceSurfaceResolver,
            $authenticator,
            $extensionRegistry,
            $runtimeHookDispatcher,
        );
    }

    private function authenticator(): PanelAuthenticator
    {
        return new PanelAuthenticator(new class () implements \Illuminate\Contracts\Auth\Factory {
            public function guard($name = null): \Illuminate\Contracts\Auth\Guard|\Illuminate\Contracts\Auth\StatefulGuard
            {
                return new class () implements \Illuminate\Contracts\Auth\StatefulGuard {
                    public function check(): bool
                    {
                        return false;
                    }

                    public function guest(): bool
                    {
                        return true;
                    }

                    public function user(): ?\Illuminate\Contracts\Auth\Authenticatable
                    {
                        return null;
                    }

                    public function id(): int|string|null
                    {
                        return null;
                    }

                    public function validate(array $credentials = []): bool
                    {
                        return false;
                    }

                    public function hasUser(): bool
                    {
                        return false;
                    }

                    public function setUser(\Illuminate\Contracts\Auth\Authenticatable $user): static
                    {
                        return $this;
                    }

                    public function attempt(array $credentials = [], $remember = false): bool
                    {
                        return false;
                    }

                    public function once(array $credentials = []): bool
                    {
                        return false;
                    }

                    public function login(\Illuminate\Contracts\Auth\Authenticatable $user, $remember = false): void
                    {
                    }

                    public function loginUsingId($id, $remember = false): \Illuminate\Contracts\Auth\Authenticatable|false
                    {
                        return false;
                    }

                    public function onceUsingId($id): \Illuminate\Contracts\Auth\Authenticatable|false
                    {
                        return false;
                    }

                    public function viaRemember(): bool
                    {
                        return false;
                    }

                    public function logout(): void
                    {
                    }
                };
            }

            public function shouldUse($name): void
            {
            }
        });
    }

    private function authenticatedUser(int|string $id): \Illuminate\Contracts\Auth\Authenticatable
    {
        return new class ($id) implements \Illuminate\Contracts\Auth\Authenticatable {
            public function __construct(
                private int|string $id,
            ) {
            }

            public function getAuthIdentifierName(): string
            {
                return 'id';
            }

            public function getAuthIdentifier(): int|string
            {
                return $this->id;
            }

            public function getAuthPasswordName(): string
            {
                return 'password';
            }

            public function getAuthPassword(): string
            {
                return '';
            }

            public function getRememberToken(): ?string
            {
                return null;
            }

            public function setRememberToken($value): void
            {
            }

            public function getRememberTokenName(): string
            {
                return 'remember_token';
            }
        };
    }
}
