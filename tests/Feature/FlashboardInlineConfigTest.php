<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Tests\Feature;

use Pepperfm\Flashboard\Flashboard;
use Pepperfm\Flashboard\Tests\TestCase;

final class FlashboardInlineConfigTest extends TestCase
{
    protected function tearDown(): void
    {
        Flashboard::resetConfiguration();

        parent::tearDown();
    }

    public function test_inline_configuration_overrides_panel_settings_and_appends_discovery(): void
    {
        Flashboard::configure()
            ->path('panel')
            ->routeNamePrefix('panel')
            ->resource('App\\Flashboard\\UsersResource')
            ->page('App\\Flashboard\\ReviewQueuePage')
            ->reportBoot();

        $resolved = Flashboard::resolvedConfig([
            'path' => 'admin',
            'route_name_prefix' => 'flashboard.',
            'discovery' => [
                'providers' => [],
                'resources' => ['App\\Flashboard\\OrdersResource'],
                'pages' => [],
            ],
            'logging' => [
                'report_boot' => false,
            ],
        ]);

        self::assertSame('panel', $resolved['path']);
        self::assertSame('panel', $resolved['route_name_prefix']);
        self::assertSame(
            ['App\\Flashboard\\OrdersResource', 'App\\Flashboard\\UsersResource'],
            $resolved['discovery']['resources'],
        );
        self::assertSame(
            ['App\\Flashboard\\ReviewQueuePage'],
            $resolved['discovery']['pages'],
        );
        self::assertTrue($resolved['logging']['report_boot']);
    }
}
