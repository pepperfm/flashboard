<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Integration\Laravel\Console;

use Illuminate\Console\Attributes\Signature;
use Pepperfm\Flashboard\Integration\Laravel\FlashboardServiceProvider;

use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\table;
use function Laravel\Prompts\warning;

#[Signature('flashboard:install {--force : Overwrite publish targets when supported}')]
final class InstallCommand extends \Illuminate\Console\Command
{
    protected $description = 'Publish Flashboard configuration and starter panel assets.';

    public function handle(): int
    {
        info('Installing Flashboard...');

        $publishOptions = [
            '--provider' => FlashboardServiceProvider::class,
        ];
        if ($this->option('force')) {
            $publishOptions['--force'] = true;
        }

        $this->call('vendor:publish', $publishOptions + ['--tag' => 'flashboard-config']);
        $this->call('vendor:publish', $publishOptions + ['--tag' => 'flashboard-views']);
        $this->call('vendor:publish', $publishOptions + ['--tag' => 'flashboard-assets']);

        info('Flashboard install bootstrap completed.');
        note('Next steps');
        table(
            ['Step', 'Action'],
            [
                ['1', 'Review config/flashboard.php'],
                ['2', 'Register a resource/page in config/flashboard.php'],
                ['3', 'Ensure your auth middleware can protect /admin'],
                ['4', 'Visit /admin to confirm the package wiring'],
            ],
        );

        if ($this->option('force')) {
            warning('Install ran with --force, so published targets may have been overwritten.');
        }

        return self::SUCCESS;
    }
}
