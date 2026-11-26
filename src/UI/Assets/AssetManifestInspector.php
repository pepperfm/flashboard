<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\UI\Assets;

final class AssetManifestInspector
{
    private const string ENTRYPOINT = 'resources/js/flashboard.ts';

    /**
     * @return list<string> Stable diagnostic codes, without exposing filesystem paths.
     */
    public function inspect(string $directory, bool $checkUnreferenced = false): array
    {
        $manifestPath = $directory . '/manifest.json';

        if (!is_file($manifestPath) || !is_readable($manifestPath)) {
            return ['manifest_missing'];
        }

        try {
            $manifest = json_decode((string) file_get_contents($manifestPath), true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return ['manifest_invalid'];
        }

        if (!is_array($manifest) || !isset($manifest[self::ENTRYPOINT])) {
            return ['entrypoint_missing'];
        }

        $errors = [];
        $referenced = [];

        foreach ($manifest as $entry) {
            if (!is_array($entry) || !is_string($entry['file'] ?? null)) {
                $errors[] = 'entry_invalid';
                continue;
            }

            foreach ($this->files($entry) as $file) {
                if (!$this->isLocalFile($directory, $file)) {
                    $errors[] = 'asset_missing_or_unsafe';
                    continue;
                }

                $referenced[$file] = true;
            }

            foreach (['imports', 'dynamicImports'] as $key) {
                foreach ($this->list($entry, $key) as $import) {
                    if (!is_string($import) || !array_key_exists($import, $manifest)) {
                        $errors[] = 'import_missing';
                    }
                }
            }
        }

        if ($checkUnreferenced && $this->hasUnreferencedFiles($directory, $referenced)) {
            $errors[] = 'asset_unreferenced';
        }

        return array_values(array_unique($errors));
    }

    /**
     * @param array<string, mixed> $entry
     *
     * @return list<mixed>
     */
    private function files(array $entry): array
    {
        return [
            $entry['file'],
            ...$this->list($entry, 'css'),
            ...$this->list($entry, 'assets'),
        ];
    }

    /**
     * @param array<string, mixed> $entry
     *
     * @return list<mixed>
     */
    private function list(array $entry, string $key): array
    {
        $values = array_key_exists($key, $entry) ? $entry[$key] : [];

        // A malformed list must fail validation rather than be treated as empty.
        return is_array($values) && array_is_list($values) ? $values : [null];
    }

    private function isLocalFile(string $directory, mixed $file): bool
    {
        if (!is_string($file) || $file === '' || str_starts_with($file, '/') || str_contains($file, '\\') || str_contains($file, "\0")) {
            return false;
        }

        if (array_any(explode('/', $file), static fn(string $part): bool => in_array($part, ['', '.', '..'], true))) {
            return false;
        }

        $root = realpath($directory);
        $path = realpath($directory.'/'.$file);

        return $root !== false && $path !== false
            && str_starts_with($path, $root.DIRECTORY_SEPARATOR) && is_file($path);
    }

    /**
     * @param array<string, true> $referenced
     */
    private function hasUnreferencedFiles(string $directory, array $referenced): bool
    {
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(
            $directory,
            \FilesystemIterator::SKIP_DOTS
        ));

        foreach ($files as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile()) {
                continue;
            }

            $relative = substr($file->getPathname(), strlen(rtrim($directory, '/')) + 1);
            if ($relative !== 'manifest.json' && !isset($referenced[$relative])) {
                return true;
            }
        }

        return false;
    }
}
