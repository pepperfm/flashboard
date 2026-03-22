<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Tests\Feature;

use Illuminate\Filesystem\Filesystem;
use Pepperfm\Flashboard\Integration\Laravel\Console\BuildAssetsCommand;
use Pepperfm\Flashboard\Integration\Laravel\Console\InstallCommand;
use Pepperfm\Flashboard\Tests\TestCase;

final class InstallCommandTest extends TestCase
{
    public function test_provider_class_name_is_derived_from_panel_path(): void
    {
        $command = $this->makeCommand();
        $method = (new \ReflectionClass(InstallCommand::class))
            ->getMethod('providerClassNameForPanelPath');
        $method->setAccessible(true);

        self::assertSame('PanelPanelProvider', $method->invoke($command, 'panel'));
        self::assertSame('AdminPanelProvider', $method->invoke($command, 'admin'));
        self::assertSame('PartnerBalancePanelProvider', $method->invoke($command, 'partner-balance'));
        self::assertSame('InternalOpsPanelProvider', $method->invoke($command, 'internal/ops'));
    }

    public function test_panel_path_helper_returns_root_slash_for_empty_path(): void
    {
        $command = $this->makeCommand();
        $method = (new \ReflectionClass(InstallCommand::class))
            ->getMethod('panelPath');
        $method->setAccessible(true);

        self::assertSame('/', $method->invoke($command, ''));
        self::assertSame('/panel', $method->invoke($command, 'panel'));
    }

    public function test_default_package_manager_prefers_existing_lock_files(): void
    {
        $command = $this->makeCommand();
        $method = (new \ReflectionClass(InstallCommand::class))
            ->getMethod('defaultPackageManager');
        $method->setAccessible(true);

        $files = new Filesystem();
        $testDirectory = sys_get_temp_dir() . '/flashboard-install-' . bin2hex(random_bytes(8));
        $packageLockPath = $testDirectory . '/package-lock.json';
        $bunLockPath = $testDirectory . '/bun.lock';

        try {
            $files->ensureDirectoryExists($testDirectory);

            $files->put($packageLockPath, '{}');

            self::assertSame('npm', $method->invoke($command, $files, $testDirectory));

            $files->put($bunLockPath, '');

            self::assertSame('bun', $method->invoke($command, $files, $testDirectory));
        } finally {
            if ($files->isDirectory($testDirectory)) {
                $files->deleteDirectory($testDirectory);
            }
        }
    }

    public function test_frontend_install_command_uses_selected_package_manager(): void
    {
        $command = $this->makeBuildAssetsCommand();
        $method = (new \ReflectionClass(BuildAssetsCommand::class))
            ->getMethod('frontendInstallCommand');
        $method->setAccessible(true);

        self::assertSame(['npm', 'install'], $method->invoke($command, 'npm'));
        self::assertSame(['pnpm', 'install'], $method->invoke($command, 'pnpm'));
        self::assertSame(['yarn', 'install'], $method->invoke($command, 'yarn'));
        self::assertSame(['bun', 'install'], $method->invoke($command, 'bun'));
    }

    public function test_frontend_build_command_uses_selected_package_manager(): void
    {
        $command = $this->makeBuildAssetsCommand();
        $method = (new \ReflectionClass(BuildAssetsCommand::class))
            ->getMethod('frontendBuildCommand');
        $method->setAccessible(true);

        self::assertSame(['npm', 'run', 'build'], $method->invoke($command, 'npm'));
        self::assertSame(['pnpm', 'run', 'build'], $method->invoke($command, 'pnpm'));
        self::assertSame(['yarn', 'run', 'build'], $method->invoke($command, 'yarn'));
        self::assertSame(['bun', 'run', 'build'], $method->invoke($command, 'bun'));
    }

    private function makeCommand(): InstallCommand
    {
        $reflection = new \ReflectionClass(InstallCommand::class);

        return $reflection->newInstanceWithoutConstructor();
    }

    private function makeBuildAssetsCommand(): BuildAssetsCommand
    {
        $reflection = new \ReflectionClass(BuildAssetsCommand::class);

        return $reflection->newInstanceWithoutConstructor();
    }
}
