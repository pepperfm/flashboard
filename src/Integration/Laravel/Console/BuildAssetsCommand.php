<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Integration\Laravel\Console;

use Illuminate\Console\Attributes\Signature;
use Illuminate\Filesystem\Filesystem;
use Pepperfm\Flashboard\Integration\Laravel\Console\Concerns\InteractsWithFrontendAssets;

use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\warning;

#[Signature(
    'flashboard:build-assets
    {--force : Overwrite published views and assets when supported}
    {--bun : Use bun for install and build}
    {--npm : Use npm for install and build}
    {--pnpm : Use pnpm for install and build}
    {--yarn : Use yarn for install and build}
    {--skip : Skip frontend install and build}'
)]
final class BuildAssetsCommand extends \Illuminate\Console\Command
{
    use InteractsWithFrontendAssets;

    protected $description = 'Install frontend dependencies and build Flashboard package assets.';

    protected $aliases = ['fb:ba'];

    public function handle(Filesystem $files): int
    {
        $packageManager = $this->requestedPackageManager($files);
        $assetsReady = $this->runFrontendSetup($packageManager, $files);

        if (!$assetsReady) {
            warning('No frontend assets were built.');

            return self::FAILURE;
        }

        $this->publishFrontendArtifacts((bool) $this->option('force'));

        info('Flashboard assets built successfully.');
        note('Published Flashboard views and assets into the host application.');

        return self::SUCCESS;
    }
}
