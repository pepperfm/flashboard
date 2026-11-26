<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Ai;

use Illuminate\Filesystem\Filesystem;
use Pepperfm\Flashboard\Ai\Generation\FieldCompiler;
use Pepperfm\Flashboard\Ai\Generation\MetricCompiler;
use Pepperfm\Flashboard\Ai\Generation\ResourceFileStore;
use Pepperfm\Flashboard\Core\Registry\ResourceRegistry;

final readonly class ResourceManager
{
    private ResourceFileStore $store;

    public function __construct(
        private ModelCatalog $catalog,
        Filesystem $files,
        private ?string $outputDirectory = null,
    ) {
        $this->store = new ResourceFileStore($files);
    }

    /**
     * @param array<string, mixed> $spec
     * @return array<string, mixed>
     */
    public function preview(array $spec): array
    {
        [$path, $source] = $this->compile($spec);
        $current = $this->store->read($path);
        $exists = $current !== null;
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
        $this->store->create($path, $source);

        // The stdio server is long-lived; register fresh Resources immediately so
        // subsequent MCP calls can reference them as related Resources.
        $resourceClass = 'App\\Flashboard\\Ai\\' . $class;
        if (class_exists($resourceClass)) {
            app(ResourceRegistry::class)->register($resourceClass);
        }

        return ['path' => $path, 'resource' => $resourceClass];
    }

    /**
     * @param array<string, mixed> $spec
     * @return array{path: string, resource: string}
     */
    public function update(array $spec, string $expectedSha, string $proposalHash): array
    {
        [$path, $source, $class] = $this->compile($spec);
        $this->assertProposal($source, $proposalHash);
        $this->store->update($path, $source, $expectedSha, $this->isUnmodifiedGeneratedFile(...));

        return ['path' => $path, 'resource' => 'App\\Flashboard\\Ai\\' . $class];
    }

    private function assertProposal(string $source, string $proposalHash): void
    {
        if (!preg_match('/^[a-f0-9]{64}$/D', $proposalHash)
            || !hash_equals(hash('sha256', $source), $proposalHash)) {
            throw new \InvalidArgumentException('Proposal hash does not match. Call preview_resource with this exact specification first.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function managedSpecification(string $resource): array
    {
        if (!preg_match('/^[A-Za-z]\w{0,63}$/D', $resource)) {
            throw new \InvalidArgumentException('Resource must be a simple class name.');
        }

        $class = str($resource)->studly()->value();
        if (!str_ends_with($class, 'Resource')) {
            $class .= 'Resource';
        }

        $root = $this->outputDirectory ?? app_path('Flashboard/Ai');
        $path = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $class . '.php';
        $source = $this->store->read($path);
        if ($source === null) {
            throw new \RuntimeException('Resource is not an AI-generated file.');
        }

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
        $columns = $model->getConnection()->getSchemaBuilder()->getColumns($model->getTable());
        $available = [];
        foreach ($columns as $column) {
            $available[$column['name']] = strtolower($column['type_name'] ?? $column['type']);
        }
        $relations = $spec['relations'] ?? [];
        if (!is_array($relations) || !array_is_list($relations)) {
            throw new \InvalidArgumentException('relations must be a list.');
        }
        $compiledRelations = app(RelationCompiler::class)->compile($model, $relations);
        $readOnly = $spec['read_only'] ?? false;
        if (!is_bool($readOnly)) {
            throw new \InvalidArgumentException('read_only must be a boolean.');
        }

        if ($readOnly && $compiledRelations['forms'] !== []) {
            throw new \InvalidArgumentException('Read-only resources cannot declare editable relation fields.');
        }

        if ($readOnly) {
            foreach ($relations as $relation) {
                if (($relation['read_only'] ?? true) !== true || ($relation['show_on_edit'] ?? false) !== false) {
                    throw new \InvalidArgumentException('Read-only resources require read-only relation managers.');
                }
            }
        }

        $metrics = $spec['metrics'] ?? [];
        if (!is_array($metrics) || !array_is_list($metrics)) {
            throw new \InvalidArgumentException('metrics must be a list.');
        }
        $metricLines = new MetricCompiler()->compile($model, $metrics, $available);
        $metricsMethod = $metricLines === [] ? '' : "\n    public static function metrics(): array\n    {\n        return [\n"
            . implode("\n", $metricLines) . "\n        ];\n    }\n";

        $compiledFields = new FieldCompiler()->compile($model, $fields, $available, $compiledRelations['foreign_keys'], $readOnly);
        $table = $compiledFields['table'];
        $form = $compiledFields['form'];
        $detail = $compiledFields['detail'];
        $filters = $compiledFields['filters'];
        $rules = $compiledFields['rules'];

        $form = [...$form, ...$compiledRelations['forms']];
        if ($table === [] || (!$readOnly && $form === [])) {
            throw new \InvalidArgumentException('At least one table column and, for writable resources, one editable form field are required.');
        }

        $delete = $spec['allow_delete'] ?? false;
        if (!is_bool($delete) || ($readOnly && $delete)) {
            throw new \InvalidArgumentException('allow_delete must be a boolean and cannot be enabled on read-only resources.');
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
        if ($readOnly) {
            $manifest['read_only'] = true;
        }
        if ($metrics !== []) {
            $manifest['metrics'] = $metrics;
        }
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
            . ($metricLines === [] ? '' : "use Pepperfm\\Flashboard\\Core\\Metrics\\Metric;\n")
            . "use Pepperfm\\Flashboard\\Core\\Detail\\Entries\\TextEntry;\n\n"
            . "final class $class extends Resource\n{\n"
            . "    public static function model(): string\n    {\n        return \\" . ltrim($modelClass, '\\') . "::class;\n    }\n\n"
            . "    public static function table(TableContract \$table): TableContract\n    {\n"
            . "        return \$table->columns([\n" . implode("\n", $table) . "\n        ])"
            . ($filters === [] ? '' : "->filters([\n" . implode("\n", $filters) . "\n        ])")
            . ";\n    }\n\n"
            . ($readOnly ? "    public static function isReadOnly(): bool\n    {\n        return true;\n    }\n"
                : "    public static function form(FormContract \$form): FormContract\n    {\n"
            . "        return \$form->schema([\n" . implode("\n", $form) . "\n        ])->rules([\n"
            . implode("\n", $rules) . "\n        ]);\n    }\n")
            . $detailMethod
            . $relationsMethod
            . $metricsMethod
            . ($readOnly ? '' : "\n    public static function actions(): array\n    {\n        return [\n            EditAction::make(),"
            . ($delete ? "\n            DeleteAction::make()," : '')
            . "\n        ];\n    }\n") . "}\n";

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
