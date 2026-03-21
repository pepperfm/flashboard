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
                '{{ table_columns }}',
                '{{ form_fields }}',
                '{{ form_rules }}',
                '{{ detail_method }}',
            ],
            [
                'App\\Flashboard',
                $modelClass,
                class_basename($modelClass),
                implode(PHP_EOL, array_map(static fn (string $import): string => 'use ' . $import . ';', $imports)),
                $className,
                $this->navigationGroupMethod($navigationGroup),
                $this->tableColumns($titleField, $secondaryField),
                $this->formFields($titleField, $secondaryField),
                $this->formRules($titleField, $secondaryField),
                $this->detailMethod($includeDetail, $titleField, $secondaryField),
            ],
            $stub,
        );
    }

    private function navigationGroupMethod(string $navigationGroup): string
    {
        if ($navigationGroup === '') {
            return '';
        }

        return <<<PHP
    public static function navigationGroup(): ?string
    {
        return '$navigationGroup';
    }

PHP;
    }

    private function tableColumns(string $titleField, string $secondaryField): string
    {
        $columns = [
            $this->textColumnExpression('id', label: 'ID', sortable: true, indent: '            ') . ',',
            $this->textColumnExpression(
                $titleField,
                label: str($titleField)->headline()->toString(),
                sortable: true,
                searchable: true,
                indent: '            ',
            ) . ',',
        ];

        if ($secondaryField !== '') {
            $columns[] = $this->textColumnExpression(
                $secondaryField,
                label: str($secondaryField)->headline()->toString(),
                searchable: true,
                indent: '            ',
            ) . ',';
        }

        return implode(PHP_EOL, $columns);
    }

    private function formFields(string $titleField, string $secondaryField): string
    {
        $fields = [
            $this->textInputExpression($titleField, required: true, indent: '                    ') . ',',
        ];

        if ($secondaryField !== '') {
            $fields[] = $this->textInputExpression($secondaryField, indent: '                    ') . ',';
        }

        return implode(PHP_EOL, $fields);
    }

    private function formRules(string $titleField, string $secondaryField): string
    {
        $rules = [
            "                '$titleField' => ['required', 'string'],",
        ];

        if ($secondaryField !== '') {
            $rules[] = "                '$secondaryField' => ['nullable', 'string'],";
        }

        return implode(PHP_EOL, $rules);
    }

    private function detailMethod(bool $includeDetail, string $titleField, string $secondaryField): string
    {
        if (!$includeDetail) {
            return '';
        }

        $entries = [
            $this->textEntryExpression('id', label: 'ID', indent: '            ') . ',',
            $this->textEntryExpression(
                $titleField,
                label: str($titleField)->headline()->toString(),
                indent: '            ',
            ) . ',',
        ];

        if ($secondaryField !== '') {
            $entries[] = $this->textEntryExpression(
                $secondaryField,
                label: str($secondaryField)->headline()->toString(),
                indent: '            ',
            ) . ',';
        }

        $detailEntries = implode(PHP_EOL, $entries);

        return <<<PHP
    public static function infolist(DetailContract \$detail): DetailContract
    {
        return \$detail->entries([
{$detailEntries}
        ]);
    }

PHP;
    }

    private function relativePath(string $path): string
    {
        $basePath = rtrim(base_path(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        return str_starts_with($path, $basePath)
            ? substr($path, strlen($basePath))
            : $path;
    }

    private function textInputExpression(string $field, bool $required = false, string $indent = ''): string
    {
        $lines = [
            "{$indent}TextInput::make('$field')",
            "{$indent}    ->label('" . str($field)->headline()->toString() . "')",
        ];

        if ($required) {
            $lines[] = "{$indent}    ->required()";
        }

        if (str_contains(strtolower($field), 'email')) {
            $lines[] = "{$indent}    ->email()";
        }

        return implode(PHP_EOL, $lines);
    }

    private function textColumnExpression(
        string $field,
        ?string $label = null,
        bool $sortable = false,
        bool $searchable = false,
        string $indent = '',
    ): string {
        $resolvedLabel = $label ?? str($field)->headline()->toString();
        $lines = [
            "{$indent}TextColumn::make('$field')",
            "{$indent}    ->label('$resolvedLabel')",
        ];

        if ($sortable) {
            $lines[] = "{$indent}    ->sortable()";
        }

        if ($searchable) {
            $lines[] = "{$indent}    ->searchable()";
        }

        return implode(PHP_EOL, $lines);
    }

    private function textEntryExpression(string $field, ?string $label = null, string $indent = ''): string
    {
        $resolvedLabel = $label ?? str($field)->headline()->toString();

        return implode(PHP_EOL, [
            "{$indent}TextEntry::make('$field')",
            "{$indent}    ->label('$resolvedLabel')",
        ]);
    }
}
