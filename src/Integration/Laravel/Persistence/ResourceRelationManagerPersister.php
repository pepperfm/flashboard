<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Integration\Laravel\Persistence;

use Illuminate\Container\Attributes\Singleton;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Pepperfm\Flashboard\Contracts\Resources\Relations\RelationDefinitionContract;
use Pepperfm\Flashboard\Contracts\Resources\Resource;
use Pepperfm\Flashboard\Core\Authorization\Visibility\ScreenAccessResolver;
use Pepperfm\Flashboard\Core\Extensions\ExtensionRegistry;
use Pepperfm\Flashboard\Core\Registry\ResourceRegistry;
use Pepperfm\Flashboard\Core\Relations\HasMany;
use Pepperfm\Flashboard\Core\Relations\HasOne;
use Pepperfm\Flashboard\Core\Relations\RelationManagerDefinition;
use Pepperfm\Flashboard\Integration\Laravel\Relations\RelationManagerMetadata;
use Pepperfm\Flashboard\Integration\Laravel\Relations\RelationManagerMetadataResolver;
use Pepperfm\Flashboard\Integration\Laravel\Relations\RelationQueryModifier;

#[Singleton]
final readonly class ResourceRelationManagerPersister
{
    public function __construct(
        private ResourceRegistry $resourceRegistry,
        private ScreenAccessResolver $screenAccessResolver,
        private ?ExtensionRegistry $extensionRegistry = null,
    ) {
    }

    /**
     * @param class-string<Resource> $resourceClass
     */
    public function attach(
        string $resourceClass,
        Model $parent,
        string $relationKey,
        mixed $relatedKey,
        ?\Illuminate\Contracts\Auth\Authenticatable $user = null,
    ): void {
        [$relation, $metadata] = $this->definitionAndMetadata($resourceClass, $relationKey);
        $this->assertMode($metadata->attachable, 'Relation manager attach is not enabled.');
        $this->assertCanMutate($resourceClass, $parent, $metadata, $user);
        $related = $this->relatedRecord($metadata, $relation, $relatedKey);
        $this->assertCanMutateRelated($metadata, $related, $user);

        $parent->getConnection()->transaction(function () use ($metadata, $parent, $related): void {
            $related->forceFill([
                $metadata->foreignKey => $parent->getAttribute($metadata->localKey),
            ]);
            $related->save();
        });
    }

    /**
     * @param class-string<Resource> $resourceClass
     * @param list<mixed> $relatedKeys
     */
    public function detach(
        string $resourceClass,
        Model $parent,
        string $relationKey,
        array $relatedKeys = [],
        ?\Illuminate\Contracts\Auth\Authenticatable $user = null,
    ): void {
        [$relation, $metadata] = $this->definitionAndMetadata($resourceClass, $relationKey);
        $this->assertMode($metadata->detachable, 'Relation manager detach is not enabled.');
        $this->assertForeignKeyCanBeCleared($metadata, $parent);
        $this->assertCanMutate($resourceClass, $parent, $metadata, $user);
        $records = $this->relatedRecordsForDetach($metadata, $parent, $relation, $relatedKeys);

        $parent->getConnection()->transaction(function () use ($metadata, $records, $user): void {
            foreach ($records as $record) {
                $this->assertCanMutateRelated($metadata, $record, $user);
                $record->forceFill([$metadata->foreignKey => null]);
                $record->save();
            }
        });
    }

    /**
     * @param class-string<Resource> $resourceClass
     */
    public function replace(
        string $resourceClass,
        Model $parent,
        string $relationKey,
        mixed $relatedKey,
        ?\Illuminate\Contracts\Auth\Authenticatable $user = null,
    ): void {
        [$relation, $metadata] = $this->definitionAndMetadata($resourceClass, $relationKey);
        $this->assertMode($metadata->type === HasOne::TYPE && $metadata->replaceable, 'Relation manager replace is not enabled.');
        $this->assertMode($metadata->detachable, 'Relation manager replace requires detach to be enabled.');
        $this->assertForeignKeyCanBeCleared($metadata, $parent);
        $this->assertCanMutate($resourceClass, $parent, $metadata, $user);
        $replacement = $this->relatedRecord($metadata, $relation, $relatedKey);
        $this->assertCanMutateRelated($metadata, $replacement, $user);
        $current = $this->relationQuery($metadata, $parent, $relation)->first();

        $parent->getConnection()->transaction(function () use ($metadata, $parent, $current, $replacement, $user): void {
            if ($current instanceof Model) {
                $this->assertCanMutateRelated($metadata, $current, $user);
                $current->forceFill([$metadata->foreignKey => null]);
                $current->save();
            }

            $replacement->forceFill([
                $metadata->foreignKey => $parent->getAttribute($metadata->localKey),
            ]);
            $replacement->save();
        });
    }

    /**
     * @param class-string<Resource> $resourceClass
     * @param list<mixed> $relatedKeys
     */
    public function sync(
        string $resourceClass,
        Model $parent,
        string $relationKey,
        array $relatedKeys,
        ?\Illuminate\Contracts\Auth\Authenticatable $user = null,
    ): void {
        [$relation, $metadata] = $this->definitionAndMetadata($resourceClass, $relationKey);
        $this->assertMode($metadata->type === HasMany::TYPE && $metadata->syncable, 'Relation manager sync is not enabled.');
        $this->assertMode($metadata->detachable, 'Relation manager sync requires detach to be enabled.');
        $this->assertForeignKeyCanBeCleared($metadata, $parent);
        $this->assertCanMutate($resourceClass, $parent, $metadata, $user);
        $selectedRecords = $this->relatedRecordsByKeys($metadata, $relation, $relatedKeys);
        $selectedKeys = array_fill_keys(array_map(
            static fn (Model $record): string => (string) $record->getAttribute($metadata->recordKeyName),
            $selectedRecords,
        ), true);
        $currentRecords = $this->relationQuery($metadata, $parent, $relation)->get()->all();

        $parent->getConnection()->transaction(function () use ($metadata, $parent, $currentRecords, $selectedRecords, $selectedKeys, $user): void {
            foreach ($currentRecords as $currentRecord) {
                if (
                    !$currentRecord instanceof Model
                    || isset($selectedKeys[(string) $currentRecord->getAttribute($metadata->recordKeyName)])
                ) {
                    continue;
                }

                $this->assertCanMutateRelated($metadata, $currentRecord, $user);
                $currentRecord->forceFill([$metadata->foreignKey => null]);
                $currentRecord->save();
            }

            foreach ($selectedRecords as $selectedRecord) {
                $this->assertCanMutateRelated($metadata, $selectedRecord, $user);
                $selectedRecord->forceFill([
                    $metadata->foreignKey => $parent->getAttribute($metadata->localKey),
                ]);
                $selectedRecord->save();
            }
        });
    }

    /**
     * @param class-string<Resource> $resourceClass
     *
     * @return array{0: RelationDefinitionContract, 1: RelationManagerMetadata}
     */
    private function definitionAndMetadata(string $resourceClass, string $relationKey): array
    {
        foreach ($resourceClass::relations() as $relation) {
            $definition = $relation->toArray();
            if ((string) Arr::get($definition, 'key', '') !== $relationKey) {
                continue;
            }

            $type = (string) Arr::get($definition, RelationManagerDefinition::ATTRIBUTE_TYPE, '');
            if (!in_array($type, [HasOne::TYPE, HasMany::TYPE], true)) {
                break;
            }

            return [
                $relation,
                new RelationManagerMetadataResolver($this->resourceRegistry)->resolve($resourceClass, $definition),
            ];
        }

        throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
    }

    private function relatedRecord(
        RelationManagerMetadata $metadata,
        RelationDefinitionContract $relation,
        mixed $relatedKey,
    ): Model
    {
        $record = $this->relatedQuery($metadata, $relation)
            ->where($metadata->recordKeyName, $relatedKey)
            ->first();
        if (!$record instanceof Model) {
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
        }

        return $record;
    }

    /**
     * @param list<mixed> $relatedKeys
     *
     * @return list<Model>
     */
    private function relatedRecordsByKeys(
        RelationManagerMetadata $metadata,
        RelationDefinitionContract $relation,
        array $relatedKeys,
    ): array
    {
        $relatedKeys = $this->uniqueRelatedKeys($relatedKeys);
        if ($relatedKeys === []) {
            return [];
        }

        $records = $this->modelList($this->relatedQuery($metadata, $relation)
            ->whereIn($metadata->recordKeyName, $relatedKeys)
            ->get()
            ->all());
        if (count($records) !== count($relatedKeys)) {
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
        }

        return $records;
    }

    /**
     * @param list<mixed> $relatedKeys
     *
     * @return list<Model>
     */
    private function relatedRecordsForDetach(
        RelationManagerMetadata $metadata,
        Model $parent,
        RelationDefinitionContract $relation,
        array $relatedKeys,
    ): array
    {
        $query = $this->relationQuery($metadata, $parent, $relation);

        if ($metadata->type === HasOne::TYPE && $relatedKeys === []) {
            $record = $query->first();

            return $record instanceof Model ? [$record] : [];
        }
        if ($relatedKeys === []) {
            throw new \InvalidArgumentException('Relation manager detach requires selected records.');
        }

        $relatedKeys = $this->uniqueRelatedKeys($relatedKeys);
        $records = $this->modelList($query
            ->whereIn($metadata->recordKeyName, $relatedKeys)
            ->get()
            ->all());
        if (count($records) !== count($relatedKeys)) {
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
        }

        return $records;
    }

    /**
     * @return Builder<Model>
     */
    private function relatedQuery(RelationManagerMetadata $metadata, RelationDefinitionContract $relation): Builder
    {
        if ($metadata->relatedResource !== null) {
            $query = $metadata->relatedResource::query();

            $query = $this->extensionRegistry?->extendQuery($metadata->relatedResource, $query) ?? $query;

            return RelationQueryModifier::apply(
                $this->attachOptionsQueryModifier($relation),
                $query,
                $metadata->key . ':attach-options',
            );
        }

        return RelationQueryModifier::apply(
            $this->attachOptionsQueryModifier($relation),
            $metadata->relatedModel::query(),
            $metadata->key . ':attach-options',
        );
    }

    /**
     * @return Builder<Model>
     */
    private function relationQuery(
        RelationManagerMetadata $metadata,
        Model $parent,
        RelationDefinitionContract $relation,
    ): Builder
    {
        $query = $parent->{$metadata->relationship}()->getQuery();

        if ($metadata->relatedResource !== null) {
            $query = $this->extensionRegistry?->extendQuery($metadata->relatedResource, $query) ?? $query;
        }

        return RelationQueryModifier::apply(
            $this->recordsQueryModifier($relation),
            $query,
            $metadata->key . ':records',
        );
    }

    private function recordsQueryModifier(RelationDefinitionContract $relation): ?\Closure
    {
        return $relation instanceof RelationManagerDefinition ? $relation->recordsQueryModifier() : null;
    }

    private function attachOptionsQueryModifier(RelationDefinitionContract $relation): ?\Closure
    {
        return $relation instanceof RelationManagerDefinition ? $relation->attachOptionsQueryModifier() : null;
    }

    /**
     * @param list<mixed> $relatedKeys
     *
     * @return list<mixed>
     */
    private function uniqueRelatedKeys(array $relatedKeys): array
    {
        $unique = [];

        foreach ($relatedKeys as $relatedKey) {
            if (!is_scalar($relatedKey) && !$relatedKey instanceof \Stringable) {
                continue;
            }

            $unique[(string) $relatedKey] = $relatedKey;
        }

        return array_values($unique);
    }

    /**
     * @param array $records
     *
     * @return list<Model>
     */
    private function modelList(iterable $records): array
    {
        $models = [];
        foreach ($records as $record) {
            if ($record instanceof Model) {
                $models[] = $record;
            }
        }

        return $models;
    }

    private function assertCanMutate(
        string $resourceClass,
        Model $parent,
        RelationManagerMetadata $metadata,
        ?\Illuminate\Contracts\Auth\Authenticatable $user,
    ): void {
        if (!$this->screenAccessResolver->canEditRecord($resourceClass, $user, $parent)) {
            throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException();
        }
        if ($metadata->relatedResource === null) {
            throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException();
        }
    }

    private function assertCanMutateRelated(
        RelationManagerMetadata $metadata,
        Model $record,
        ?\Illuminate\Contracts\Auth\Authenticatable $user,
    ): void {
        if ($metadata->relatedResource === null || !$this->screenAccessResolver->canEditRecord($metadata->relatedResource, $user, $record)) {
            throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException();
        }
    }

    private function assertMode(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new \InvalidArgumentException($message);
        }
    }

    private function assertForeignKeyCanBeCleared(RelationManagerMetadata $metadata, Model $parent): void
    {
        if (!$this->foreignKeyCanBeCleared($metadata, $parent)) {
            throw new \InvalidArgumentException('Relation manager foreign key cannot be cleared safely.');
        }
    }

    private function foreignKeyCanBeCleared(RelationManagerMetadata $metadata, Model $parent): bool
    {
        try {
            foreach ($parent->getConnection()->getSchemaBuilder()->getColumns($metadata->relatedTable) as $column) {
                if ((string) ($column['name'] ?? '') !== $metadata->foreignKey) {
                    continue;
                }

                return (bool) ($column['nullable'] ?? true);
            }
        } catch (\Throwable) {
            return true;
        }

        return true;
    }
}
