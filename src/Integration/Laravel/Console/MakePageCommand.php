<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Integration\Laravel\Console;

use Illuminate\Console\Attributes\Signature;
use Illuminate\Filesystem\Filesystem;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

#[Signature(
    'flashboard:make-page
    {name? : Page class name}
    {--title= : Page title}
    {--uri= : Page URI}
    {--description= : Workspace description}
    {--component : Generate a browser-registered Vue component page}
    {--component-name= : Browser page component registry name}
    {--force : Overwrite the target file if it exists}'
)]
final class MakePageCommand extends \Illuminate\Console\Command
{
    private const string COMPONENT_NAME_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9._:-]*$/';

    private const string COMPONENT_SCRIPT_ENTRY = 'resources/js/flashboard/pages.ts';

    private const string COMPONENTS_ENTRY_PATH = 'resources/js/flashboard/pages.ts';

    private const string COMPONENTS_PAGE_DIRECTORY = 'resources/js/flashboard/pages';

    protected $description = 'Generate a Flashboard custom page with prompt-driven defaults.';

    protected $aliases = ['fb:mp'];

    public function handle(Filesystem $files): int
    {
        $className = $this->qualifyPageClassName(
            (string) ($this->argument('name') ?: text(
                label: 'Page class name',
                default: 'ReviewQueuePage',
                required: true,
            )),
        );
        $title = $this->stringOption('title') ?? text(
            label: 'Page title',
            default: $this->defaultPageTitle($className),
            required: true,
        );
        $uri = trim($this->stringOption('uri') ?? text(
            label: 'Page URI',
            default: $this->defaultPageUri($className),
            required: true,
        ), '/');
        $description = trim($this->stringOption('description') ?? text(
            label: 'Workspace description (optional)',
        ));
        $componentName = $this->stringOption('component-name');
        $withComponent = (bool) $this->option('component') || $componentName !== null;

        if (!$withComponent && $this->input->isInteractive()) {
            $withComponent = confirm('Generate a Vue component for this page?', default: false);
        }

        if ($withComponent) {
            $componentName ??= $this->defaultComponentName($className);

            if (!$this->isValidComponentName($componentName)) {
                warning("Invalid page component name: $componentName");

                return self::FAILURE;
            }
        }

        $targetDirectory = app_path('Flashboard');
        $targetPath = $targetDirectory . '/' . $className . '.php';
        $componentPath = $withComponent
            ? base_path(self::COMPONENTS_PAGE_DIRECTORY . '/' . $className . '.vue')
            : null;

        if ($files->exists($targetPath) && !$this->option('force')) {
            warning("File already exists: $targetPath");

            return self::FAILURE;
        }

        if ($componentPath !== null && $files->exists($componentPath) && !$this->option('force')) {
            warning("File already exists: $componentPath");

            return self::FAILURE;
        }

        $files->ensureDirectoryExists($targetDirectory);
        $files->put($targetPath, $this->renderStub(
            $files->get(dirname(__DIR__, 4) . '/stubs/page.stub'),
            className: $className,
            title: $title,
            uri: $uri,
            description: $description,
            componentName: $componentName,
        ));

        info('Flashboard page created: ' . $this->relativePath($targetPath));

        if ($withComponent && $componentPath !== null && $componentName !== null) {
            $this->writeComponentFiles($files, $componentPath, $className, $title, $componentName);
        }

        note('Pages placed in app/Flashboard are auto-discovered when your panel provider calls $this->panelConfig()->discover().');

        if ($withComponent) {
            note('Add resources/js/flashboard/pages.ts to your host Vite inputs so the generated page component can register itself.');
        }

        return self::SUCCESS;
    }

    private function qualifyPageClassName(string $className): string
    {
        return str_ends_with($className, 'Page') ? $className : $className . 'Page';
    }

    private function defaultPageTitle(string $className): string
    {
        return str(str_replace('Page', '', $className))->headline()->toString();
    }

    private function defaultPageUri(string $className): string
    {
        return str(str_replace('Page', '', $className))->snake()->replace('_', '/')->toString();
    }

    private function defaultComponentName(string $className): string
    {
        return str(str_replace('Page', '', $className))->kebab()->toString();
    }

    private function isValidComponentName(string $componentName): bool
    {
        return preg_match(self::COMPONENT_NAME_PATTERN, $componentName) === 1;
    }

    private function stringOption(string $key): ?string
    {
        $value = $this->option($key);

        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function renderStub(
        string $stub,
        string $className,
        string $title,
        string $uri,
        string $description,
        ?string $componentName,
    ): string {
        return str_replace(
            ['{{ namespace }}', '{{ class }}', '{{ title }}', '{{ uri }}', '{{ description }}', '{{ component_methods }}'],
            [
                'App\\Flashboard',
                $className,
                $title,
                $uri,
                $description === '' ? 'null' : "'" . addslashes($description) . "'",
                $this->renderComponentMethods($componentName),
            ],
            $stub,
        );
    }

    private function renderComponentMethods(?string $componentName): string
    {
        if ($componentName === null) {
            return '';
        }

        return implode(PHP_EOL, [
            '    public static function component(): ?string',
            '    {',
            "        return '$componentName';",
            '    }',
            '',
            '    /**',
            '     * @param array<string, mixed> $context',
            '     *',
            '     * @return array<string, mixed>',
            '     */',
            '    public static function componentProps(array $context = []): array',
            '    {',
            '        return [',
            "            'initialFilters' => \$context['query'] ?? [],",
            '        ];',
            '    }',
            '',
            '    /** @return array{scripts: list<array{url: string, type: string}>, styles: list<array{url: string}>} */',
            '    public static function componentAssets(): array',
            '    {',
            '        return [',
            "            'scripts' => [",
            '                [',
            "                    'url' => app(\\Illuminate\\Foundation\\Vite::class)->asset('" . self::COMPONENT_SCRIPT_ENTRY . "'),",
            "                    'type' => 'module',",
            '                ],',
            '            ],',
            "            'styles' => [],",
            '        ];',
            '    }',
            '',
        ]);
    }

    private function writeComponentFiles(
        Filesystem $files,
        string $componentPath,
        string $className,
        string $title,
        string $componentName,
    ): void {
        $files->ensureDirectoryExists(dirname($componentPath));
        $files->put($componentPath, str_replace(
            ['{{ title }}'],
            [$title],
            $files->get(dirname(__DIR__, 4) . '/stubs/page-component.vue.stub'),
        ));

        $entryPath = base_path(self::COMPONENTS_ENTRY_PATH);
        $files->ensureDirectoryExists(dirname($entryPath));
        $this->writeComponentEntry($files, $entryPath, $className, $componentName);

        info('Flashboard page component created: ' . $this->relativePath($componentPath));
        info('Flashboard page component entry updated: ' . $this->relativePath($entryPath));
    }

    private function writeComponentEntry(
        Filesystem $files,
        string $entryPath,
        string $className,
        string $componentName,
    ): void {
        $importLine = "import $className from './pages/$className.vue'";
        $registrationLine = "window.Flashboard?.registerPageComponent?.('$componentName', $className)";

        if (!$files->exists($entryPath)) {
            $files->put($entryPath, str_replace(
                ['{{ component_import }}', '{{ component_file }}', '{{ component_name }}'],
                [$className, $className, $componentName],
                $files->get(dirname(__DIR__, 4) . '/stubs/page-components-entry.ts.stub'),
            ));

            return;
        }

        $content = $files->get($entryPath);

        if (!str_contains($content, $importLine)) {
            $content = $importLine . PHP_EOL . $content;
        }

        if (!str_contains($content, $registrationLine)) {
            $content = rtrim($content) . PHP_EOL . PHP_EOL . $registrationLine . PHP_EOL;
        }

        $files->put($entryPath, $content);
    }

    private function relativePath(string $path): string
    {
        $basePath = rtrim(base_path(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        return str_starts_with($path, $basePath)
            ? substr($path, strlen($basePath))
            : $path;
    }
}
