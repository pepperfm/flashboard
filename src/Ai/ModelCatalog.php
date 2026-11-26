<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Ai;

use Illuminate\Database\Eloquent\Model;

use Pepperfm\Flashboard\Ai\Generation\EnumOptions;

final readonly class ModelCatalog
{
    public function __construct(
        private ?string $modelsDirectory = null,
        private string $modelNamespace = 'App\\Models\\',
    ) {
    }

    /**
     * @return list<array{class: string, table: string}>
     */
    public function all(): array
    {
        $directory = $this->modelsDirectory ?? app_path('Models');
        if (!is_dir($directory)) {
            return [];
        }

        $models = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $relative = substr($file->getPathname(), strlen(rtrim($directory, DIRECTORY_SEPARATOR)) + 1);
            $relative = str_replace([DIRECTORY_SEPARATOR, '.php'], ['\\', ''], $relative);
            $class = $this->modelNamespace . $relative;

            if (!class_exists($class) || !is_subclass_of($class, Model::class)) {
                continue;
            }

            $reflection = new \ReflectionClass($class);
            if ($reflection->isAbstract()) {
                continue;
            }

            /** @var Model $model */
            $model = $reflection->newInstance();
            $models[] = ['class' => $class, 'table' => $model->getTable()];
        }

        usort($models, static fn (array $a, array $b): int => strcmp($a['class'], $b['class']));

        return $models;
    }

    public function model(string $class): Model
    {
        if (
            !str_starts_with($class, $this->modelNamespace)
            || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\\\\[A-Za-z_][A-Za-z0-9_]*)*$/D', $class)
            || !class_exists($class)
            || !is_subclass_of($class, Model::class)
            || !(new \ReflectionClass($class))->isInstantiable()
        ) {
            throw new \InvalidArgumentException('Model must be a concrete Eloquent class inside the configured models namespace.');
        }

        return new $class();
    }

    /**
     * @return array{model: string, table: string, primary_key: string, fields: list<array<string, mixed>>, relations: list<array{name: string, type: string}>}
     */
    public function inspect(string $class): array
    {
        $model = $this->model($class);
        $columns = $model->getConnection()->getSchemaBuilder()->getColumns($model->getTable());
        $fields = [];

        foreach ($columns as $column) {
            $name = (string) $column['name'];
            $field = [
                'name' => $name,
                'type' => (string) ($column['type_name'] ?? $column['type'] ?? 'unknown'),
                'nullable' => (bool) ($column['nullable'] ?? true),
                'primary' => $name === $model->getKeyName(),
                'fillable' => $model->isFillable($name),
                'hidden' => in_array($name, $model->getHidden(), true),
            ];
            if (!$field['hidden'] && !preg_match('/password|secret|token|api_key|private_key/i', $name)) {
                $enum = new EnumOptions()->inspect($model, $name);
                if ($enum !== null) {
                    $field['enum'] = [
                        'class' => $enum['class'],
                        'backing' => $enum['backing'],
                        'cases' => array_map(
                            static fn(int|string $value, string $label): array => [
                                'value' => $enum['backing'] === 'int' ? (int) $value : (string) $value,
                                'label' => $label,
                            ],
                            array_keys($enum['options']),
                            array_values($enum['options']),
                        ),
                    ];
                }
            }

            $fields[] = $field;
        }

        $relations = [];

        foreach ((new \ReflectionClass($model))->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->isStatic() || $method->getNumberOfRequiredParameters() > 0) {
                continue;
            }

            $type = $method->getReturnType();
            if (!$type instanceof \ReflectionNamedType || $type->isBuiltin()) {
                continue;
            }

            $typeName = $type->getName();
            if (is_subclass_of($typeName, \Illuminate\Database\Eloquent\Relations\Relation::class)) {
                $relations[] = [
                    'name' => $method->getName(),
                    'type' => class_basename($typeName),
                ];
            }
        }

        return [
            'model' => $class,
            'table' => $model->getTable(),
            'primary_key' => $model->getKeyName(),
            'fields' => $fields,
            'relations' => $relations,
        ];
    }
}
