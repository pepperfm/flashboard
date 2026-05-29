<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Tests\Feature;

use Pepperfm\Flashboard\Core\Pages\DashboardPage;
use Pepperfm\Flashboard\Core\Registry\PageRegistry;
use Pepperfm\Flashboard\Core\Registry\ResourceRegistry;
use Pepperfm\Flashboard\Core\Resources\ResourceSurfaceResolver;
use Pepperfm\Flashboard\Integration\Laravel\Routing\PanelRouteRegistrar;
use Pepperfm\Flashboard\Integration\Laravel\Auth\PolicyBridge;
use Pepperfm\Flashboard\Tests\Fixtures\Flashboard\UsersResource;
use Pepperfm\Flashboard\Tests\Fixtures\Resources\BelongsToProductResource;
use Pepperfm\Flashboard\Tests\Fixtures\Resources\LazyFilterOptionsResource;
use Pepperfm\Flashboard\Tests\Fixtures\Resources\RelationManagerOrderResource;
use Pepperfm\Flashboard\Tests\TestCase;

final class PanelRoutingTest extends TestCase
{
    public function test_login_route_is_registered(): void
    {
        $this->app->instance(PageRegistry::class, new PageRegistry());
        $this->app->instance(ResourceRegistry::class, new ResourceRegistry());

        new PanelRouteRegistrar(
            $this->app->make(PageRegistry::class),
            $this->app->make(ResourceRegistry::class),
            new ResourceSurfaceResolver(new \Pepperfm\Flashboard\Core\Authorization\Visibility\ScreenAccessResolver(new PolicyBridge())),
        )->register();
        $this->router->getRoutes()->refreshNameLookups();

        self::assertNotNull($this->router->getRoutes()->getByName('flashboard.auth.login'));
    }

    public function test_dashboard_route_is_registered_when_page_exists(): void
    {
        $pageRegistry = new PageRegistry();
        $pageRegistry->register(DashboardPage::class);

        $this->app->instance(PageRegistry::class, $pageRegistry);
        $this->app->instance(ResourceRegistry::class, new ResourceRegistry());

        new PanelRouteRegistrar(
            $this->app->make(PageRegistry::class),
            $this->app->make(ResourceRegistry::class),
            new ResourceSurfaceResolver(new \Pepperfm\Flashboard\Core\Authorization\Visibility\ScreenAccessResolver(new PolicyBridge())),
        )->register();
        $this->router->getRoutes()->refreshNameLookups();

        self::assertNotNull($this->router->getRoutes()->getByName('flashboard.home'));
    }

    public function test_detail_route_is_not_registered_when_resource_has_no_detail_surface(): void
    {
        $pageRegistry = new PageRegistry();
        $resourceRegistry = new ResourceRegistry();
        $resourceRegistry->register(UsersResource::class);

        $this->app->instance(PageRegistry::class, $pageRegistry);
        $this->app->instance(ResourceRegistry::class, $resourceRegistry);

        new PanelRouteRegistrar(
            $this->app->make(PageRegistry::class),
            $this->app->make(ResourceRegistry::class),
            new ResourceSurfaceResolver(new \Pepperfm\Flashboard\Core\Authorization\Visibility\ScreenAccessResolver(new PolicyBridge())),
        )->register();
        $this->router->getRoutes()->refreshNameLookups();

        self::assertNull($this->router->getRoutes()->getByName('flashboard.resources.users.detail'));
        self::assertNotNull($this->router->getRoutes()->getByName('flashboard.resources.users.edit'));
    }

    public function test_filter_options_route_is_registered_before_record_routes(): void
    {
        $pageRegistry = new PageRegistry();
        $resourceRegistry = new ResourceRegistry();
        $resourceRegistry->register(LazyFilterOptionsResource::class);

        $this->app->instance(PageRegistry::class, $pageRegistry);
        $this->app->instance(ResourceRegistry::class, $resourceRegistry);

        (new PanelRouteRegistrar(
            $this->app->make(PageRegistry::class),
            $this->app->make(ResourceRegistry::class),
            new ResourceSurfaceResolver(new \Pepperfm\Flashboard\Core\Authorization\Visibility\ScreenAccessResolver(new PolicyBridge())),
        ))->register();
        $this->router->getRoutes()->refreshNameLookups();

        $route = $this->router->getRoutes()->match(
            \Illuminate\Http\Request::create('/admin/resources/lazy_filter_options/_options/status'),
        );

        self::assertSame('flashboard.resources.lazy_filter_options.filters.options', $route->getName());

        $legacyRoute = $this->router->getRoutes()->match(
            \Illuminate\Http\Request::create('/admin/resources/lazy_filter_options/_filter-options/status'),
        );

        self::assertSame('flashboard.resources.lazy_filter_options.filters.legacy-options', $legacyRoute->getName());
    }

    public function test_relation_options_route_is_registered_before_record_routes(): void
    {
        $pageRegistry = new PageRegistry();
        $resourceRegistry = new ResourceRegistry();
        $resourceRegistry->register(BelongsToProductResource::class);

        $this->app->instance(PageRegistry::class, $pageRegistry);
        $this->app->instance(ResourceRegistry::class, $resourceRegistry);

        new PanelRouteRegistrar(
            $this->app->make(PageRegistry::class),
            $this->app->make(ResourceRegistry::class),
            new ResourceSurfaceResolver(new \Pepperfm\Flashboard\Core\Authorization\Visibility\ScreenAccessResolver(new PolicyBridge())),
        )->register();
        $this->router->getRoutes()->refreshNameLookups();

        $route = $this->router->getRoutes()->match(
            \Illuminate\Http\Request::create('/admin/resources/belongs_to_product/_relation-options/category_id'),
        );

        self::assertSame('flashboard.resources.belongs_to_product.relations.options', $route->getName());
    }

    public function test_relation_manager_routes_are_registered_before_record_routes(): void
    {
        $pageRegistry = new PageRegistry();
        $resourceRegistry = new ResourceRegistry();
        $resourceRegistry->register(RelationManagerOrderResource::class);

        $this->app->instance(PageRegistry::class, $pageRegistry);
        $this->app->instance(ResourceRegistry::class, $resourceRegistry);

        (new PanelRouteRegistrar(
            $this->app->make(PageRegistry::class),
            $this->app->make(ResourceRegistry::class),
            new ResourceSurfaceResolver(new \Pepperfm\Flashboard\Core\Authorization\Visibility\ScreenAccessResolver(new PolicyBridge())),
        ))->register();
        $this->router->getRoutes()->refreshNameLookups();

        $recordsRoute = $this->router->getRoutes()->match(
            \Illuminate\Http\Request::create('/admin/resources/relation_manager_order/1/_relations/items'),
        );

        self::assertSame('flashboard.resources.relation_manager_order.relations.records', $recordsRoute->getName());

        $optionsRoute = $this->router->getRoutes()->match(
            \Illuminate\Http\Request::create('/admin/resources/relation_manager_order/1/_relations/items/options'),
        );

        self::assertSame('flashboard.resources.relation_manager_order.relations.attach-options', $optionsRoute->getName());
    }

    public function test_destroy_route_is_registered_for_resource_records(): void
    {
        $pageRegistry = new PageRegistry();
        $resourceRegistry = new ResourceRegistry();
        $resourceRegistry->register(LazyFilterOptionsResource::class);

        $this->app->instance(PageRegistry::class, $pageRegistry);
        $this->app->instance(ResourceRegistry::class, $resourceRegistry);

        (new PanelRouteRegistrar(
            $this->app->make(PageRegistry::class),
            $this->app->make(ResourceRegistry::class),
            new ResourceSurfaceResolver(new \Pepperfm\Flashboard\Core\Authorization\Visibility\ScreenAccessResolver(new PolicyBridge())),
        ))->register();
        $this->router->getRoutes()->refreshNameLookups();

        $route = $this->router->getRoutes()->match(
            \Illuminate\Http\Request::create('/admin/resources/lazy_filter_options/1', 'DELETE'),
        );

        self::assertSame('flashboard.resources.lazy_filter_options.destroy', $route->getName());
    }
}
