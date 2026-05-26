<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Tests\Feature;

use Pepperfm\Flashboard\Core\Panel\DiscoveryScope;
use Pepperfm\Flashboard\Core\Panel\DiscoveryTarget;
use Pepperfm\Flashboard\Integration\Laravel\Discovery\AutoDiscoveryScanner;
use Pepperfm\Flashboard\Tests\TestCase;

final class AutoDiscoveryScannerTest extends TestCase
{
    public function test_scanner_discovers_resources_and_pages_and_honors_exclusions(): void
    {
        $scanner = new AutoDiscoveryScanner($this->app->make('log'));
        $fixtureDirectory = __DIR__ . '/../Fixtures/Flashboard';

        $result = $scanner->scan([
            DiscoveryTarget::make($fixtureDirectory, 'Pepperfm\\Flashboard\\Tests\\Fixtures\\Flashboard', DiscoveryScope::Both),
        ], [
            'IconNavigationResource',
            'Pepperfm\\Flashboard\\Tests\\Fixtures\\Flashboard\\Support\\IgnoredResource',
        ]);

        self::assertSame(
            ['Pepperfm\\Flashboard\\Tests\\Fixtures\\Flashboard\\UsersResource'],
            $result['resources'],
        );
        self::assertSame(
            ['Pepperfm\\Flashboard\\Tests\\Fixtures\\Flashboard\\ReviewQueuePage'],
            $result['pages'],
        );
    }
}
