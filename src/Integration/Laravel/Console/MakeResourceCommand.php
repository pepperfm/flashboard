<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Integration\Laravel\Console;

use Illuminate\Console\Attributes\Signature;
use Illuminate\Filesystem\Filesystem;
use Pepperfm\Flashboard\Contracts\Resources\Resource;
use Pepperfm\Flashboard\Contracts\Detail\DetailContract;
use Pepperfm\Flashboard\Contracts\Forms\FormContract;
use Pepperfm\Flashboard\Contracts\Tables\TableContract;
use Pepperfm\Flashboard\Core\Actions\Builders\Action;
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
            default: 'name',
            required: true,
        );
        $secondaryField = trim(text(
            label: 'Secondary field (optional)',
            default: 'email',
        ));
        $navigationGroup = trim(text(
            label: 'Navigation group (optional)',
        ));
        $includeForm = confirm('Include create and edit form?');
        $includeDetail = confirm('Include detail screen?');
        $includeActions = confirm('Include example action?', default: false);

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
            includeForm: $includeForm,
            includeDetail: $includeDetail,
            includeActions: $includeActions,
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
        bool $includeForm,
        bool $includeDetail,
        bool $includeActions,
    ): string {
        $imports = [
            Resource::class,
            TableContract::class,
        ];
        if ($includeForm) {
            $imports[] = FormContract::class;
            $imports[] = Section::class;
            $imports[] = TextInput::class;
        }
        if ($includeDetail) {
            $imports[] = DetailContract::class;
            $imports[] = TextEntry::class;
        }
        $imports[] = TextColumn::class;
        if ($includeActions) {
            $imports[] = Action::class;
        }

        sort($imports);

        return str_replace(
            [
                '{{ namespace }}',
                '{{ model }}',
                '{{ imports }}',
                '{{ class }}',
                '{{ methods }}',
            ],
            [
                'App\\Flashboard',
                $modelClass,
                implode(PHP_EOL, array_map(static fn (string $import): string => 'use ' . $import . ';', $imports)),
                $className,
                $this->methods(
                    $this->modelMethod(class_basename($modelClass)),
                    $this->navigationGroupMethod($navigationGroup),
                    $this->tableMethod($titleField, $secondaryField),
                    $this->formMethod($includeForm, $titleField, $secondaryField),
                    $this->detailMethod($includeDetail, $titleField, $secondaryField),
                    $this->actionsMethod($includeActions, $className),
                ),
            ],
            $stub,
        );
    }

    private function modelMethod(string $modelBasename): string
    {
        return <<<PHP
    public static function model(): string
    {
        return {$modelBasename}::class;
    }
PHP;
    }

    private function navigationGroupMethod(string $navigationGroup): string
    {
        if ($navigationGroup === '') {
            return '';
        }

        return <<<PHP
    public static function navigationGroup(): ?string
    {
        return '{$navigationGroup}';
    }
PHP;
    }

    private function secondaryTableColumn(string $secondaryField): string
    {
        if ($secondaryField === '') {
            return '';
        }

        return PHP_EOL . "            TextColumn::make('$secondaryField')->label('" . str($secondaryField)->headline()->toString() . "')->searchable(),";
    }

    private function tableMethod(string $titleField, string $secondaryField): string
    {
        $titleLabel = str($titleField)->headline()->toString();
        $secondaryTableColumn = $this->secondaryTableColumn($secondaryField);

        return <<<PHP
    public static function table(TableContract \$table): TableContract
    {
        return \$table->columns([
            TextColumn::make('id')->label('ID')->sortable(),
            TextColumn::make('$titleField')->label('$titleLabel')->sortable()->searchable(),{$secondaryTableColumn}
        ]);
    }
PHP;
    }

    private function formMethod(bool $includeForm, string $titleField, string $secondaryField): ?string
    {
        if (!$includeForm) {
            return null;
        }

        $fieldRows = [
            '                ' . $this->textInputExpression($titleField, required: true) . ',',
        ];
        $rules = [
            "                '$titleField' => ['required', 'string'],",
        ];

        if ($secondaryField !== '') {
            $fieldRows[] = '                ' . $this->textInputExpression($secondaryField) . ',';
            $rules[] = "                '$secondaryField' => ['nullable', 'string'],";
        }

        $fields = implode(PHP_EOL, $fieldRows);
        $formRules = implode(PHP_EOL, $rules);

        return <<<PHP
    public static function form(FormContract \$form): FormContract
    {
        return \$form
            ->sections([
                Section::make('main')->label('Main')->schema([
{$fields}
                ]),
            ])
            ->rules([
{$formRules}
            ]);
    }
PHP;
    }

    private function detailMethod(bool $includeDetail, string $titleField, string $secondaryField): ?string
    {
        if (!$includeDetail) {
            return null;
        }

        $entries = [
            "            TextEntry::make('id')->label('ID'),",
            "            TextEntry::make('$titleField')->label('" . str($titleField)->headline()->toString() . "'),",
        ];

        if ($secondaryField !== '') {
            $entries[] = "            TextEntry::make('$secondaryField')->label('" . str($secondaryField)->headline()->toString() . "'),";
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

    private function actionsMethod(bool $includeActions, string $className): ?string
    {
        if (!$includeActions) {
            return null;
        }

        $resourceLabel = str(str_replace('Resource', '', $className))->headline()->value();

        return <<<PHP
    public static function actions(): array
    {
        return [
            Action::make('refresh')
                ->label('Refresh')
                ->successMessage('{$resourceLabel} refreshed.'),
        ];
    }
PHP;
    }

    private function methods(?string ...$methods): string
    {
        $methods = array_values(array_filter(
            $methods,
            static fn (?string $method): bool => $method !== null && $method !== '',
        ));

        return implode(PHP_EOL . PHP_EOL, $methods);
    }

    private function relativePath(string $path): string
    {
        $basePath = rtrim(base_path(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        return str_starts_with($path, $basePath)
            ? substr($path, strlen($basePath))
            : $path;
    }

    private function textInputExpression(string $field, bool $required = false): string
    {
        $expression = "TextInput::make('$field')->label('" . str($field)->headline()->toString() . "')";

        if ($required) {
            $expression .= '->required()';
        }

        if (str_contains(strtolower($field), 'email')) {
            $expression .= '->email()';
        }

        return $expression;
    }
}
