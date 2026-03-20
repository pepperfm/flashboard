<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Tests\Fixtures\Providers;

use Pepperfm\Flashboard\Integration\Laravel\FlashboardPanelProvider;

final class AdminPanelProvider extends FlashboardPanelProvider
{
    public function register(): void
    {
        $this->panelConfig()
            ->path('panel')
            ->routeNamePrefix('panel')
            ->except('IgnoredResource')
            ->discover(dirname(__DIR__) . '/Flashboard', 'Pepperfm\\Flashboard\\Tests\\Fixtures\\Flashboard');
    }
}
