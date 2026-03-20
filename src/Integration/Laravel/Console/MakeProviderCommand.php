<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Integration\Laravel\Console;

use Illuminate\Console\Attributes\Signature;
use Illuminate\Filesystem\Filesystem;

use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

#[Signature('flashboard:make-provider {name? : Provider class name} {--force : Overwrite the target file if it exists}')]
final class MakeProviderCommand extends \Illuminate\Console\Command
{
    protected $description = 'Generate a host-side Flashboard panel provider.';

    public function handle(Filesystem $files): int
    {
        $className = $this->qualifyProviderClassName(
            (string) ($this->argument('name') ?: text(
                label: 'Provider class name',
                default: 'AdminPanelProvider',
                required: true,
            )),
        );
        $panelPath = (string) text(
            label: 'Panel path',
            default: 'panel',
            required: true,
        );
        $targetDirectory = app_path('Providers/Flashboard');
        $targetPath = $targetDirectory . '/' . $className . '.php';
        $providerClass = 'App\\Providers\\Flashboard\\' . $className;
        $providersPath = base_path('bootstrap/providers.php');

        if ($files->exists($targetPath) && !$this->option('force')) {
            warning("File already exists: {$targetPath}");

            return self::FAILURE;
        }

        $files->ensureDirectoryExists($targetDirectory);
        $files->put($targetPath, str_replace(
            ['{{ namespace }}', '{{ class }}', '{{ path }}'],
            ['App\\Providers\\Flashboard', $className, trim($panelPath, '/')],
            $files->get(dirname(__DIR__, 4) . '/stubs/panel-provider.stub'),
        ));

        info('Flashboard provider created: ' . $this->relativePath($targetPath));
        note($this->registerProviderInBootstrap($files, $providersPath, $providerClass));

        return self::SUCCESS;
    }

    private function qualifyProviderClassName(string $className): string
    {
        return str_ends_with($className, 'Provider') ? $className : $className . 'Provider';
    }

    private function registerProviderInBootstrap(Filesystem $files, string $providersPath, string $providerClass): string
    {
        if (!$files->exists($providersPath)) {
            return 'Provider file generated. Add it to your host provider list manually.';
        }

        $contents = $files->get($providersPath);

        if (str_contains($contents, $providerClass . '::class')) {
            return 'Provider file generated and already present in bootstrap/providers.php.';
        }

        $updatedContents = preg_replace(
            '/\];\s*$/',
            "    {$providerClass}::class," . PHP_EOL . '];' . PHP_EOL,
            $contents,
            1,
            $count,
        );

        if (!is_string($updatedContents) || $count !== 1) {
            return 'Provider file generated. Unable to update bootstrap/providers.php automatically.';
        }

        $files->put($providersPath, $updatedContents);

        return 'Provider file generated and registered in bootstrap/providers.php.';
    }

    private function relativePath(string $path): string
    {
        $basePath = rtrim(base_path(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        return str_starts_with($path, $basePath)
            ? substr($path, strlen($basePath))
            : $path;
    }
}
