<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Integration\Laravel\Console;

use Illuminate\Console\Attributes\Signature;
use Illuminate\Filesystem\Filesystem;

use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\warning;

#[Signature('flashboard:make-demo-resource {name=DemoOrdersResource} {--force : Overwrite the target file if it exists}')]
final class MakeDemoResourceCommand extends \Illuminate\Console\Command
{
    protected $description = 'Generate a demo Flashboard resource from the package stub.';

    public function handle(Filesystem $files): int
    {
        $className = (string) $this->argument('name');
        $targetDirectory = app_path('Flashboard');
        $targetPath = "$targetDirectory/$className.php";

        if ($files->exists($targetPath) && !$this->option('force')) {
            warning("File already exists: $targetPath");

            return self::FAILURE;
        }

        $stub = $files->get(dirname(__DIR__, 4) . '/stubs/demo-resource.stub');
        $contents = str_replace(
            ['{{ namespace }}', '{{ class }}'],
            ['App\\Flashboard', $className],
            $stub,
        );

        $files->ensureDirectoryExists($targetDirectory);
        $files->put($targetPath, $contents);

        info("Demo resource created: $targetPath");
        note('Register the generated class in config/flashboard.php under discovery.resources to expose it in the panel.');

        return self::SUCCESS;
    }
}
