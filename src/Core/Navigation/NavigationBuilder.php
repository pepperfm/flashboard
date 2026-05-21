<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Core\Navigation;

use Illuminate\Container\Attributes\Singleton;
use Pepperfm\Flashboard\Contracts\Panel\PanelDefinitionContract;
use Pepperfm\Flashboard\Core\Authorization\Visibility\ScreenAccessResolver;
use Pepperfm\Flashboard\Core\Navigation\Builders\NavigationItem;
use Pepperfm\Flashboard\Core\Registry\PageRegistry;
use Pepperfm\Flashboard\Core\Registry\ResourceRegistry;

#[Singleton]
final readonly class NavigationBuilder
{
    public function __construct(
        private PageRegistry $pageRegistry,
        private ResourceRegistry $resourceRegistry,
        private ScreenAccessResolver $screenAccessResolver,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function build(PanelDefinitionContract $panel): array
    {
        $user = auth($panel->guard())->user();
        $items = [];

        foreach ($this->pageRegistry->all() as $pageClass) {
            if (!$this->screenAccessResolver->canViewPageInNavigation($pageClass, $user)) {
                continue;
            }

            $item = NavigationItem::make($pageClass::key())
                ->label((string) ($pageClass::navigationLabel() ?? $pageClass::title()))
                ->group($pageClass::navigationGroup());

            $payload = $item->toArray();
            $payload['href'] = route(
                config('flashboard.route_name_prefix', 'flashboard.') . ($pageClass::key() === 'dashboard' ? 'home' : 'pages.' . $pageClass::key()),
            );

            $items[] = $payload;
        }

        foreach ($this->resourceRegistry->all() as $resourceClass) {
            if (!$this->screenAccessResolver->canViewResourceInNavigation($resourceClass, $user)) {
                continue;
            }

            $payload = $resourceClass::navigationItem(
                NavigationItem::make($resourceClass::key()),
            )->toArray();
            $payload['href'] = route(
                config('flashboard.route_name_prefix', 'flashboard.') . 'resources.' . $resourceClass::key() . '.index',
            );

            $items[] = $payload;
        }

        return $items;
    }
}
