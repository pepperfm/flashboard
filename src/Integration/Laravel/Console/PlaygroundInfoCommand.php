<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Integration\Laravel\Console;

use Illuminate\Console\Attributes\Signature;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\table;

#[Signature('flashboard:playground')]
final class PlaygroundInfoCommand extends \Illuminate\Console\Command
{
    protected $description = 'Show local playground guidance for developing Flashboard resources and pages.';

    public function handle(): int
    {
        info('Flashboard playground guidance');
        note('Suggested flow');
        table(
            ['Step', 'Action'],
            [
                ['1', 'Read playground/README.md'],
                ['2', 'Generate a demo resource with php artisan flashboard:make-demo-resource'],
                ['3', 'Register your resource class in config/flashboard.php under discovery.resources'],
                ['4', 'Visit /admin/resources/<resource-key>'],
            ],
        );

        return self::SUCCESS;
    }
}
