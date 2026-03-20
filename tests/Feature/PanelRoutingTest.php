<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Tests\Feature;

use Pepperfm\Flashboard\Core\Pages\DashboardPage;
use Pepperfm\Flashboard\Core\Registry\PageRegistry;
use Pepperfm\Flashboard\Core\Registry\ResourceRegistry;
use Pepperfm\Flashboard\Integration\Laravel\Routing\PanelRouteRegistrar;
use Pepperfm\Flashboard\Tests\TestCase;

final class PanelRoutingTest extends TestCase
{
    public function test_login_route_is_registered(): void
    {
        $this->app->instance(PageRegistry::class, new PageRegistry());
        $this->app->instance(ResourceRegistry::class, new ResourceRegistry());

        (new PanelRouteRegistrar(
            $this->app->make(PageRegistry::class),
            $this->app->make(ResourceRegistry::class),
        ))->register();
        $this->router->getRoutes()->refreshNameLookups();

        self::assertNotNull($this->router->getRoutes()->getByName('flashboard.auth.login'));
    }

    public function test_dashboard_route_is_registered_when_page_exists(): void
    {
        $pageRegistry = new PageRegistry();
        $pageRegistry->register(DashboardPage::class);

        $this->app->instance(PageRegistry::class, $pageRegistry);
        $this->app->instance(ResourceRegistry::class, new ResourceRegistry());

        (new PanelRouteRegistrar(
            $this->app->make(PageRegistry::class),
            $this->app->make(ResourceRegistry::class),
        ))->register();
        $this->router->getRoutes()->refreshNameLookups();

        self::assertNotNull($this->router->getRoutes()->getByName('flashboard.home'));
    }
}
