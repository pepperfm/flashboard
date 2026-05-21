<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Integration\Laravel\Console;

use Illuminate\Console\Attributes\Signature;
use Pepperfm\Flashboard\Core\Panel\DiscoveryTarget;
use Pepperfm\Flashboard\Integration\Laravel\Console\Concerns\InteractsWithFrontendAssets;
use Pepperfm\Flashboard\Integration\Laravel\Discovery\AutoDiscoveryScanner;
use Pepperfm\Flashboard\Integration\Laravel\FlashboardServiceProvider;

use Illuminate\Filesystem\Filesystem;

use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\table;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

#[Signature(
    'flashboard:install
    {--force : Overwrite publish targets when supported}
    {--bun : Use bun for install and build}
    {--npm : Use npm for install and build}
    {--pnpm : Use pnpm for install and build}
    {--yarn : Use yarn for install and build}
    {--skip : Skip frontend install and build}'
)]
final class InstallCommand extends \Illuminate\Console\Command
{
    use InteractsWithFrontendAssets;

    protected $description = 'Publish Flashboard panel assets and starter integration files.';

    protected $aliases = ['fb:i'];

    public function handle(Filesystem $files, AutoDiscoveryScanner $autoDiscoveryScanner): int
    {
        $panelPath = $this->requestedPanelPath();
        $packageManager = $this->requestedPackageManager($files);
        $discoveryDirectory = app_path('Flashboard');
        $packageBuildPath = $this->packageBasePath() . '/public/build';
        $providerPath = $this->ensurePanelProvider($files, $panelPath);

        info('Installing Flashboard...');

        $publishOptions = [
            '--provider' => FlashboardServiceProvider::class,
        ];
        if ($this->option('force')) {
            $publishOptions['--force'] = true;
        }

        $this->call('vendor:publish', $publishOptions + ['--tag' => 'flashboard-views']);
        $assetsReady = $this->runFrontendSetup($packageManager, $files);

        if ($assetsReady && $files->isDirectory($packageBuildPath)) {
            $this->call('vendor:publish', $publishOptions + ['--tag' => 'flashboard-assets']);
        } else {
            warning('Skipped publishing flashboard-assets because no package build artifacts were generated.');
        }

        info('Flashboard install bootstrap completed.');
        note('Next steps');
        table(
            ['Step', 'Action'],
            [
                ['1', sprintf('Review %s', $providerPath)],
                ['2', sprintf('Ensure your auth middleware can protect %s', $this->panelPath($panelPath))],
                ['3', 'Generate a resource or page with php artisan fb:mr / fb:mp'],
                ['4', sprintf('Visit %s to confirm the package wiring', $this->panelPath($panelPath))],
            ],
        );

        if ($files->isDirectory($discoveryDirectory)) {
            $discovered = $autoDiscoveryScanner->scan([
                DiscoveryTarget::make($discoveryDirectory, 'App\\Flashboard'),
            ]);
            $resourceCount = count($discovered['resources']);
            $pageCount = count($discovered['pages']);

            if ($resourceCount > 0 || $pageCount > 0) {
                note("Auto-discovery detected $resourceCount resource(s) and $pageCount page(s) in $discoveryDirectory.");
            } else {
                note("Default discovery directory detected: $discoveryDirectory. No discoverable Flashboard classes found yet.");
            }
        }

        if ($this->option('force')) {
            warning('Install ran with --force, so published targets may have been overwritten.');
        }

        return self::SUCCESS;
    }

    private function panelPath(string $panelPath): string
    {
        $path = trim($panelPath, '/');
        if ($path === '') {
            return '/';
        }

        return '/' . $path;
    }

    private function requestedPanelPath(): string
    {
        $path = trim(text(
            label: 'Panel path',
            default: 'panel',
            required: true,
        ), '/');

        return $path === '' ? 'panel' : $path;
    }

    private function ensurePanelProvider(Filesystem $files, string $panelPath): string
    {
        $className = $this->providerClassNameForPanelPath($panelPath);
        $providerClass = 'App\\Providers\\Flashboard\\' . $className;
        $targetDirectory = app_path('Providers/Flashboard');
        $targetPath = "$targetDirectory/$className.php";
        $providersPath = base_path('bootstrap/providers.php');

        if (!$files->exists($targetPath)) {
            $files->ensureDirectoryExists($targetDirectory);
            $files->put($targetPath, str_replace(
                ['{{ namespace }}', '{{ class }}', '{{ path }}'],
                ['App\\Providers\\Flashboard', $className, trim($panelPath, '/')],
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

    private function providerClassNameForPanelPath(string $panelPath): string
    {
        $normalizedPath = trim($panelPath, '/');

        if ($normalizedPath === '') {
            return 'PanelPanelProvider';
        }

        $segments = preg_split('/[\/_-]+/', $normalizedPath) ?: [];
        $studly = collect($segments)
            ->filter(static fn (string $segment): bool => $segment !== '')
            ->map(static fn (string $segment): string => str($segment)->studly()->toString())
            ->implode('');

        return ($studly === '' ? 'Panel' : $studly) . 'PanelProvider';
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
            "    $providerClass::class," . PHP_EOL . '];' . PHP_EOL,
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
