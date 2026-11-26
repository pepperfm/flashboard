<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Integration\Laravel\Diagnostics;

use Illuminate\Contracts\Foundation\Application;
use Pepperfm\Flashboard\UI\Assets\AssetManifestInspector;

final readonly class PanelDoctor
{
    public function __construct(
        private Application $app,
        private PanelConfigurationChecks $configuration,
        private ResourceDiagnosticChecks $resources,
        private AssetManifestInspector $assets
    ) {
    }

    /**
     * @return list<Diagnostic>
     */
    public function inspect(): array
    {
        return [
            ...$this->configuration->inspect(),
            ...$this->resources->inspect(),
            ...$this->assetChecks(),
        ];
    }

    /**
     * @return list<Diagnostic>
     */
    private function assetChecks(): array
    {
        $packageDirectory = dirname(__DIR__, 4) . '/public/build';
        $publishedDirectory = $this->app->publicPath('vendor/flashboard/build');

        $packageErrors = $this->assets->inspect($packageDirectory);
        $publishedErrors = $this->assets->inspect($publishedDirectory);
        $diagnostics = [];

        if ($packageErrors !== []) {
            $diagnostics[] = new Diagnostic('package_assets', DiagnosticSeverity::Error, 'Package assets are incomplete. Verify the installed distribution.');
        }
        if ($publishedErrors !== []) {
            $diagnostics[] = new Diagnostic('published_assets', DiagnosticSeverity::Error, 'Published assets are missing or invalid. Run vendor:publish --tag=flashboard-assets --force.');
        }
        if ($diagnostics !== []) {
            return $diagnostics;
        }

        $packageHash = hash_file('sha256', $packageDirectory . '/manifest.json');
        $publishedHash = hash_file('sha256', $publishedDirectory . '/manifest.json');

        return $packageHash === $publishedHash
            ? [new Diagnostic('published_assets', DiagnosticSeverity::Ok, 'Published manifest matches the installed package and all referenced files exist.')]
            : [new Diagnostic('published_assets_stale', DiagnosticSeverity::Error, 'Published manifest differs from the installed package. Republish Flashboard assets.')];
    }
}
