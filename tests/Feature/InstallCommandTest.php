<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Tests\Feature;

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

    private function makeCommand(): InstallCommand
    {
        $reflection = new \ReflectionClass(InstallCommand::class);

        return $reflection->newInstanceWithoutConstructor();
    }
}
