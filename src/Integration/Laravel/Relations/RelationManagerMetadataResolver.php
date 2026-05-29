<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Integration\Laravel\Relations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany as EloquentHasMany;
use Illuminate\Database\Eloquent\Relations\HasOne as EloquentHasOne;
use Illuminate\Support\Arr;
use Pepperfm\Flashboard\Contracts\Resources\Resource;
use Pepperfm\Flashboard\Core\Registry\ResourceRegistry;
use Pepperfm\Flashboard\Core\Relations\HasMany;
use Pepperfm\Flashboard\Core\Relations\HasOne;
use Pepperfm\Flashboard\Core\Relations\RelationManagerDefinition;

final readonly class RelationManagerMetadataResolver
{
    public function __construct(
        private ?ResourceRegistry $resourceRegistry = null,
    ) {
    }

    /**
     * @param class-string<Resource> $resourceClass
     * @param array<string, mixed> $definition
     */
    public function resolve(string $resourceClass, array $definition): RelationManagerMetadata
    {
        $key = $this->requiredString($definition, 'key', 'Relation manager key is required.');
        $relationship = $this->stringValue($definition, RelationManagerDefinition::ATTRIBUTE_RELATIONSHIP) ?? $key;
        $parentModel = $resourceClass::model();
        $parent = new $parentModel();
        $relation = $this->resolveRelation($parent, $relationship);
        $type = $this->relationType($definition, $relation);
        $relatedModel = $this->relatedModel($definition, $relation);
        $relatedResource = $this->relatedResource($definition, $relatedModel);
        $relatedModelInstance = new $relatedModel();
        $titleAttribute = $this->stringValue($definition, RelationManagerDefinition::ATTRIBUTE_TITLE_ATTRIBUTE) ?? 'name';
        $readOnly = (bool) Arr::get($definition, RelationManagerDefinition::ATTRIBUTE_READ_ONLY, false);

        return new RelationManagerMetadata(
            type: $type,
            key: $key,
            label: $this->stringValue($definition, 'label') ?? str($key)->headline()->value(),
            relationship: $relationship,
            parentModel: $parentModel,
            relatedModel: $relatedModel,
            relatedResource: $relatedResource,
            relatedTable: $relatedModelInstance->getTable(),
            localKey: $this->stringValue($definition, RelationManagerDefinition::ATTRIBUTE_LOCAL_KEY)
                ?? $relation->getLocalKeyName(),
            foreignKey: $this->stringValue($definition, RelationManagerDefinition::ATTRIBUTE_FOREIGN_KEY)
                ?? $relation->getForeignKeyName(),
            recordKeyName: $this->stringValue($definition, RelationManagerDefinition::ATTRIBUTE_RECORD_KEY_NAME)
                ?? $relatedModelInstance->getKeyName(),
            titleAttribute: $titleAttribute,
            searchColumns: $this->searchColumns($definition, $titleAttribute),
            perPage: $this->positiveIntegerValue($definition, RelationManagerDefinition::ATTRIBUTE_PER_PAGE)
                ?? RelationManagerDefinition::DEFAULT_PER_PAGE,
            readOnly: $readOnly,
            attachable: !$readOnly && Arr::get($definition, RelationManagerDefinition::ATTRIBUTE_ATTACHABLE, false),
            detachable: !$readOnly && Arr::get($definition, RelationManagerDefinition::ATTRIBUTE_DETACHABLE, false),
            replaceable: !$readOnly && $type === HasOne::TYPE && Arr::get($definition, RelationManagerDefinition::ATTRIBUTE_REPLACEABLE, false),
            syncable: !$readOnly && $type === HasMany::TYPE && Arr::get($definition, RelationManagerDefinition::ATTRIBUTE_SYNCABLE, false),
            showOnDetail: (bool) Arr::get($definition, RelationManagerDefinition::ATTRIBUTE_SHOW_ON_DETAIL, true),
            showOnEdit: (bool) Arr::get($definition, RelationManagerDefinition::ATTRIBUTE_SHOW_ON_EDIT, false),
            definition: $definition,
        );
    }

    private function resolveRelation(Model $model, string $relationship): EloquentHasOne|EloquentHasMany
    {
        if (!method_exists($model, $relationship)) {
            throw new \InvalidArgumentException(sprintf(
                'Relation manager relationship [%s] does not exist on model [%s].',
                $relationship,
                $model::class,
            ));
        }

        $relation = $model->{$relationship}();

        if (!$relation instanceof EloquentHasOne && !$relation instanceof EloquentHasMany) {
            throw new \InvalidArgumentException(sprintf(
                'Relationship [%s] on model [%s] must be an instance of [%s] or [%s].',
                $relationship,
                $model::class,
                EloquentHasOne::class,
                EloquentHasMany::class,
            ));
        }

        return $relation;
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function relationType(array $definition, EloquentHasOne|EloquentHasMany $relation): string
    {
        $type = $this->stringValue($definition, RelationManagerDefinition::ATTRIBUTE_TYPE);
        if ($type === HasOne::TYPE || $type === HasMany::TYPE) {
            return $type;
        }

        return $relation instanceof EloquentHasOne ? HasOne::TYPE : HasMany::TYPE;
    }

    /**
     * @param array<string, mixed> $definition
     *
     * @return class-string<\Illuminate\Database\Eloquent\Model>
     */
    private function relatedModel(array $definition, EloquentHasOne|EloquentHasMany $relation): string
    {
        $explicitModel = $this->stringValue($definition, RelationManagerDefinition::ATTRIBUTE_MODEL);
        if ($explicitModel !== null) {
            if (!is_subclass_of($explicitModel, Model::class)) {
                throw new \InvalidArgumentException(sprintf(
                    'Relation manager related model [%s] must extend [%s].',
                    $explicitModel,
                    Model::class,
                ));
            }

            return $explicitModel;
        }

        return $relation->getRelated()::class;
    }

    /**
     * @param array<string, mixed> $definition
     * @param class-string<\Illuminate\Database\Eloquent\Model> $relatedModel
     *
     * @return class-string<Resource>|null
     */
    private function relatedResource(array $definition, string $relatedModel): ?string
    {
        $explicitResource = $this->stringValue($definition, RelationManagerDefinition::ATTRIBUTE_RELATED_RESOURCE);
        if ($explicitResource !== null) {
            if (!is_subclass_of($explicitResource, Resource::class)) {
                throw new \InvalidArgumentException(sprintf(
                    'Relation manager related resource [%s] must extend [%s].',
                    $explicitResource,
                    Resource::class,
                ));
            }

            return $explicitResource;
        }
        if ($this->resourceRegistry === null) {
            return null;
        }

        $matches = $this->resourceRegistry->resourcesForModel($relatedModel);
        if (count($matches) > 1) {
            throw new \InvalidArgumentException(sprintf(
                'Related resource for model [%s] is ambiguous; define resource() explicitly.',
                $relatedModel,
            ));
        }

        return $matches[0] ?? null;
    }

    /**
     * @param array<string, mixed> $definition
     *
     * @return list<string>
     */
    private function searchColumns(array $definition, string $titleAttribute): array
    {
        $columns = Arr::get($definition, RelationManagerDefinition::ATTRIBUTE_SEARCH_COLUMNS, true);
        if ($columns === false) {
            return [];
        }
        if ($columns === true) {
            return [$titleAttribute];
        }

        $columns = is_string($columns) ? [$columns] : (array) $columns;

        return array_values(array_filter(
            array_map(static fn (mixed $column): string => trim((string) $column), $columns),
            static fn (string $column): bool => $column !== '',
        ));
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function requiredString(array $definition, string $key, string $message): string
    {
        $value = $this->stringValue($definition, $key);
        if ($value === null) {
            throw new \InvalidArgumentException($message);
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function stringValue(array $definition, string $key): ?string
    {
        $value = Arr::get($definition, $key);
        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }

        return null;
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function positiveIntegerValue(array $definition, string $key): ?int
    {
        $value = Arr::get($definition, $key);
        if (!is_numeric($value)) {
            return null;
        }

        return max(1, (int) $value);
    }
}
