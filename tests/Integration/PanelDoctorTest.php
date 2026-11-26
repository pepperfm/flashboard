<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Tests\Integration;

use Orchestra\Testbench\TestCase;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Gate;
use Pepperfm\Flashboard\Core\Registry\ResourceRegistry;
use Pepperfm\Flashboard\Integration\Laravel\Diagnostics\PanelDoctor;
use Pepperfm\Flashboard\Integration\Laravel\Diagnostics\Diagnostic;
use Pepperfm\Flashboard\Integration\Laravel\Diagnostics\DiagnosticSeverity;
use Pepperfm\Flashboard\Integration\Laravel\Diagnostics\ResourceDiagnosticChecks;
use Pepperfm\Flashboard\Integration\Laravel\FlashboardServiceProvider;
use Pepperfm\Flashboard\Tests\Fixtures\DoctorProbePolicy;
use Pepperfm\Flashboard\Tests\Fixtures\Models\BelongsToProduct;
use Pepperfm\Flashboard\Contracts\Resources\Resource;

final class PanelDoctorTest extends TestCase
{
    private string $publicDirectory;

    protected function getPackageProviders($app): array
    {
        return [FlashboardServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $this->publicDirectory = sys_get_temp_dir().'/flashboard-doctor-'.bin2hex(random_bytes(8));
        (new Filesystem())->ensureDirectoryExists($this->publicDirectory);
        $app->usePublicPath($this->publicDirectory);
    }

    protected function tearDown(): void
    {
        if (isset($this->publicDirectory)) {
            (new Filesystem())->deleteDirectory($this->publicDirectory);
        }
        parent::tearDown();
    }

    public function test_doctor_is_read_only_and_returns_machine_readable_diagnostics(): void
    {
        Gate::define('accessFlashboard', static function (): never {
            throw new \RuntimeException('Do not execute the access gate during inspection.');
        });

        $this->artisan('vendor:publish', ['--tag' => 'flashboard-assets', '--force' => true])->assertSuccessful();
        $kernel = $this->app->make(Kernel::class);
        $manifest = $this->publicDirectory.'/vendor/flashboard/build/manifest.json';
        $before = file_get_contents($manifest);

        $exitCode = $kernel->call('flashboard:doctor', ['--json' => true]);
        $raw = $kernel->output();
        self::assertSame(0, $exitCode, $raw);
        $output = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
        self::assertContains('published_assets', array_column($output, 'code'));
        self::assertContains('mcp', array_column($output, 'code'));
        self::assertNotContains('error', array_column($output, 'severity'));
        self::assertSame($before, file_get_contents($manifest));

        file_put_contents($manifest, $before.PHP_EOL);
        self::assertSame(1, $kernel->call('flashboard:doctor', ['--json' => true]));
        self::assertStringContainsString('published_assets_stale', $kernel->output());

        file_put_contents($manifest, '{}');
        self::assertSame(1, $kernel->call('flashboard:doctor', ['--json' => true]));
        self::assertSame('{}', file_get_contents($manifest));
    }

    public function test_doctor_reports_resource_conflicts_and_missing_policy(): void
    {
        $registry = $this->app->make(ResourceRegistry::class);
        $registry->register((new class extends Resource {
            public static function model(): string
            {
                return BelongsToProduct::class;
            }

            public static function key(): string
            {
                return 'doctor_probe';
            }

            public static function actionAbilityMap(): array
            {
                return ['approve' => 'approve'];
            }
        })::class);
        $registry->register((new class extends Resource {
            public static function model(): string
            {
                return BelongsToProduct::class;
            }

            public static function key(): string
            {
                return 'doctor_probe';
            }
        })::class);

        $diagnostics = $this->app->make(PanelDoctor::class)->inspect();
        $errors = array_filter($diagnostics, static fn(Diagnostic $item): bool => $item->severity === DiagnosticSeverity::Error);
        $codes = array_map(static fn(Diagnostic $item): string => $item->code, $errors);

        self::assertContains('resource_conflict', $codes);
        self::assertContains('resource_policy_missing', $codes);
        self::assertContains('published_assets', $codes);
    }
    public function test_resource_policy_registration_is_checked_without_executing_policy(): void
    {
        $resource = (new class extends Resource {
            public static function model(): string
            {
                return BelongsToProduct::class;
            }

            public static function policy(): string
            {
                return DoctorProbePolicy::class;
            }

            public static function actionAbilityMap(): array
            {
                return ['approve' => 'approve'];
            }
        })::class;

        $checks = $this->app->make(ResourceDiagnosticChecks::class);
        self::assertSame('resource_policy_unregistered', $checks->inspectResource($resource)[0]->code);

        Gate::policy(BelongsToProduct::class, DoctorProbePolicy::class);
        self::assertSame('resource_policy', $checks->inspectResource($resource)[0]->code);
    }

}
