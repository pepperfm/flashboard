<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Integration\Laravel\Console;

use Illuminate\Console\Attributes\Signature;
use Illuminate\Filesystem\Filesystem;
use Pepperfm\Flashboard\Integration\Laravel\Console\Concerns\InteractsWithFrontendAssets;

use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\warning;

#[Signature('flashboard:build-assets')]
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

        info('Flashboard assets built successfully.');
        note('If you are working from a host app, publish the updated assets with php artisan vendor:publish --tag=flashboard-assets --force.');

        return self::SUCCESS;
    }
}
