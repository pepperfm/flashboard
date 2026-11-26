<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Tests\Integration;

use Illuminate\Filesystem\Filesystem;
use Orchestra\Testbench\TestCase;
use Pepperfm\Flashboard\Integration\Laravel\FlashboardServiceProvider;

final class AssetPublishHttpTest extends TestCase
{
    private string $publicDirectory;

    protected function getPackageProviders($app): array
    {
        return [FlashboardServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $this->publicDirectory = sys_get_temp_dir().'/flashboard-assets-'.bin2hex(random_bytes(8));
        (new Filesystem)->ensureDirectoryExists($this->publicDirectory);
        $app->usePublicPath($this->publicDirectory);
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('session.driver', 'array');
    }

    protected function tearDown(): void
    {
        if (isset($this->publicDirectory)) {
            (new Filesystem)->deleteDirectory($this->publicDirectory);
        }
        parent::tearDown();
    }

    public function test_publishing_refreshes_manifest_and_all_referenced_assets(): void
    {
        $target = $this->publicDirectory.'/vendor/flashboard/build';
        $this->artisan('vendor:publish', ['--tag' => 'flashboard-assets', '--force' => true])->assertSuccessful();
        $manifest = $target.'/manifest.json';
        self::assertFileExists($manifest);
        $contents = file_get_contents($manifest);
        self::assertIsString($contents);
        $entries = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        self::assertNotEmpty($entries);
        foreach ($entries as $entry) {
            foreach (array_merge([$entry['file']], $entry['css'] ?? [], $entry['assets'] ?? []) as $file) {
                self::assertFileExists($target.'/'.$file);
            }
            foreach (array_merge($entry['imports'] ?? [], $entry['dynamicImports'] ?? []) as $import) {
                self::assertArrayHasKey($import, $entries);
            }
        }

        file_put_contents($manifest, '{}');
        $this->artisan('vendor:publish', ['--tag' => 'flashboard-assets', '--force' => true])->assertSuccessful();
        self::assertSame($contents, file_get_contents($manifest));
        $this->get('/admin/login')->assertOk()->assertSee('vendor/flashboard/build/', false);
    }
}
