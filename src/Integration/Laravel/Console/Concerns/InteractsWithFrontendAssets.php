<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Integration\Laravel\Console\Concerns;

use Illuminate\Filesystem\Filesystem;
use Pepperfm\Flashboard\Integration\Laravel\FlashboardServiceProvider;
use Symfony\Component\Process\Process;

use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\select;

trait InteractsWithFrontendAssets
{
    private function requestedPackageManager(Filesystem $files): string
    {
        $explicitPackageManager = $this->explicitPackageManagerOption();

        if ($explicitPackageManager !== null) {
            return $explicitPackageManager;
        }

        $default = $this->defaultPackageManager($files, $this->packageBasePath());

        return (string) select(
            label: 'Package manager for frontend assets',
            options: [
                'bun' => 'bun',
                'npm' => 'npm',
                'pnpm' => 'pnpm',
                'yarn' => 'yarn',
                'skip' => 'skip frontend install/build',
            ],
            default: $default,
        );
    }

    private function explicitPackageManagerOption(): ?string
    {
        $selected = array_values(array_filter([
            $this->option('bun') ? 'bun' : null,
            $this->option('npm') ? 'npm' : null,
            $this->option('pnpm') ? 'pnpm' : null,
            $this->option('yarn') ? 'yarn' : null,
            $this->option('skip') ? 'skip' : null,
        ]));

        if (count($selected) > 1) {
            throw new \InvalidArgumentException('Choose only one package manager option: --bun, --npm, --pnpm, --yarn, or --skip.');
        }

        return $selected[0] ?? null;
    }

    private function defaultPackageManager(Filesystem $files, ?string $directory = null): string
    {
        $directory = $directory ?? $this->packageBasePath();

        if ($files->exists($directory . '/bun.lock') || $files->exists($directory . '/bun.lockb')) {
            return 'bun';
        }
        if ($files->exists($directory . '/pnpm-lock.yaml')) {
            return 'pnpm';
        }
        if ($files->exists($directory . '/yarn.lock')) {
            return 'yarn';
        }

        return 'npm';
    }

    protected function runFrontendSetup(string $packageManager, Filesystem $files): bool
    {
        if ($packageManager === 'skip') {
            note('Skipped frontend dependency install and asset build.');

            return false;
        }

        $installCommand = $this->frontendInstallCommand($packageManager);
        $buildCommand = $this->frontendBuildCommand($packageManager);
        $workingDirectory = $this->packageBasePath();

        info(sprintf('Installing frontend dependencies with %s...', $packageManager));
        $this->runProcess($installCommand, $workingDirectory);

        info(sprintf('Building frontend assets with %s...', $packageManager));
        $this->runProcess($buildCommand, $workingDirectory);

        return $files->isDirectory("$workingDirectory/public/build");
    }

    protected function publishFrontendArtifacts(bool $force = false): void
    {
        $publishOptions = [
            '--provider' => FlashboardServiceProvider::class,
        ];

        if ($force) {
            $publishOptions['--force'] = true;
        }

        $this->call('vendor:publish', $publishOptions + ['--tag' => 'flashboard-views']);
        $this->call('vendor:publish', $publishOptions + ['--tag' => 'flashboard-assets']);
    }

    /**
     * @param list<string> $command
     */
    protected function runProcess(array $command, string $workingDirectory): void
    {
        $process = new Process($command, $workingDirectory);
        $process->setTimeout(null);
        $process->run(function (string $type, string $output): void {
            $this->output->write($output);
        });

        if (!$process->isSuccessful()) {
            throw new \RuntimeException(sprintf(
                'Command failed: %s',
                implode(' ', $command),
            ));
        }
    }

    /**
     * @return list<string>
     */
    private function frontendInstallCommand(string $packageManager): array
    {
        return match ($packageManager) {
            'bun' => ['bun', 'install'],
            'pnpm' => ['pnpm', 'install'],
            'yarn' => ['yarn', 'install'],
            default => ['npm', 'install'],
        };
    }

    /**
     * @return list<string>
     */
    private function frontendBuildCommand(string $packageManager): array
    {
        return match ($packageManager) {
            'bun' => ['bun', 'run', 'build'],
            'pnpm' => ['pnpm', 'run', 'build'],
            'yarn' => ['yarn', 'run', 'build'],
            default => ['npm', 'run', 'build'],
        };
    }

    private function packageBasePath(): string
    {
        return dirname(__DIR__, 5);
    }
}
