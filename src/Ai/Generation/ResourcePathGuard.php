<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Ai\Generation;

use Illuminate\Filesystem\Filesystem;

final readonly class ResourcePathGuard
{
    public function __construct(private Filesystem $files = new Filesystem())
    {
    }

    public function assertSafe(string $path): void
    {
        if (
            !str_starts_with($path, DIRECTORY_SEPARATOR)
            || str_contains($path, "\0")
            || str_contains($path, '\\')
            || array_any(explode(DIRECTORY_SEPARATOR, $path), static fn(string $part): bool => in_array($part, ['.', '..'], true))
        ) {
            throw new \RuntimeException('Resource path must be absolute and contain no traversal segments.');
        }

        // macOS exposes the system temporary directory through /var.
        // Trust that platform-provided root, not symlinks inside it.
        $tempRoot = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR);
        $canonicalTemp = realpath($tempRoot);

        if ($canonicalTemp !== false && str_starts_with($path, $tempRoot.DIRECTORY_SEPARATOR)) {
            $path = $canonicalTemp . substr($path, strlen($tempRoot));
        }

        clearstatcache(true);
        $current = $path;

        do {
            if (is_link($current)) {
                throw new \RuntimeException('Symlinked resource directories and files are not supported.');
            }
            if ($current !== $path && $this->files->exists($current) && !$this->files->isDirectory($current)) {
                throw new \RuntimeException('A resource path ancestor is not a directory.');
            }

            $parent = $this->files->dirname($current);
            if ($parent === $current) {
                break;
            }

            $current = $parent;
        } while (true);
    }
}
