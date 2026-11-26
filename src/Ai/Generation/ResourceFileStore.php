<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Ai\Generation;

use Illuminate\Filesystem\Filesystem;

final readonly class ResourceFileStore
{
    private ResourcePathGuard $guard;

    public function __construct(
        private Filesystem $files,
        ?ResourcePathGuard $guard = null,
    ) {
        $this->guard = $guard ?? new ResourcePathGuard($files);
    }

    public function read(string $path): ?string
    {
        $this->guard->assertSafe($path);
        if (!$this->files->exists($path)) {
            return null;
        }

        $stat = lstat($path);
        if ($stat === false || $stat['nlink'] !== 1 || !$this->files->isFile($path)) {
            throw new \RuntimeException('Resource must be a regular, non-linked file.');
        }

        return $this->files->get($path);
    }

    public function create(string $path, string $source): void
    {
        $this->locked($path, function () use ($path, $source): void {
            if ($this->read($path) !== null) {
                throw new \RuntimeException('Resource already exists; preview an update instead.');
            }

            $this->publish($path, $source, null);
        }, createDirectory: true);
    }

    public function update(string $path, string $source, string $expectedSha, callable $isManaged): void
    {
        $this->locked($path, function () use ($path, $source, $expectedSha, $isManaged): void {
            $current = $this->read($path);

            if (
                $current === null
                || !hash_equals(hash('sha256', $current), $expectedSha)
                || !$isManaged($current)
            ) {
                throw new \RuntimeException('Resource changed since preview or contains manual edits. Refusing to overwrite it.');
            }
            if ($current !== $source) {
                $this->publish($path, $source, $current);
            }
        });
    }

    private function locked(string $path, callable $operation, bool $createDirectory = false): void
    {
        $this->guard->assertSafe($path);
        $directory = $this->files->dirname($path);
        if ($createDirectory) {
            $this->files->ensureDirectoryExists($directory);
        } elseif (!$this->files->isDirectory($directory)) {
            throw new \RuntimeException('Resource directory does not exist.');
        }
        $this->guard->assertSafe($path);

        // A directory lock survives atomic replacement of individual resources.
        // No lock artifact is created in the host's source tree.
        $handle = @fopen($directory, 'rb');
        if ($handle === false) {
            throw new \RuntimeException('Unable to open resource directory for locking.');
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new \RuntimeException('Unable to lock resource directory.');
            }

            $this->guard->assertSafe($path);
            $opened = fstat($handle);
            $current = stat($directory);

            if (
                $opened === false
                || $current === false
                || $opened['ino'] !== $current['ino']
                || $opened['dev'] !== $current['dev']
            ) {
                throw new \RuntimeException('Resource directory changed while acquiring the lock.');
            }

            $operation();
        } finally {
            fclose($handle);
        }
    }

    private function publish(string $path, string $source, ?string $previous): void
    {
        $temporary = $this->files->dirname($path) . '/.flashboard-ai-' . bin2hex(random_bytes(16)) . '.tmp';
        $handle = @fopen($temporary, 'xb');
        if ($handle === false) {
            throw new \RuntimeException('Unable to stage resource source.');
        }

        try {
            $mode = $previous === null ? (0666 & ~umask()) : fileperms($path);
            if ($mode === false || !chmod($temporary, $mode & 0777)) {
                throw new \RuntimeException('Unable to set resource permissions.');
            }

            if (fwrite($handle, $source) !== strlen($source) || !fflush($handle)) {
                throw new \RuntimeException('Unable to write complete resource source.');
            }

            fclose($handle);

            // Recheck manual changes immediately before publishing.
            if ($this->read($path) !== $previous) {
                throw new \RuntimeException('Resource changed during publication.');
            }

            // Native link() creates a hardlink; Filesystem::link() creates a symlink.
            $published = $previous === null
                ? @link($temporary, $path)
                : @$this->files->move($temporary, $path);
            if (!$published) {
                throw new \RuntimeException('Unable to publish resource atomically.');
            }
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
            if ($this->files->isFile($temporary) && !$this->files->delete($temporary)) {
                throw new \RuntimeException('Unable to remove staged resource source.');
            }
        }
    }
}
