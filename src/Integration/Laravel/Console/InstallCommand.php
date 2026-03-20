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
        $providerPath = $this->ensurePanelProvider($files);

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
                ['1', sprintf('Review %s', $providerPath)],
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

    private function ensurePanelProvider(Filesystem $files): string
    {
        $className = 'AdminPanelProvider';
        $providerClass = 'App\\Providers\\Flashboard\\' . $className;
        $targetDirectory = app_path('Providers/Flashboard');
        $targetPath = $targetDirectory . '/' . $className . '.php';
        $providersPath = base_path('bootstrap/providers.php');

        if (!$files->exists($targetPath)) {
            $files->ensureDirectoryExists($targetDirectory);
            $files->put($targetPath, str_replace(
                ['{{ namespace }}', '{{ class }}', '{{ path }}'],
                ['App\\Providers\\Flashboard', $className, trim($this->panelPath(), '/')],
                $files->get(dirname(__DIR__, 4) . '/stubs/panel-provider.stub'),
            ));

            info('Flashboard provider created: ' . $this->relativePath($targetPath));
        } else {
            note('Flashboard provider already exists: ' . $this->relativePath($targetPath));
        }

        $registrationMessage = $this->registerProviderInBootstrap($files, $providersPath, $providerClass);
        note($registrationMessage);

        return $this->relativePath($targetPath);
    }

    private function registerProviderInBootstrap(Filesystem $files, string $providersPath, string $providerClass): string
    {
        if (!$files->exists($providersPath)) {
            return 'Unable to locate bootstrap/providers.php. Register the panel provider manually.';
        }

        $contents = $files->get($providersPath);

        if (str_contains($contents, $providerClass . '::class')) {
            return 'Panel provider is already registered in bootstrap/providers.php.';
        }

        $updatedContents = preg_replace(
            '/\];\s*$/',
            "    {$providerClass}::class," . PHP_EOL . '];' . PHP_EOL,
            $contents,
            1,
            $count,
        );

        if (!is_string($updatedContents) || $count !== 1) {
            return 'Unable to update bootstrap/providers.php automatically. Register the panel provider manually.';
        }

        $files->put($providersPath, $updatedContents);

        return 'Panel provider registered in bootstrap/providers.php.';
    }

    private function relativePath(string $path): string
    {
        $basePath = rtrim(base_path(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        return str_starts_with($path, $basePath)
            ? substr($path, strlen($basePath))
            : $path;
    }
}
