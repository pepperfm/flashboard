<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Core\Runtime\Resolvers;

use Illuminate\Support\Arr;
use Pepperfm\Flashboard\Contracts\Panel\PanelDefinitionContract;
use Pepperfm\Flashboard\Core\Pages\DashboardPage;
use Pepperfm\Flashboard\Core\Registry\PageRegistry;
use Pepperfm\Flashboard\Core\Registry\ResourceRegistry;
use Pepperfm\Flashboard\Core\Runtime\Screens\ResolvedScreen;
use Pepperfm\Flashboard\Core\Runtime\Screens\ScreenKind;

final readonly class ScreenResolver
{
    public function __construct(
        private PageRegistry $pageRegistry,
        private ResourceRegistry $resourceRegistry,
    ) {
    }

    public function resolve(\Illuminate\Http\Request $request, PanelDefinitionContract $panel): ResolvedScreen
    {
        $route = $request->route();

        if (!$route instanceof \Illuminate\Routing\Route) {
            throw new \InvalidArgumentException('Flashboard runtime requires a resolved Laravel route.');
        }

        $defaults = $route->defaults;
        $routeName = $route->getName() ?? $panel->routeNamePrefix() . 'unknown';
        $pageClass = Arr::get($defaults, 'flashboard.page');
        $resourceClass = Arr::get($defaults, 'flashboard.resource');

        if (is_string($pageClass) && $pageClass !== '') {
            if (!$this->pageRegistry->has($pageClass)) {
                throw new \InvalidArgumentException("Page [{$pageClass}] is not registered in the Flashboard page registry.");
            }

            return new ResolvedScreen(
                kind: ScreenKind::Page,
                key: $pageClass::key(),
                routeName: $routeName,
                pageClass: $pageClass,
                resourceClass: null,
            );
        }

        if (is_string($resourceClass) && $resourceClass !== '') {
            if (!$this->resourceRegistry->has($resourceClass)) {
                throw new \InvalidArgumentException("Resource [{$resourceClass}] is not registered in the Flashboard resource registry.");
            }

            return new ResolvedScreen(
                kind: ScreenKind::Resource,
                key: $resourceClass::key(),
                routeName: $routeName,
                pageClass: null,
                resourceClass: $resourceClass,
            );
        }

        if ($this->pageRegistry->has(DashboardPage::class)) {
            return new ResolvedScreen(
                kind: ScreenKind::Page,
                key: DashboardPage::key(),
                routeName: $routeName,
                pageClass: DashboardPage::class,
                resourceClass: null,
            );
        }

        throw new \InvalidArgumentException('Unable to resolve a Flashboard page or resource for the current route.');
    }
}
