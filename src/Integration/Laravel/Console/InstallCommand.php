<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Integration\Laravel\Console;

use Illuminate\Console\Attributes\Signature;
use Pepperfm\Flashboard\Core\Panel\DiscoveryTarget;
use Pepperfm\Flashboard\Integration\Laravel\Discovery\AutoDiscoveryScanner;
use Pepperfm\Flashboard\Integration\Laravel\FlashboardServiceProvider;

use Illuminate\Filesystem\Filesystem;

use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\table;
use function Laravel\Prompts\warning;

#[Signature('flashboard:install {--force : Overwrite publish targets when supported}')]
final class InstallCommand extends \Illuminate\Console\Command
{
    protected $description = 'Publish Flashboard panel assets and starter integration files.';

    public function handle(Filesystem $files, AutoDiscoveryScanner $autoDiscoveryScanner): int
    {
        $panelPath = $this->panelPath();
        $discoveryDirectory = app_path('Flashboard');

        info('Installing Flashboard...');

        $publishOptions = [
            '--provider' => FlashboardServiceProvider::class,
        ];
        if ($this->option('force')) {
            $publishOptions['--force'] = true;
        }

        $this->call('vendor:publish', $publishOptions + ['--tag' => 'flashboard-views']);
        $this->call('vendor:publish', $publishOptions + ['--tag' => 'flashboard-assets']);

        info('Flashboard install bootstrap completed.');
        note('Next steps');
        table(
            ['Step', 'Action'],
            [
                ['1', 'Configure Flashboard inline with Flashboard::configure()->path(...)->discover()'],
                ['2', 'Generate a resource or page with php artisan flashboard:make-resource / make-page'],
                ['3', sprintf('Ensure your auth middleware can protect %s', $panelPath)],
                ['4', sprintf('Visit %s to confirm the package wiring', $panelPath)],
            ],
        );

        if ($files->isDirectory($discoveryDirectory)) {
            $discovered = $autoDiscoveryScanner->scan([
                DiscoveryTarget::make($discoveryDirectory, 'App\\Flashboard'),
            ]);
            $resourceCount = count($discovered['resources']);
            $pageCount = count($discovered['pages']);

            if ($resourceCount > 0 || $pageCount > 0) {
                note("Auto-discovery detected {$resourceCount} resource(s) and {$pageCount} page(s) in {$discoveryDirectory}.");
            } else {
                note("Default discovery directory detected: {$discoveryDirectory}. No discoverable Flashboard classes found yet.");
            }
        }

        if ($this->option('force')) {
            warning('Install ran with --force, so published targets may have been overwritten.');
        }

        return self::SUCCESS;
    }

    private function panelPath(): string
    {
        $path = trim((string) config('flashboard.path', 'admin'), '/');

        if ($path === '') {
            return '/';
        }

        return '/' . $path;
    }
}
