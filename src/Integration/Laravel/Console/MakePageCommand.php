<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Integration\Laravel\Console;

use Illuminate\Console\Attributes\Signature;
use Illuminate\Filesystem\Filesystem;

use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

#[Signature('flashboard:make-page {name? : Page class name} {--force : Overwrite the target file if it exists}')]
final class MakePageCommand extends \Illuminate\Console\Command
{
    protected $description = 'Generate a Flashboard custom page with prompt-driven defaults.';

    public function handle(Filesystem $files): int
    {
        $className = $this->qualifyPageClassName(
            (string) ($this->argument('name') ?: text(
                label: 'Page class name',
                default: 'ReviewQueuePage',
                required: true,
            )),
        );
        $title = (string) text(
            label: 'Page title',
            default: str(str_replace('Page', '', $className))->headline()->toString(),
            required: true,
        );
        $uri = trim((string) text(
            label: 'Page URI',
            default: str(str_replace('Page', '', $className))->snake()->replace('_', '/')->toString(),
            required: true,
        ), '/');
        $description = trim((string) text(
            label: 'Workspace description (optional)',
            default: '',
            required: false,
        ));

        $targetDirectory = app_path('Flashboard');
        $targetPath = $targetDirectory . '/' . $className . '.php';

        if ($files->exists($targetPath) && !$this->option('force')) {
            warning("File already exists: {$targetPath}");

            return self::FAILURE;
        }

        $files->ensureDirectoryExists($targetDirectory);
        $files->put($targetPath, str_replace(
            ['{{ namespace }}', '{{ class }}', '{{ title }}', '{{ uri }}', '{{ description }}'],
            [
                'App\\Flashboard',
                $className,
                $title,
                $uri,
                $description === '' ? 'null' : "'" . addslashes($description) . "'",
            ],
            $files->get(dirname(__DIR__, 4) . '/stubs/page.stub'),
        ));

        info("Flashboard page created: {$targetPath}");
        note('Register it inline via Flashboard::configure()->page(' . "App\\\\Flashboard\\\\{$className}::class" . ');');

        return self::SUCCESS;
    }

    private function qualifyPageClassName(string $className): string
    {
        return str_ends_with($className, 'Page') ? $className : $className . 'Page';
    }
}
