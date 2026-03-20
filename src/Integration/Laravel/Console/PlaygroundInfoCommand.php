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
        $resourcePath = $this->resourcePath();

        info('Flashboard playground guidance');
        note('Suggested flow');
        table(
            ['Step', 'Action'],
            [
                ['1', 'Read playground/README.md'],
                ['2', 'Generate a panel provider with php artisan flashboard:make-provider'],
                ['3', 'Generate a resource with php artisan flashboard:make-resource'],
                ['4', sprintf('Visit %s', $resourcePath)],
            ],
        );

        return self::SUCCESS;
    }

    private function resourcePath(): string
    {
        $path = trim((string) config('flashboard.path', 'admin'), '/');

        if ($path === '') {
            return '/resources/<resource-key>';
        }

        return sprintf('/%s/resources/<resource-key>', $path);
    }
}
