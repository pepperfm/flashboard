<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Tests\Feature;

use Pepperfm\Flashboard\Flashboard;
use Pepperfm\Flashboard\Core\Pages\DashboardPage;
use Pepperfm\Flashboard\Core\Panel\PanelConfig;
use Pepperfm\Flashboard\Integration\Laravel\Discovery\AutoDiscoveryScanner;
use Pepperfm\Flashboard\Integration\Laravel\Discovery\ConfigPanelProvider;
use Pepperfm\Flashboard\Tests\TestCase;

final class ConfigPanelProviderDiscoveryTest extends TestCase
{
    protected function tearDown(): void
    {
        Flashboard::resetConfiguration();

        parent::tearDown();
    }

    public function test_provider_merges_explicit_and_discovered_classes_without_duplicates(): void
    {
        $fixtureDirectory = __DIR__ . '/../Fixtures/Flashboard';

        Flashboard::configure()
            ->resource('Pepperfm\\Flashboard\\Tests\\Fixtures\\Flashboard\\UsersResource')
            ->except('IgnoredResource')
            ->discover($fixtureDirectory, 'Pepperfm\\Flashboard\\Tests\\Fixtures\\Flashboard');

        $provider = new ConfigPanelProvider(
            PanelConfig::fromArray(Flashboard::resolvedConfig((array) config('flashboard'))),
            new AutoDiscoveryScanner($this->app->make('log')),
        );

        self::assertSame(
            ['Pepperfm\\Flashboard\\Tests\\Fixtures\\Flashboard\\UsersResource'],
            $provider->resources(),
        );
        self::assertSame(
            [
                DashboardPage::class,
                'Pepperfm\\Flashboard\\Tests\\Fixtures\\Flashboard\\ReviewQueuePage',
            ],
            $provider->pages(),
        );
    }
}
