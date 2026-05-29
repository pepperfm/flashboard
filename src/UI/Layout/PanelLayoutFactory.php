<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\UI\Layout;

use Illuminate\Container\Attributes\Singleton;
use Pepperfm\Flashboard\Core\Runtime\Context\RuntimeRequestContext;
use Pepperfm\Flashboard\UI\Notifications\NotificationCenter;
use Pepperfm\Flashboard\UI\Overlays\OverlayFactory;
use Pepperfm\Flashboard\UI\States\ScreenStateFactory;
use Pepperfm\Flashboard\UI\Theme\ThemePreset;

#[Singleton]
final readonly class PanelLayoutFactory
{
    public function __construct(
        private NotificationCenter $notificationCenter,
        private OverlayFactory $overlayFactory,
        private ScreenStateFactory $screenStateFactory,
    ) {
    }

    /**
     * @param list<array<string, mixed>> $navigation
     */
    public function make(
        RuntimeRequestContext $context,
        array $navigation,
        array $payload,
        ?\Illuminate\Contracts\Auth\Authenticatable $user,
    ): PanelLayout {
        $title = $this->titleFromContext($context);

        return new PanelLayout(
            title: $title,
            navigation: $navigation,
            headerActions: [],
            userMenu: $this->userMenu($user),
            notifications: $this->notificationCenter->current(),
            overlays: $this->overlayFactory->make($payload),
            state: $this->screenStateFactory->make($payload),
            theme: ThemePreset::default()->toArray(),
        );
    }

    private function titleFromContext(RuntimeRequestContext $context): string
    {
        $screen = $context->screen();
        if ($screen->pageClass() !== null) {
            return $screen->pageClass()::title();
        }
        if ($screen->resourceClass() !== null) {
            return $screen->resourceClass()::name();
        }

        return $context->panel()->name();
    }

    /**
     * @return list<array<string, string>>
     */
    private function userMenu(?\Illuminate\Contracts\Auth\Authenticatable $user): array
    {
        if ($user === null) {
            return [];
        }

        return [
            [
                'label' => 'Signed in',
                'href' => '#',
            ],
            [
                'label' => 'Logout',
                'href' => route((string) config('flashboard.route_name_prefix', 'flashboard.') . 'auth.logout'),
            ],
        ];
    }
}
