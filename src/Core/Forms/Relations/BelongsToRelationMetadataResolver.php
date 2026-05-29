<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Core\Forms\Relations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo as EloquentBelongsTo;
use Illuminate\Support\Arr;
use Pepperfm\Flashboard\Contracts\Resources\Resource;
use Pepperfm\Flashboard\Core\Forms\Fields\BelongsTo;
use Pepperfm\Flashboard\Core\Registry\ResourceRegistry;

final readonly class BelongsToRelationMetadataResolver
{
    public function __construct(
        private ?ResourceRegistry $resourceRegistry = null,
    ) {
    }

    /**
     * @param class-string<Resource> $resourceClass
     * @param array<string, mixed> $field
     */
    public function resolve(string $resourceClass, array $field): BelongsToRelationMetadata
    {
        $fieldKey = $this->requiredString($field, 'key', 'BelongsTo field key is required.');
        $relationship = $this->stringValue($field, BelongsTo::ATTRIBUTE_RELATIONSHIP)
            ?? $this->inferRelationshipName($fieldKey);
        $model = $this->newResourceModel($resourceClass);
        $relation = $this->resolveRelation($model, $relationship, $field);
        $relatedModel = $this->relatedModel($relation, $field);
        $relatedModelInstance = $this->newModel($relatedModel);
        $foreignKey = $this->stringValue($field, BelongsTo::ATTRIBUTE_FOREIGN_KEY)
            ?? $relation?->getForeignKeyName()
            ?? $fieldKey;
        $ownerKey = $this->stringValue($field, BelongsTo::ATTRIBUTE_OWNER_KEY)
            ?? $relation?->getOwnerKeyName()
            ?? $relatedModelInstance->getKeyName();
        $recordKeyName = $this->stringValue($field, BelongsTo::ATTRIBUTE_RECORD_KEY_NAME)
            ?? $relatedModelInstance->getKeyName();
        $relatedResource = $this->relatedResource($field, $relatedModel);
        $titleAttribute = $this->stringValue($field, BelongsTo::ATTRIBUTE_TITLE_ATTRIBUTE) ?? 'name';
        $searchColumns = $this->searchColumns($field, $titleAttribute);
        $optionsPerPage = $this->positiveIntegerValue($field, BelongsTo::ATTRIBUTE_OPTIONS_PER_PAGE)
            ?? BelongsTo::DEFAULT_OPTIONS_PER_PAGE;
        $allowModelFallback = (bool) Arr::get($field, BelongsTo::ATTRIBUTE_ALLOW_MODEL_FALLBACK, false);

        return new BelongsToRelationMetadata(
            fieldKey: $fieldKey,
            relationship: $relationship,
            relatedModel: $relatedModel,
            relatedResource: $relatedResource,
            foreignKey: $foreignKey,
            ownerKey: $ownerKey,
            recordKeyName: $recordKeyName,
            relatedTable: $relatedModelInstance->getTable(),
            titleAttribute: $titleAttribute,
            searchColumns: $searchColumns,
            optionsPerPage: $optionsPerPage,
            allowModelFallback: $allowModelFallback,
        );
    }

    /**
     * @param class-string<Resource> $resourceClass
     */
    private function newResourceModel(string $resourceClass): Model
    {
        $modelClass = $resourceClass::model();

        return $this->newModel($modelClass);
    }

    /**
     * @param class-string<Model> $modelClass
     */
    private function newModel(string $modelClass): Model
    {
        if (!is_a($modelClass, Model::class, true)) {
            throw new \InvalidArgumentException(sprintf(
                'Class [%s] must extend [%s].',
                $modelClass,
                Model::class,
            ));
        }

        return new $modelClass();
    }

    /**
     * @param array<string, mixed> $field
     */
    private function resolveRelation(Model $model, string $relationship, array $field): ?EloquentBelongsTo
    {
        if (!method_exists($model, $relationship)) {
            if ($this->stringValue($field, BelongsTo::ATTRIBUTE_MODEL) !== null) {
                return null;
            }

            throw new \InvalidArgumentException(sprintf(
                'BelongsTo relationship [%s] does not exist on model [%s].',
                $relationship,
                $model::class,
            ));
        }

        $relation = $model->{$relationship}();

        if (!$relation instanceof EloquentBelongsTo) {
            throw new \InvalidArgumentException(sprintf(
                'Relationship [%s] on model [%s] must be an instance of [%s].',
                $relationship,
                $model::class,
                EloquentBelongsTo::class,
            ));
        }

        return $relation;
    }

    /**
     * @param array<string, mixed> $field
     *
     * @return class-string<Model>
     */
    private function relatedModel(?EloquentBelongsTo $relation, array $field): string
    {
        $explicitModel = $this->stringValue($field, BelongsTo::ATTRIBUTE_MODEL);
        if ($explicitModel !== null) {
            /** @var class-string<Model> $explicitModel */
            return $explicitModel;
        }
        if ($relation === null) {
            throw new \InvalidArgumentException('BelongsTo field must define a related model or a valid relationship.');
        }

        return $relation->getRelated()::class;
    }

    /**
     * @param array<string, mixed> $field
     * @param class-string<Model> $relatedModel
     *
     * @return class-string<Resource>|null
     */
    private function relatedResource(array $field, string $relatedModel): ?string
    {
        $explicitResource = $this->stringValue($field, BelongsTo::ATTRIBUTE_RELATED_RESOURCE);
        if ($explicitResource !== null) {
            if (!is_a($explicitResource, Resource::class, true)) {
                throw new \InvalidArgumentException(sprintf(
                    'Related resource [%s] must extend [%s].',
                    $explicitResource,
                    Resource::class,
                ));
            }

            /** @var class-string<Resource> $explicitResource */
            return $explicitResource;
        }
        if ($this->resourceRegistry === null) {
            return null;
        }

        $matches = $this->resourceRegistry->resourcesForModel($relatedModel);
        if (count($matches) > 1) {
            throw new \InvalidArgumentException(sprintf(
                'Related resource for model [%s] is ambiguous; define BelongsTo::resource() explicitly.',
                $relatedModel,
            ));
        }

        return $matches[0] ?? null;
    }

    /**
     * @param array<string, mixed> $field
     *
     * @return list<string>
     */
    private function searchColumns(array $field, string $titleAttribute): array
    {
        $columns = Arr::get($field, BelongsTo::ATTRIBUTE_SEARCH_COLUMNS, true);
        if ($columns === false || $columns === []) {
            return [];
        }
        if ($columns === true || $columns === null) {
            return [$titleAttribute];
        }

        $columns = is_array($columns) ? $columns : [$columns];
        $normalized = [];

        foreach ($columns as $column) {
            if (!is_scalar($column) && !$column instanceof \Stringable) {
                continue;
            }

            $column = trim((string) $column);
            if ($column !== '') {
                $normalized[] = $column;
            }
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @param array<string, mixed> $values
     */
    private function requiredString(array $values, string $key, string $message): string
    {
        $value = $this->stringValue($values, $key);
        if ($value === null) {
            throw new \InvalidArgumentException($message);
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $values
     */
    private function stringValue(array $values, string $key): ?string
    {
        $value = Arr::get($values, $key);
        if (!is_scalar($value) && !$value instanceof \Stringable) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * @param array<string, mixed> $values
     */
    private function positiveIntegerValue(array $values, string $key): ?int
    {
        $value = Arr::get($values, $key);

        return is_int($value) && $value > 0 ? $value : null;
    }

    private function inferRelationshipName(string $key): string
    {
        $inferred = preg_replace('/_(?:id|uuid|ulid)$/', '', $key);

        return is_string($inferred) && $inferred !== '' ? $inferred : $key;
    }
}
