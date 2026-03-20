<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\UI\Layout;

final readonly class PanelLayout
{
    /**
     * @param list<array<string, mixed>> $navigation
     * @param list<array<string, string>> $breadcrumbs
     * @param list<array<string, string>> $headerActions
     * @param list<array<string, string>> $userMenu
     * @param list<array<string, string>> $notifications
     * @param list<array<string, mixed>> $overlays
     */
    public function __construct(
        private string $title,
        private array $navigation,
        private array $breadcrumbs,
        private array $headerActions,
        private array $userMenu,
        private array $notifications,
        private array $overlays,
        private array $state,
        private array $theme,
    ) {
    }

    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'navigation' => $this->navigation,
            'breadcrumbs' => $this->breadcrumbs,
            'header_actions' => $this->headerActions,
            'user_menu' => $this->userMenu,
            'notifications' => $this->notifications,
            'overlays' => $this->overlays,
            'state' => $this->state,
            'theme' => $this->theme,
        ];
    }
}
