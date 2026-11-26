<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Tests\Feature;

use Illuminate\Filesystem\Filesystem;
use Pepperfm\Flashboard\Ai\Generation\ResourceFileStore;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

final class AiResourceFileStoreTest extends TestCase
{
    private string $directory;

    private ResourceFileStore $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir().'/flashboard-file-store-'.bin2hex(random_bytes(8));
        (new Filesystem())->ensureDirectoryExists($this->directory);
        $this->store = new ResourceFileStore(new Filesystem());
    }

    protected function tearDown(): void
    {
        (new Filesystem())->deleteDirectory($this->directory);
        parent::tearDown();
    }

    public function test_symlinked_ancestor_and_dangling_target_are_never_read_or_written(): void
    {
        $outside = $this->directory.'/outside';
        (new Filesystem())->ensureDirectoryExists($outside);
        $original = $outside.'/Probe.php';
        file_put_contents($original, 'original');
        symlink($outside, $this->directory.'/alias');
        symlink($outside.'/missing.php', $this->directory.'/dangling.php');

        foreach ([
            $this->directory.'/alias/deep/nested/Probe.php',
            $this->directory.'/alias/Probe.php',
            $this->directory.'/dangling.php',
            $this->directory.'/../Probe.php',
        ] as $path) {
            $this->assertRejected(fn() => $this->store->read($path));
            $this->assertRejected(fn() => $this->store->create($path, 'replacement'));
            $this->assertRejected(fn() => $this->store->update($path, 'replacement', hash('sha256', 'original'), static fn(): bool => true));
        }

        self::assertSame('original', file_get_contents($original));
        self::assertDirectoryDoesNotExist($outside.'/deep');
        self::assertFileDoesNotExist($outside.'/missing.php');
    }

    public function test_hardlinked_file_and_directory_cannot_be_used_as_resource(): void
    {
        $source = $this->directory.'/original.php';
        $alias = $this->directory.'/alias.php';
        file_put_contents($source, 'original');
        link($source, $alias);

        $this->assertRejected(fn() => $this->store->read($alias));
        $this->assertRejected(fn() => $this->store->create($this->directory, 'source'));
        self::assertSame('original', file_get_contents($source));
    }

    public function test_failed_update_does_not_create_a_directory(): void
    {
        $path = $this->directory.'/missing/Probe.php';
        $this->assertRejected(fn() => $this->store->update($path, 'replacement', hash('sha256', 'original'), static fn(): bool => true));

        self::assertDirectoryDoesNotExist(dirname($path));
    }

    public function test_atomic_create_and_update_leave_no_staging_files_and_preserve_mode(): void
    {
        $path = $this->directory.'/Probe.php';
        $this->store->create($path, 'original');
        chmod($path, 0640);
        $this->store->update($path, 'replacement', hash('sha256', 'original'), static fn(): bool => true);

        clearstatcache(true, $path);
        self::assertSame('replacement', $this->store->read($path));
        self::assertSame(0640, fileperms($path) & 0777);
        self::assertSame([], glob($this->directory.'/.flashboard-ai-*.tmp'));
    }

    public function test_concurrent_updates_cannot_both_use_the_same_sha(): void
    {
        $path = $this->directory.'/Probe.php';
        $this->store->create($path, 'original');
        $this->race($path, 'update');

        self::assertContains($this->store->read($path), ['first', 'second']);
        self::assertSame([], glob($this->directory.'/.flashboard-ai-*.tmp'));
    }

    public function test_concurrent_creates_cannot_overwrite_the_winner(): void
    {
        $path = $this->directory.'/Probe.php';
        $this->race($path, 'create');

        self::assertContains($this->store->read($path), ['first', 'second']);
        self::assertSame([], glob($this->directory.'/.flashboard-ai-*.tmp'));
    }

    private function assertRejected(callable $operation): void
    {
        try {
            $operation();
            self::fail('Unsafe resource operation was accepted.');
        } catch (\RuntimeException $exception) {
            self::assertNotSame('', $exception->getMessage());
        }
    }

    private function race(string $path, string $operation): void
    {
        $program = <<<'PHP'
            require $argv[1];
            $store = new \Pepperfm\Flashboard\Ai\Generation\ResourceFileStore(new \Illuminate\Filesystem\Filesystem());
            echo "ready\n";
            try {
                if ($argv[3] === 'create') {
                    $store->create($argv[2], $argv[4]);
                } else {
                    $store->update($argv[2], $argv[4], hash('sha256', 'original'), static fn(): bool => true);
                }
                echo "published\n";
            } catch (\RuntimeException $exception) {
                echo "rejected\n";
                exit(10);
            }
            PHP;

        $processes = array_map(static fn(string $source): Process => new Process([
            PHP_BINARY, '-r', $program, dirname(__DIR__, 2).'/vendor/autoload.php', $path, $operation, $source,
        ], timeout: 10), ['first', 'second']);

        $lock = fopen($this->directory, 'r');
        self::assertIsResource($lock);

        try {
            self::assertTrue(flock($lock, LOCK_EX));
            foreach ($processes as $process) {
                $process->start();
                self::assertTrue($process->waitUntil(static fn(string $type, string $output): bool => str_contains($output, 'ready')));
            }
            flock($lock, LOCK_UN);

            $codes = array_map(static fn(Process $process): int => $process->wait(), $processes);
            sort($codes);
            self::assertSame([0, 10], $codes);
            $winner = $processes[0]->isSuccessful() ? 'first' : 'second';
            self::assertSame($winner, $this->store->read($path));
        } finally {
            fclose($lock);
            foreach ($processes as $process) {
                $process->stop();
            }
        }
    }
}
