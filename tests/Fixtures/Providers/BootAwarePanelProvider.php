<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Tests\Fixtures\Providers;

use Pepperfm\Flashboard\Integration\Laravel\FlashboardPanelProvider;

final class BootAwarePanelProvider extends FlashboardPanelProvider
{
    public static bool $booted = false;

    public function register(): void
    {
        $this->panelConfig()->path('panel');
    }

    public function boot(): void
    {
        self::$booted = true;
    }
}
