<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Tests\Feature;

use Illuminate\Config\Repository;
use Pepperfm\Flashboard\Integration\Laravel\Discovery\PanelConfigurationResolver;
use Pepperfm\Flashboard\Integration\Laravel\FlashboardServiceProvider;
use Pepperfm\Flashboard\Integration\Laravel\FlashboardPanelProvider;
use Pepperfm\Flashboard\Tests\Fixtures\Providers\AdminPanelProvider;
use Pepperfm\Flashboard\Tests\Fixtures\Providers\BootAwarePanelProvider;
use Pepperfm\Flashboard\Tests\TestCase;

final class PanelConfigurationResolverTest extends TestCase
{
    public function test_provider_configuration_overrides_fallback_config_and_feeds_discovery(): void
    {
        $app = new \Illuminate\Foundation\Application(__DIR__ . '/../../..');
        $app->instance('config', new Repository([
            'flashboard' => [
                'path' => 'admin',
                'route_name_prefix' => 'flashboard.',
            ],
        ]));
        $app->instance('log', new \Psr\Log\NullLogger());

        $app->register(FlashboardServiceProvider::class);
        $app->register(AdminPanelProvider::class);

        $resolver = new PanelConfigurationResolver($app);
        $resolved = $resolver->resolve();
        $providers = $resolver->providers();

        self::assertCount(1, $providers);

        /** @var FlashboardPanelProvider $provider */
        $provider = $providers[0];

        self::assertSame('panel', $resolved['path']);
        self::assertSame('panel.', $resolved['route_name_prefix']);
        self::assertSame(
            ['Pepperfm\\Flashboard\\Tests\\Fixtures\\Flashboard\\UsersResource'],
            $provider->resources(),
        );
        self::assertSame(
            [
                \Pepperfm\Flashboard\Core\Pages\DashboardPage::class,
                'Pepperfm\\Flashboard\\Tests\\Fixtures\\Flashboard\\ReviewQueuePage',
            ],
            $provider->pages(),
        );
    }

    public function test_host_panel_provider_boot_method_is_executed_by_laravel(): void
    {
        BootAwarePanelProvider::$booted = false;

        $provider = new BootAwarePanelProvider(new \Illuminate\Foundation\Application(__DIR__ . '/../../..'));
        $provider->boot();

        self::assertTrue(BootAwarePanelProvider::$booted);
    }
}
