<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Integration\Laravel\Console;

use Illuminate\Console\Attributes\Signature;
use Illuminate\Filesystem\Filesystem;
use Pepperfm\Flashboard\Contracts\Detail\DetailContract;
use Pepperfm\Flashboard\Contracts\Forms\FormContract;
use Pepperfm\Flashboard\Contracts\Resources\Resource;
use Pepperfm\Flashboard\Contracts\Tables\TableContract;
use Pepperfm\Flashboard\Core\Detail\Entries\TextEntry;
use Pepperfm\Flashboard\Core\Forms\Fields\TextInput;
use Pepperfm\Flashboard\Core\Forms\Layout\Section;
use Pepperfm\Flashboard\Core\Tables\Columns\TextColumn;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

#[Signature(
    'flashboard:make-resource
    {name? : Resource class name}
    {model? : Eloquent model FQCN}
    {--force : Overwrite the target file if it exists}'
)]
final class MakeResourceCommand extends \Illuminate\Console\Command
{
    protected $description = 'Generate a Flashboard resource with prompt-driven defaults.';

    protected $aliases = ['fb:mr'];

    public function handle(Filesystem $files): int
    {
        $className = $this->qualifyResourceClassName(
            (string) ($this->argument('name') ?: text(
                label: 'Resource class name',
                default: 'UsersResource',
                required: true,
            )),
        );
        $modelClass = (string) ($this->argument('model') ?: text(
            label: 'Eloquent model class',
            default: 'App\\Models\\User',
            required: true,
        ));
        $titleField = text(
            label: 'Primary display field',
            default: 'email',
            required: true,
        );
        $secondaryField = trim(text(
            label: 'Secondary field (optional)',
        ));
        $navigationGroup = trim(text(
            label: 'Navigation group (optional)',
        ));
        $includeDetail = confirm('Include detail screen?');

        $targetDirectory = app_path('Flashboard');
        $targetPath = "$targetDirectory/$className.php";

        if ($files->exists($targetPath) && !$this->option('force')) {
            warning("File already exists: $targetPath");

            return self::FAILURE;
        }

        $files->ensureDirectoryExists($targetDirectory);
        $files->put($targetPath, $this->renderStub(
            $files->get(dirname(__DIR__, 4) . '/stubs/resource.stub'),
            className: $className,
            modelClass: $modelClass,
            titleField: $titleField,
            secondaryField: $secondaryField,
            navigationGroup: $navigationGroup,
            includeDetail: $includeDetail,
        ));

        info('Flashboard resource created: ' . $this->relativePath($targetPath));
        note('Resources placed in app/Flashboard are auto-discovered when your panel provider calls $this->panelConfig()->discover().');

        return self::SUCCESS;
    }

    private function qualifyResourceClassName(string $className): string
    {
        return str_ends_with($className, 'Resource') ? $className : $className . 'Resource';
    }

    private function renderStub(
        string $stub,
        string $className,
        string $modelClass,
        string $titleField,
        string $secondaryField,
        string $navigationGroup,
        bool $includeDetail,
    ): string {
        $imports = [
            Resource::class,
            FormContract::class,
            Section::class,
            TableContract::class,
            TextInput::class,
            TextColumn::class,
        ];

        if ($includeDetail) {
            $imports[] = DetailContract::class;
            $imports[] = TextEntry::class;
        }

        sort($imports);

        return str_replace(
            [
                '{{ namespace }}',
                '{{ model }}',
                '{{ model_basename }}',
                '{{ imports }}',
                '{{ class }}',
                '{{ navigation_group_method }}',
                '{{ title_field }}',
                '{{ title_label }}',
                '{{ title_table_suffix }}',
                '{{ title_input_suffix }}',
                '{{ secondary_table_column }}',
                '{{ secondary_form_field }}',
                '{{ secondary_form_rule }}',
                '{{ detail_method }}',
            ],
            [
                'App\\Flashboard',
                $modelClass,
                class_basename($modelClass),
                implode(PHP_EOL, array_map(static fn (string $import): string => 'use ' . $import . ';', $imports)),
                $className,
                $this->renderNavigationGroupMethod($navigationGroup),
                $titleField,
                str($titleField)->headline()->toString(),
                '',
                $this->inputSuffix($titleField),
                $this->secondaryTableColumn($secondaryField),
                $this->secondaryFormField($secondaryField),
                $this->secondaryFormRule($secondaryField),
                $this->renderDetailMethod($titleField, $secondaryField, $includeDetail),
            ],
            $stub,
        );
    }

    private function renderNavigationGroupMethod(string $navigationGroup): string
    {
        if ($navigationGroup === '') {
            return '';
        }

        return str_replace(
            '{{ navigation_group }}',
            $navigationGroup,
            $this->stubContents('resource-navigation-group.stub'),
        );
    }

    private function secondaryTableColumn(string $secondaryField): string
    {
        if ($secondaryField === '') {
            return '';
        }

        $label = str($secondaryField)->headline()->toString();

        return implode(PHP_EOL, [
            "            TextColumn::make('{$secondaryField}')",
            "                ->label('{$label}')",
            '                ->searchable(),',
        ]) . PHP_EOL;
    }

    private function secondaryFormField(string $secondaryField): string
    {
        if ($secondaryField === '') {
            return '';
        }

        $label = str($secondaryField)->headline()->toString();

        return implode(PHP_EOL, [
            "                        TextInput::make('{$secondaryField}')",
            "                            ->label('{$label}'){$this->inputSuffix($secondaryField)},",
        ]) . PHP_EOL;
    }

    private function secondaryFormRule(string $secondaryField): string
    {
        if ($secondaryField === '') {
            return '';
        }

        return "                '{$secondaryField}' => ['nullable', 'string']," . PHP_EOL;
    }

    private function renderDetailMethod(
        string $titleField,
        string $secondaryField,
        bool $includeDetail,
    ): string {
        if (!$includeDetail) {
            return '';
        }

        return str_replace(
            [
                '{{ title_field }}',
                '{{ title_label }}',
                '{{ secondary_detail_entry }}',
            ],
            [
                $titleField,
                str($titleField)->headline()->toString(),
                $this->secondaryDetailEntry($secondaryField),
            ],
            $this->stubContents('resource-detail-method.stub'),
        );
    }

    private function secondaryDetailEntry(string $secondaryField): string
    {
        if ($secondaryField === '') {
            return '';
        }

        $label = str($secondaryField)->headline()->toString();

        return implode(PHP_EOL, [
            "            TextEntry::make('{$secondaryField}')",
            "                ->label('{$label}'),",
        ]) . PHP_EOL;
    }

    private function inputSuffix(string $field): string
    {
        return str_contains(strtolower($field), 'email') ? PHP_EOL . '                            ->email()' : '';
    }

    private function relativePath(string $path): string
    {
        $basePath = rtrim(base_path(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        return str_starts_with($path, $basePath)
            ? substr($path, strlen($basePath))
            : $path;
    }

    private function stubContents(string $stub): string
    {
        return (string) file_get_contents(dirname(__DIR__, 4) . '/stubs/' . $stub);
    }
}
