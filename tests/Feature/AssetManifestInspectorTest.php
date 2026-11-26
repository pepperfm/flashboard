<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Tests\Feature;

use PHPUnit\Framework\TestCase;
use Illuminate\Filesystem\Filesystem;
use Pepperfm\Flashboard\UI\Assets\AssetManifestInspector;

final class AssetManifestInspectorTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir().'/flashboard-manifest-'.bin2hex(random_bytes(8));
        new Filesystem()->ensureDirectoryExists($this->directory);
    }

    protected function tearDown(): void
    {
        new Filesystem()->deleteDirectory($this->directory);
        parent::tearDown();
    }

    public function test_it_detects_missing_and_invalid_manifest(): void
    {
        $inspector = new AssetManifestInspector();
        self::assertSame(['manifest_missing'], $inspector->inspect($this->directory));

        file_put_contents($this->directory.'/manifest.json', '{');
        self::assertSame(['manifest_invalid'], $inspector->inspect($this->directory));

        $this->manifest([]);
        self::assertSame(['entrypoint_missing'], $inspector->inspect($this->directory));
    }

    public function test_it_checks_asset_graph_and_distinguishes_stale_distribution_files(): void
    {
        file_put_contents($this->directory.'/entry.js', '');
        file_put_contents($this->directory.'/chunk.js', '');
        $this->manifest([
            'resources/js/flashboard.ts' => [
                'file' => 'entry.js',
                'imports' => ['chunk'],
            ],
            'chunk' => ['file' => 'chunk.js'],
        ]);

        $inspector = new AssetManifestInspector();
        self::assertSame([], $inspector->inspect($this->directory, checkUnreferenced: true));
        file_put_contents($this->directory.'/stale.js', '');
        self::assertSame([], $inspector->inspect($this->directory));
        self::assertSame(['asset_unreferenced'], $inspector->inspect($this->directory, checkUnreferenced: true));

        unlink($this->directory.'/chunk.js');
        self::assertSame(['asset_missing_or_unsafe'], $inspector->inspect($this->directory));
    }

    public function test_it_rejects_unsafe_paths_and_invalid_imports(): void
    {
        $this->manifest([
            'resources/js/flashboard.ts' => [
                'file' => '../outside.js',
                'imports' => ['unknown'],
            ],
        ]);

        self::assertSame(
            ['asset_missing_or_unsafe', 'import_missing'],
            (new AssetManifestInspector())->inspect($this->directory)
        );
    }

    /** @param array<string, mixed> $entries */
    private function manifest(array $entries): void
    {
        file_put_contents($this->directory.'/manifest.json', json_encode($entries, JSON_THROW_ON_ERROR));
    }
}
