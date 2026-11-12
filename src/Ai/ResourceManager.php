<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Ai;

use Illuminate\Filesystem\Filesystem;
use Pepperfm\Flashboard\Core\Registry\ResourceRegistry;

final class ResourceManager
{
    public function __construct(
        private readonly ModelCatalog $catalog,
        private readonly Filesystem $files,
        private readonly ?string $outputDirectory = null,
    ) {
    }

    /**
     * @param array<string, mixed> $spec
     * @return array<string, mixed>
     */
    public function preview(array $spec): array
    {
        [$path, $source] = $this->compile($spec);
        $exists = $this->files->exists($path);
        $current = $exists ? $this->files->get($path) : null;
        $writable = !$exists || $this->isUnmodifiedGeneratedFile($current);

        return [
            'path' => $path,
            'operation' => $exists ? 'update' : 'create',
            'writable' => $writable,
            'reason' => $writable ? null : 'Existing file is not an unmodified Flashboard AI resource.',
            'expected_sha' => $current === null ? null : hash('sha256', $current),
            'proposal_hash' => hash('sha256', $source),
            'diff' => $this->diff($current, $source),
        ];
    }

    /**
     * @param array<string, mixed> $spec
     * @return array{path: string, resource: string}
     */
    public function create(array $spec, string $proposalHash): array
    {
        [$path, $source, $class] = $this->compile($spec);
        $this->assertProposal($source, $proposalHash);
        $this->assertLocalPath($path);

        if ($this->files->exists($path)) {
            throw new \RuntimeException('Resource already exists; preview an update instead.');
        }

        $this->files->ensureDirectoryExists(dirname($path));
        // Exclusive creation prevents silently overwriting a file created after preview.
        $handle = @fopen($path, 'x');
        if ($handle === false) {
            throw new \RuntimeException('Unable to create resource exclusively.');
        }

        try {
            $length = strlen($source);
            if (fwrite($handle, $source) !== $length) {
                throw new \RuntimeException('Unable to write complete resource source.');
            }
        } finally {
            fclose($handle);
        }

        return ['path' => $path, 'resource' => 'App\\Flashboard\\Ai\\' . $class];
    }

    /**
     * @param array<string, mixed> $spec
     * @return array{path: string, resource: string}
     */
    public function update(array $spec, string $expectedSha, string $proposalHash): array
    {
        [$path, $source, $class] = $this->compile($spec);
        $this->assertProposal($source, $proposalHash);
        $this->assertLocalPath($path);

        if (!$this->files->isFile($path) || is_link($path)) {
            throw new \RuntimeException('Resource does not exist as a regular file.');
        }

        // Protect both the agent's preview and any manual edits to a generated class.
        $current = $this->files->get($path);
        if (!hash_equals(hash('sha256', $current), $expectedSha) || !$this->isUnmodifiedGeneratedFile($current)) {
            throw new \RuntimeException('Resource changed since preview or contains manual edits. Refusing to overwrite it.');
        }

        if ($current !== $source) {
            $this->files->replace($path, $source);
        }

        return ['path' => $path, 'resource' => 'App\\Flashboard\\Ai\\' . $class];
    }

    private function assertProposal(string $source, string $proposalHash): void
    {
        if (!preg_match('/^[a-f0-9]{64}$/D', $proposalHash)
            || !hash_equals(hash('sha256', $source), $proposalHash)) {
            throw new \InvalidArgumentException('Proposal hash does not match. Call preview_resource with this exact specification first.');
        }
    }

    private function assertLocalPath(string $path): void
    {
        $root = $this->outputDirectory ?? app_path('Flashboard/Ai');
        if (is_link($root) || is_link($path) || is_link(dirname($root))) {
            throw new \RuntimeException('Symlinked resource directories and files are not supported.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function managedSpecification(string $resource): array
    {
        if (!preg_match('/^[A-Za-z][A-Za-z0-9_]{0,63}$/D', $resource)) {
            throw new \InvalidArgumentException('Resource must be a simple class name.');
        }

        $class = str($resource)->studly()->value();
        if (!str_ends_with($class, 'Resource')) {
            $class .= 'Resource';
        }

        $root = $this->outputDirectory ?? app_path('Flashboard/Ai');
        $path = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $class . '.php';
        $this->assertLocalPath($path);

        if (!$this->files->isFile($path) || is_link($path)) {
            throw new \RuntimeException('Resource is not an AI-generated file.');
        }

        $source = $this->files->get($path);
        if (!$this->isUnmodifiedGeneratedFile($source)
            || !preg_match('/^\/\/ @flashboard-ai-spec: ([A-Za-z0-9+\/=]+)$/m', $source, $match)) {
            throw new \RuntimeException('Resource is handwritten, manually modified, or predates editable AI specs. Use full-spec preview/update instead.');
        }

        $decoded = base64_decode($match[1], true);
        if ($decoded === false) {
            throw new \RuntimeException('Invalid AI Resource specification encoding.');
        }

        $spec = json_decode($decoded, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($spec) || !is_string($spec['resource'] ?? null)
            || str($spec['resource'])->studly()->replaceEnd('Resource', '')->value() !== str($resource)->studly()->replaceEnd('Resource', '')->value()) {
            throw new \RuntimeException('AI Resource manifest does not match the requested resource.');
        }

        return $spec;
    }

    private function isUnmodifiedGeneratedFile(?string $source): bool
    {
        if ($source === null
            || !str_contains($source, '// @flashboard-ai-managed: 1')
            || !preg_match('/^\/\/ @flashboard-ai-fingerprint: ([a-f0-9]{64})$/m', $source, $match)) {
            return false;
        }

        $unsigned = preg_replace('/^\/\/ @flashboard-ai-fingerprint: [a-f0-9]{64}\n/m', '', $source, 1);

        return is_string($unsigned) && hash_equals($match[1], hash('sha256', $unsigned));
    }

    /**
     * @param array<string, mixed> $spec
     * @return array{string, string, string}
     */
    private function compile(array $spec): array
    {
        $resource = $spec['resource'] ?? null;
        $modelClass = $spec['model'] ?? null;
        $fields = $spec['fields'] ?? null;

        if (
            !is_string($resource)
            || !preg_match('/^[A-Za-z][A-Za-z0-9_]{0,63}$/D', $resource)
            || !is_string($modelClass)
            || !is_array($fields)
            || !array_is_list($fields)
            || count($fields) < 1
            || count($fields) > 30
        ) {
            throw new \InvalidArgumentException('Specify a simple resource name, an Eloquent model, and 1-30 field definitions.');
        }

        $class = str($resource)->studly()->value();
        if (!str_ends_with($class, 'Resource')) {
            $class .= 'Resource';
        }

        // A second resource with the same route key would create ambiguous URLs.
        $key = str($class)->replaceLast('Resource', '')->snake()->value();
        $registered = app(ResourceRegistry::class)->forKey($key);
        if ($registered !== null && $registered !== 'App\\Flashboard\\Ai\\' . $class) {
            throw new \InvalidArgumentException("Another Flashboard resource already uses key [$key].");
        }

        $model = $this->catalog->model($modelClass);
        $columns = $model->getConnection()->getSchemaBuilder()->getColumnListing($model->getTable());
        $available = array_fill_keys($columns, true);
        $relations = $spec['relations'] ?? [];
        if (!is_array($relations) || !array_is_list($relations)) {
            throw new \InvalidArgumentException('relations must be a list.');
        }
        $compiledRelations = app(RelationCompiler::class)->compile($model, $relations);
        $hidden = $model->getHidden();
        $primary = $model->getKeyName();

        $table = [];
        $form = [];
        $detail = [];
        $filters = [];
        $rules = [];
        $seen = [];

        foreach ($fields as $item) {
            if (!is_array($item)) {
                throw new \InvalidArgumentException('Each field must be an object.');
            }

            $name = $item['name'] ?? null;
            $type = $item['type'] ?? 'text';
            if (!is_string($name) || !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/D', $name)
                || !isset($available[$name]) || isset($seen[$name])
                || !is_string($type) || !in_array($type, ['text', 'textarea', 'number', 'date', 'select', 'checkbox', 'toggle'], true)) {
                throw new \InvalidArgumentException('Field names must be unique, valid database columns with supported types.');
            }

            $seen[$name] = true;
            if (in_array($name, $hidden, true)
                || preg_match('/(?:password|secret|token|api_key|private_key|remember_token)/i', $name)) {
                throw new \InvalidArgumentException("Sensitive field [$name] cannot be generated automatically.");
            }

            $label = $item['label'] ?? str($name)->headline()->value();
            if (!is_string($label) || strlen($label) > 100) {
                throw new \InvalidArgumentException('Field labels must be strings up to 100 bytes.');
            }

            $labelPhp = var_export($label, true);
            $namePhp = var_export($name, true);
            $showTable = $item['table'] ?? true;
            $showForm = $item['form'] ?? ($name !== $primary && $model->isFillable($name) && !in_array($name, [$model->getCreatedAtColumn(), $model->getUpdatedAtColumn()], true));
            $showDetail = $item['detail'] ?? true;
            $required = $item['required'] ?? false;
            $searchable = $item['searchable'] ?? false;
            $sortable = $item['sortable'] ?? false;
            $filter = $item['filter'] ?? false;

            foreach (compact('showTable', 'showForm', 'showDetail', 'required', 'searchable', 'sortable', 'filter') as $flag) {
                if (!is_bool($flag)) {
                    throw new \InvalidArgumentException('Field visibility and behavior flags must be booleans.');
                }
            }

            if ($showForm && ($name === $primary || !$model->isFillable($name))) {
                throw new \InvalidArgumentException("Field [$name] is not fillable or is the primary key.");
            }

            $options = [];
            if ($type === 'select') {
                $options = $item['options'] ?? null;
                if (!is_array($options) || array_is_list($options) || count($options) === 0 || count($options) > 50) {
                    throw new \InvalidArgumentException("Select field [$name] needs an options map of up to 50 entries.");
                }
                foreach ($options as $value => $optionLabel) {
                    if ((!is_string($value) && !is_int($value)) || !is_string($optionLabel) || strlen($optionLabel) > 100) {
                        throw new \InvalidArgumentException('Select options require scalar keys and string labels.');
                    }
                }
            }

            $optionsPhp = '[' . implode(', ', array_map(
                static fn (string|int $key, string $value): string => var_export($key, true) . ' => ' . var_export($value, true),
                array_keys($options),
                array_values($options),
            )) . ']';

            if ($showTable) {
                $columnType = $type === 'select' ? 'BadgeColumn' : 'TextColumn';
                $line = "                $columnType::make($namePhp, $labelPhp)";
                if ($searchable && !in_array($type, ['checkbox', 'toggle'], true)) {
                    $line .= '->searchable()';
                }
                if ($sortable) {
                    $line .= '->sortable()';
                }
                $table[] = $line . ',';
            }

            if ($showForm && !isset($compiledRelations['foreign_keys'][$name])) {
                $fieldType = match ($type) {
                    'textarea' => 'Textarea',
                    'number' => 'NumberInput',
                    'date' => 'DateInput',
                    'select' => 'Select',
                    'checkbox' => 'Checkbox',
                    'toggle' => 'Toggle',
                    default => 'TextInput',
                };
                $line = "                $fieldType::make($namePhp, $labelPhp)";
                if ($type === 'select') {
                    $line .= "->options($optionsPhp)";
                }
                if ($required) {
                    $line .= '->required()';
                }
                $form[] = $line . ',';
                $validation = $required ? 'required' : 'nullable';
                $validationType = match ($type) {
                    'date' => 'date_format:Y-m-d',
                    'number' => 'numeric',
                    'checkbox', 'toggle' => 'boolean',
                    default => 'string',
                };
                $rules[] = "                $namePhp => ['$validation', '$validationType'],";
            }

            if ($showDetail) {
                $detail[] = "                TextEntry::make($namePhp, $labelPhp),";
            }

            if ($filter && $showTable) {
                $filters[] = $type === 'select'
                    ? "                SelectFilter::make($namePhp, $labelPhp)->options($optionsPhp),"
                    : "                InputFilter::make($namePhp, $labelPhp),";
            }
        }

        $form = [...$form, ...$compiledRelations['forms']];
        if ($table === [] || $form === []) {
            throw new \InvalidArgumentException('At least one table column and one editable form field are required.');
        }

        $delete = $spec['allow_delete'] ?? false;
        if (!is_bool($delete)) {
            throw new \InvalidArgumentException('allow_delete must be a boolean.');
        }

        $detailMethod = $detail === [] ? '' : "\n    public static function infolist(DetailContract \$detail): DetailContract\n    {\n        return \$detail->entries([\n"
            . implode("\n", $detail)
            . "\n        ]);\n    }\n";

        $manifest = [
            'resource' => $resource,
            'model' => $modelClass,
            'fields' => $fields,
            'relations' => $relations,
            'allow_delete' => $delete,
        ];
        $manifestEncoded = base64_encode(json_encode($manifest, JSON_THROW_ON_ERROR));
        $relationsMethod = $compiledRelations['managers'] === [] ? ''
            : "\n    public static function relations(): array\n    {\n        return [\n"
                . implode("\n", $compiledRelations['managers']) . "\n        ];\n    }\n";

        $source = "<?php\n\ndeclare(strict_types=1);\n\n"
            . "// @flashboard-ai-managed: 1\n"
            . "// @flashboard-ai-spec: $manifestEncoded\n"
            . "// Generated by Flashboard AI Harness. Manual changes disable automatic updates.\n\n"
            . "namespace App\\Flashboard\\Ai;\n\n"
            . "use Pepperfm\\Flashboard\\Contracts\\Resources\\Resource;\n"
            . "use Pepperfm\\Flashboard\\Contracts\\Tables\\TableContract;\n"
            . "use Pepperfm\\Flashboard\\Contracts\\Forms\\FormContract;\n"
            . "use Pepperfm\\Flashboard\\Contracts\\Detail\\DetailContract;\n"
            . "use Pepperfm\\Flashboard\\Core\\Tables\\Columns\\TextColumn;\n"
            . "use Pepperfm\\Flashboard\\Core\\Tables\\Columns\\BadgeColumn;\n"
            . "use Pepperfm\\Flashboard\\Core\\Tables\\Filters\\SelectFilter;\n"
            . "use Pepperfm\\Flashboard\\Core\\Tables\\Filters\\InputFilter;\n"
            . "use Pepperfm\\Flashboard\\Core\\Tables\\Actions\\EditAction;\n"
            . "use Pepperfm\\Flashboard\\Core\\Tables\\Actions\\DeleteAction;\n"
            . "use Pepperfm\\Flashboard\\Core\\Forms\\Fields\\TextInput;\n"
            . "use Pepperfm\\Flashboard\\Core\\Forms\\Fields\\Textarea;\n"
            . "use Pepperfm\\Flashboard\\Core\\Forms\\Fields\\NumberInput;\n"
            . "use Pepperfm\\Flashboard\\Core\\Forms\\Fields\\DateInput;\n"
            . "use Pepperfm\\Flashboard\\Core\\Forms\\Fields\\Select;\n"
            . "use Pepperfm\\Flashboard\\Core\\Forms\\Fields\\Checkbox;\n"
            . "use Pepperfm\\Flashboard\\Core\\Forms\\Fields\\Toggle;\n"
            . "use Pepperfm\\Flashboard\\Core\\Forms\\Fields\\BelongsTo;\n"
            . "use Pepperfm\\Flashboard\\Core\\Forms\\Fields\\BelongsToMany;\n"
            . "use Pepperfm\\Flashboard\\Core\\Relations\\HasOne;\n"
            . "use Pepperfm\\Flashboard\\Core\\Relations\\HasMany;\n"
            . "use Pepperfm\\Flashboard\\Core\\Detail\\Entries\\TextEntry;\n\n"
            . "final class $class extends Resource\n{\n"
            . "    public static function model(): string\n    {\n        return \\" . ltrim($modelClass, '\\') . "::class;\n    }\n\n"
            . "    public static function table(TableContract \$table): TableContract\n    {\n"
            . "        return \$table->columns([\n" . implode("\n", $table) . "\n        ])"
            . ($filters === [] ? '' : "->filters([\n" . implode("\n", $filters) . "\n        ])")
            . ";\n    }\n\n"
            . "    public static function form(FormContract \$form): FormContract\n    {\n"
            . "        return \$form->schema([\n" . implode("\n", $form) . "\n        ])->rules([\n"
            . implode("\n", $rules) . "\n        ]);\n    }\n"
            . $detailMethod
            . $relationsMethod
            . "\n    public static function actions(): array\n    {\n        return [\n            EditAction::make(),"
            . ($delete ? "\n            DeleteAction::make()," : '')
            . "\n        ];\n    }\n}\n";

        $unsigned = $source;
        $fingerprint = hash('sha256', $unsigned);
        $source = str_replace('// @flashboard-ai-managed: 1' . "\n", '// @flashboard-ai-managed: 1' . "\n"
            . '// @flashboard-ai-fingerprint: ' . $fingerprint . "\n", $unsigned);

        $root = $this->outputDirectory ?? app_path('Flashboard/Ai');

        return [rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $class . '.php', $source, $class];
    }

    private function diff(?string $old, string $new): string
    {
        // Fingerprints are machine-owned metadata, not a meaningful code change.
        $old = $old === null ? null : preg_replace('/^\/\/ @flashboard-ai-fingerprint: [a-f0-9]{64}\n/m', '', $old);
        $new = (string) preg_replace('/^\/\/ @flashboard-ai-fingerprint: [a-f0-9]{64}\n/m', '', $new);
        $before = $old === null ? [] : explode("\n", rtrim($old, "\n"));
        $after = explode("\n", rtrim($new, "\n"));
        $prefix = 0;

        while (isset($before[$prefix], $after[$prefix]) && $before[$prefix] === $after[$prefix]) {
            $prefix++;
        }

        $oldEnd = count($before);
        $newEnd = count($after);

        while ($oldEnd > $prefix && $newEnd > $prefix && $before[$oldEnd - 1] === $after[$newEnd - 1]) {
            $oldEnd--;
            $newEnd--;
        }

        $head = array_map(static fn (string $line): string => ' ' . $line, array_slice($before, max(0, $prefix - 2), min(2, $prefix)));
        $removed = array_map(static fn (string $line): string => '-' . $line, array_slice($before, $prefix, $oldEnd - $prefix));
        $added = array_map(static fn (string $line): string => '+' . $line, array_slice($after, $prefix, $newEnd - $prefix));
        $tail = array_map(static fn (string $line): string => ' ' . $line, array_slice($after, $newEnd, 2));

        return implode("\n", ['--- current', '+++ proposed', '@@', ...$head, ...$removed, ...$added, ...$tail]);
    }
}
