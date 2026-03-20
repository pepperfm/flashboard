<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\UI\Assets;

use Illuminate\Routing\UrlGenerator;

final readonly class PublishedAssetManager
{
    private const string ENTRYPOINT = 'resources/js/flashboard.ts';

    public function __construct(
        private UrlGenerator $urlGenerator,
        private string $hostPublicPath,
        private string $packageRoot,
    ) {
    }

    /**
     * @return list<string>
     */
    public function styles(): array
    {
        $entry = $this->entry();
        if (!is_array($entry)) {
            return [];
        }

        $styles = $entry['css'] ?? [];
        if (!is_array($styles)) {
            return [];
        }

        return array_values(array_filter(
            array_map(fn(mixed $path): ?string => is_string($path) ? $this->getAssets($path) : null, $styles),
            static fn(?string $path): bool => $path !== null,
        ));
    }

    public function script(): ?string
    {
        $entry = $this->entry();

        $file = $entry['file'] ?? null;
        if (!is_string($file)) {
            return null;
        }

        return $this->urlGenerator->asset('vendor/flashboard/build/' . ltrim($file, '/'));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function entry(): ?array
    {
        $manifest = $this->manifest();

        $entry = $manifest[self::ENTRYPOINT] ?? null;

        return is_array($entry) ? $entry : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function manifest(): array
    {
        $manifestPath = $this->hostPublicPath . '/vendor/flashboard/build/manifest.json';

        if (!is_file($manifestPath)) {
            $manifestPath = $this->packageRoot . '/public/build/manifest.json';
        }
        if (!is_file($manifestPath)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($manifestPath), true);

        return is_array($decoded) ? $decoded : [];
    }

    private function getAssets(string $path): string
    {
        return $this->urlGenerator->asset('vendor/flashboard/build/' . ltrim($path, '/'));
    }
}
