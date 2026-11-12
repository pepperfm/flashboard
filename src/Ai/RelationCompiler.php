<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Ai;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo as EloquentBelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany as EloquentBelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany as EloquentHasMany;
use Illuminate\Database\Eloquent\Relations\HasOne as EloquentHasOne;
use Illuminate\Database\Eloquent\Relations\Relation;
use Pepperfm\Flashboard\Core\Registry\ResourceRegistry;

final class RelationCompiler
{
    public function __construct(
        private readonly ResourceRegistry $registry,
    ) {
    }

    /**
     * @param list<mixed> $definitions
     * @return array{forms: list<string>, managers: list<string>, foreign_keys: array<string, true>}
     */
    public function compile(Model $model, array $definitions): array
    {
        if (count($definitions) > 15) {
            throw new \InvalidArgumentException('AI Harness supports at most 15 relations per Resource.');
        }

        $forms = [];
        $managers = [];
        $foreignKeys = [];
        $seen = [];

        foreach ($definitions as $definition) {
            if (!is_array($definition)) {
                throw new \InvalidArgumentException('Each relation must be an object.');
            }

            $name = $definition['name'] ?? null;
            $type = $definition['type'] ?? null;

            if (!is_string($name) || !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/D', $name)
                || isset($seen[$name]) || !is_string($type)
                || !in_array($type, ['belongs_to', 'belongs_to_many', 'has_one', 'has_many'], true)) {
                throw new \InvalidArgumentException('Relation name must be unique and relation type supported.');
            }

            $seen[$name] = true;

            if (!method_exists($model, $name)) {
                throw new \InvalidArgumentException("Eloquent relation [$name] does not exist on [{$model::class}].");
            }

            $method = new \ReflectionMethod($model, $name);
            if (!$method->isPublic() || $method->isStatic() || $method->getNumberOfRequiredParameters() !== 0) {
                throw new \InvalidArgumentException("Relation [$name] must be a public zero-argument instance method.");
            }

            // Only invoke Eloquent relationship methods in a trusted local application.
            $relation = $model->{$name}();
            $expected = match ($type) {
                'belongs_to' => EloquentBelongsTo::class,
                'belongs_to_many' => EloquentBelongsToMany::class,
                'has_one' => EloquentHasOne::class,
                'has_many' => EloquentHasMany::class,
            };

            if (!$relation instanceof $expected) {
                throw new \InvalidArgumentException("Relation [$name] must return [$expected].");
            }

            /** @var Relation $relation */
            $related = $relation->getRelated();
            $relatedClass = $related::class;
            $candidates = $this->registry->resourcesForModel($relatedClass);
            $explicit = $definition['resource'] ?? null;

            if ($explicit !== null) {
                if (!is_string($explicit) || !in_array($explicit, $candidates, true)) {
                    throw new \InvalidArgumentException("Related Resource [$explicit] is not registered for [$relatedClass].");
                }
                $resourceClass = $explicit;
            } else {
                if (count($candidates) !== 1) {
                    throw new \InvalidArgumentException("Relation [$name] needs exactly one registered Resource for [$relatedClass]. Generate/register it first, or pass resource explicitly.");
                }
                $resourceClass = $candidates[0];
            }

            $relatedColumns = $related->getConnection()->getSchemaBuilder()->getColumnListing($related->getTable());
            $title = $definition['title_attribute']
                ?? (in_array('name', $relatedColumns, true) ? 'name'
                    : (in_array('title', $relatedColumns, true) ? 'title' : $related->getKeyName()));

            if (!is_string($title) || !in_array($title, $relatedColumns, true)
                || in_array($title, $related->getHidden(), true)
                || preg_match('/password|secret|token|private_key/i', $title)) {
                throw new \InvalidArgumentException("Relation [$name] has invalid or sensitive title_attribute.");
            }

            $label = $definition['label'] ?? str($name)->headline()->value();
            if (!is_string($label) || strlen($label) > 100) {
                throw new \InvalidArgumentException('Relation label must be a string up to 100 bytes.');
            }

            $searchColumns = $definition['search_columns'] ?? [$title];
            if (!is_array($searchColumns) || !array_is_list($searchColumns) || count($searchColumns) > 8) {
                throw new \InvalidArgumentException('search_columns must be a list of up to eight related columns.');
            }
            foreach ($searchColumns as $column) {
                if (!is_string($column) || !in_array($column, $relatedColumns, true)
                    || in_array($column, $related->getHidden(), true)
                    || preg_match('/password|secret|token|private_key/i', $column)) {
                    throw new \InvalidArgumentException("Invalid search column for relation [$name].");
                }
            }

            $resourcePhp = '\\' . ltrim($resourceClass, '\\') . '::class';
            $labelPhp = var_export($label, true);
            $namePhp = var_export($name, true);
            $searchPhp = var_export($searchColumns, true);
            $titlePhp = var_export($title, true);
            $suffix = "->resource($resourcePhp)->titleAttribute($titlePhp)->searchable($searchPhp)";

            if ($relation instanceof EloquentBelongsTo) {
                $foreignKey = $relation->getForeignKeyName();
                $parentColumns = $model->getConnection()->getSchemaBuilder()->getColumnListing($model->getTable());
                if (!in_array($foreignKey, $parentColumns, true)
                    || !$model->isFillable($foreignKey)
                    || in_array($foreignKey, $model->getHidden(), true)
                    || isset($foreignKeys[$foreignKey])) {
                    throw new \InvalidArgumentException("BelongsTo foreign key [$foreignKey] is unavailable, not fillable, or already used.");
                }

                $foreignKeys[$foreignKey] = true;
                $forms[] = "                BelongsTo::make(" . var_export($foreignKey, true)
                    . ", $labelPhp)->relationship($namePhp)$suffix,";
                continue;
            }

            if ($relation instanceof EloquentBelongsToMany) {
                $forms[] = "                BelongsToMany::make($namePhp, $labelPhp)$suffix,";
                continue;
            }

            $showOnEdit = $definition['show_on_edit'] ?? false;
            $readOnly = $definition['read_only'] ?? true;
            if (!is_bool($showOnEdit) || !is_bool($readOnly)) {
                throw new \InvalidArgumentException('show_on_edit and read_only must be boolean.');
            }

            $managerClass = $relation instanceof EloquentHasOne ? 'HasOne' : 'HasMany';
            $managers[] = "            $managerClass::make($namePhp, $labelPhp)$suffix"
                . ($readOnly ? '->readOnly()' : '')
                . ($showOnEdit ? '->showOnEdit()' : '')
                . ',';
        }

        return ['forms' => $forms, 'managers' => $managers, 'foreign_keys' => $foreignKeys];
    }
}
