<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Tests\Feature;

use Illuminate\Config\Repository;
use Pepperfm\Flashboard\Contracts\Panel\PanelDefinitionContract;
use Pepperfm\Flashboard\Flashboard;
use Pepperfm\Flashboard\Integration\Laravel\FlashboardServiceProvider;
use Pepperfm\Flashboard\Tests\TestCase;

final class FlashboardServiceProviderConfigTest extends TestCase
{
    protected function tearDown(): void
    {
        Flashboard::resetConfiguration();

        parent::tearDown();
    }

    public function test_inline_configuration_applied_after_provider_register_still_affects_panel_definition(): void
    {
        $app = new \Illuminate\Foundation\Application(__DIR__ . '/../../..');
        $app->instance('config', new Repository([
            'flashboard' => [
                'path' => 'admin',
                'route_name_prefix' => 'flashboard.',
                'middleware' => [
                    'web' => ['web'],
                    'auth' => ['flashboard.auth'],
                ],
                'auth' => [
                    'login_path' => 'login',
                    'logout_path' => 'logout',
                ],
            ],
        ]));

        $provider = new FlashboardServiceProvider($app);
        $provider->register();

        Flashboard::configure()
            ->path('panel')
            ->routeNamePrefix('panel');

        /** @var PanelDefinitionContract $panel */
        $panel = $app->make(PanelDefinitionContract::class);

        self::assertSame('panel', $panel->path());
        self::assertSame('panel.', $panel->routeNamePrefix());
    }
}
