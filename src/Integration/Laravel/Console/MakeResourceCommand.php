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

#[Signature('flashboard:make-resource {name? : Resource class name} {model? : Eloquent model FQCN} {--force : Overwrite the target file if it exists}')]
final class MakeResourceCommand extends \Illuminate\Console\Command
{
    protected $description = 'Generate a Flashboard resource with prompt-driven defaults.';

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
        $titleField = (string) text(
            label: 'Primary display field',
            default: 'name',
            required: true,
        );
        $secondaryField = trim((string) text(
            label: 'Secondary field (optional)',
            default: 'email',
            required: false,
        ));
        $navigationGroup = trim((string) text(
            label: 'Navigation group (optional)',
            default: '',
            required: false,
        ));
        $includeForm = confirm('Include create and edit form?', default: true);
        $includeDetail = confirm('Include detail screen?', default: true);
        $includeActions = confirm('Include example action?', default: false);

        $targetDirectory = app_path('Flashboard');
        $targetPath = $targetDirectory . '/' . $className . '.php';

        if ($files->exists($targetPath) && !$this->option('force')) {
            warning("File already exists: {$targetPath}");

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

        info("Flashboard resource created: {$targetPath}");
        note('Register it inline via Flashboard::configure()->resource(' . "App\\\\Flashboard\\\\{$className}::class" . ');');

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
            'Pepperfm\\Flashboard\\Contracts\\Resources\\Resource',
            'Pepperfm\\Flashboard\\Contracts\\Tables\\TableContract',
        ];

        if ($includeForm) {
            $imports[] = 'Pepperfm\\Flashboard\\Contracts\\Forms\\FormContract';
        }

        if ($includeDetail) {
            $imports[] = 'Pepperfm\\Flashboard\\Contracts\\Detail\\DetailContract';
        }

        if ($includeActions) {
            $imports[] = 'Pepperfm\\Flashboard\\Core\\Actions\\Builders\\Action';
        }

        sort($imports);

        return str_replace(
            [
                '{{ namespace }}',
                '{{ model }}',
                '{{ imports }}',
                '{{ class }}',
                '{{ model_basename }}',
                '{{ navigation_group_method }}',
                '{{ title_field }}',
                '{{ title_label }}',
                '{{ secondary_table_column }}',
                '{{ form_method }}',
                '{{ detail_method }}',
                '{{ actions_method }}',
            ],
            [
                'App\\Flashboard',
                $modelClass,
                implode(PHP_EOL, array_map(static fn (string $import): string => 'use ' . $import . ';', $imports)),
                $className,
                class_basename($modelClass),
                $this->navigationGroupMethod($navigationGroup),
                $titleField,
                str($titleField)->headline()->toString(),
                $this->secondaryTableColumn($secondaryField),
                $this->formMethod($includeForm, $titleField, $secondaryField),
                $this->detailMethod($includeDetail, $titleField, $secondaryField),
                $this->actionsMethod($includeActions, $className),
            ],
            $stub,
        );
    }

    private function navigationGroupMethod(string $navigationGroup): string
    {
        if ($navigationGroup === '') {
            return PHP_EOL;
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

        return "            ['key' => '{$secondaryField}', 'label' => '" . str($secondaryField)->headline()->toString() . "', 'searchable' => true]," . PHP_EOL;
    }

    private function formMethod(bool $includeForm, string $titleField, string $secondaryField): string
    {
        if (!$includeForm) {
            return PHP_EOL;
        }

        $fieldRows = [
            "                ['key' => '{$titleField}', 'label' => '" . str($titleField)->headline()->toString() . "'],",
        ];
        $rules = [
            "                '{$titleField}' => ['required', 'string'],",
        ];

        if ($secondaryField !== '') {
            $label = str($secondaryField)->headline()->toString();
            $fieldRows[] = "                ['key' => '{$secondaryField}', 'label' => '{$label}'],";
            $rules[] = "                '{$secondaryField}' => ['nullable', 'string'],";
        }

        $fields = implode(PHP_EOL, $fieldRows);
        $formRules = implode(PHP_EOL, $rules);

        return <<<PHP

    public static function form(FormContract \$form): FormContract
    {
        return \$form
            ->fields([
{$fields}
            ])
            ->rules([
{$formRules}
            ]);
    }

PHP;
    }

    private function detailMethod(bool $includeDetail, string $titleField, string $secondaryField): string
    {
        if (!$includeDetail) {
            return PHP_EOL;
        }

        $entries = [
            "                ['key' => 'id', 'label' => 'ID'],",
            "                ['key' => '{$titleField}', 'label' => '" . str($titleField)->headline()->toString() . "'],",
        ];

        if ($secondaryField !== '') {
            $entries[] = "                ['key' => '{$secondaryField}', 'label' => '" . str($secondaryField)->headline()->toString() . "'],";
        }

        return <<<PHP

    public static function detail(DetailContract \$detail): DetailContract
    {
        return \$detail->entries([
{$this->indentLines($entries)}
        ]);
    }

PHP;
    }

    private function actionsMethod(bool $includeActions, string $className): string
    {
        if (!$includeActions) {
            return PHP_EOL;
        }

        $resourceLabel = str(str_replace('Resource', '', $className))->headline()->toString();

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

    /**
     * @param list<string> $lines
     */
    private function indentLines(array $lines): string
    {
        return implode(PHP_EOL, $lines) . PHP_EOL;
    }
}
