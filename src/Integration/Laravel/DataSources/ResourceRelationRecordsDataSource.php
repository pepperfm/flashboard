<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Integration\Laravel\DataSources;

use Illuminate\Container\Attributes\Singleton;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Pepperfm\Flashboard\Contracts\Resources\Resource;
use Pepperfm\Flashboard\Core\Authorization\Visibility\ScreenAccessResolver;
use Pepperfm\Flashboard\Core\Extensions\ExtensionRegistry;
use Pepperfm\Flashboard\Core\Registry\ResourceRegistry;
use Pepperfm\Flashboard\Core\Relations\HasMany;
use Pepperfm\Flashboard\Core\Relations\HasOne;
use Pepperfm\Flashboard\Core\Relations\RelationManagerDefinition;
use Pepperfm\Flashboard\Core\Resources\ResourceSurfaceResolver;
use Pepperfm\Flashboard\Integration\Laravel\Auth\PanelAuthenticator;
use Pepperfm\Flashboard\Integration\Laravel\Relations\RelationManagerMetadata;
use Pepperfm\Flashboard\Integration\Laravel\Relations\RelationManagerMetadataResolver;

#[Singleton]
final readonly class ResourceRelationRecordsDataSource
{
    private const int MAX_PER_PAGE = 100;

    public function __construct(
        private PanelAuthenticator $authenticator,
        private ScreenAccessResolver $screenAccessResolver,
        private ExtensionRegistry $extensionRegistry,
        private ResourceRegistry $resourceRegistry,
        private ResourceSurfaceResolver $resourceSurfaceResolver,
    ) {
    }

    /**
     * @param class-string<Resource> $resourceClass
     *
     * @return list<array<string, mixed>>
     */
    public function initialPayloads(
        string $resourceClass,
        ?Model $record,
        ?\Illuminate\Contracts\Auth\Authenticatable $user,
        string $placement = 'detail',
    ): array {
        if ($record === null) {
            return [];
        }

        $payloads = [];

        foreach ($resourceClass::relations() as $relation) {
            $definition = $relation->toArray();
            if (!$this->isRelationManager($definition) || !$this->isVisibleOnPlacement($definition, $placement)) {
                continue;
            }
            if (!$this->screenAccessResolver->canViewRelation($resourceClass, (string) Arr::get($definition, 'key', ''), $user)) {
                continue;
            }

            $payloads[] = $this->payload($resourceClass, $record, $definition, $user, 1);
        }

        return $payloads;
    }

    /**
     * @param class-string<Resource> $resourceClass
     *
     * @return array<string, mixed>
     */
    public function resolve(string $resourceClass, Model $record, string $relationKey, \Illuminate\Http\Request $request): array
    {
        $definition = $this->findRelationManager($resourceClass, $relationKey);
        if ($definition === null) {
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
        }

        $user = $this->authenticator->user();
        if (!$this->screenAccessResolver->canViewRelation($resourceClass, $relationKey, $user)) {
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
        }

        $page = max(1, (int) $request->query('page', '1'));

        return $this->payload($resourceClass, $record, $definition, $user, $page);
    }

    /**
     * @param class-string<Resource> $resourceClass
     * @param array<string, mixed> $definition
     *
     * @return array<string, mixed>
     */
    private function payload(
        string $resourceClass,
        Model $record,
        array $definition,
        ?\Illuminate\Contracts\Auth\Authenticatable $user,
        int $page,
    ): array {
        $metadata = $this->metadataResolver()->resolve($resourceClass, $definition);
        $perPage = min(self::MAX_PER_PAGE, max(1, $metadata->perPage));
        $records = $this->records($metadata, $record, $user, $page, $perPage);
        $hasMore = count($records) > $perPage;
        $records = array_slice($records, 0, $perPage);

        return array_merge($definition, [
            'type' => $metadata->type,
            'relationship' => $metadata->relationship,
            'related_model' => $metadata->relatedModel,
            'related_resource' => $metadata->relatedResource,
            'local_key' => $metadata->localKey,
            'foreign_key' => $metadata->foreignKey,
            'record_key_name' => $metadata->recordKeyName,
            'title_attribute' => $metadata->titleAttribute,
            'search_columns' => $metadata->searchColumns,
            'per_page' => $metadata->perPage,
            'records_url' => $this->recordsUrl($resourceClass, $record, $metadata),
            'options_url' => $metadata->attachable ? $this->optionsUrl($resourceClass, $record, $metadata) : null,
            'actions' => $this->actions($resourceClass, $record, $metadata, $user),
            'records' => $records,
            'selected_record' => $metadata->type === HasOne::TYPE ? ($records[0] ?? null) : null,
            'selected_records' => $metadata->type === HasMany::TYPE ? $records : [],
            'pagination' => $metadata->type === HasMany::TYPE ? [
                'current_page' => $page,
                'per_page' => $perPage,
                'has_more' => $hasMore,
                'next_page' => $hasMore ? $page + 1 : null,
            ] : null,
            'empty_state' => [
                'title' => 'No related records',
                'description' => 'No related records are available yet.',
            ],
            'read_only' => $metadata->readOnly,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function records(
        RelationManagerMetadata $metadata,
        Model $record,
        ?\Illuminate\Contracts\Auth\Authenticatable $user,
        int $page,
        int $perPage,
    ): array {
        $query = $this->relationQuery($metadata, $record);

        if ($metadata->type === HasOne::TYPE) {
            $relatedRecord = $query->first();

            return $relatedRecord instanceof Model
                ? array_values(array_filter([$this->recordPayload($metadata, $relatedRecord, $user)]))
                : [];
        }

        $records = $query
            ->orderBy($metadata->titleAttribute)
            ->orderBy($metadata->recordKeyName)
            ->offset(max(0, ($page - 1) * $perPage))
            ->limit($perPage + 1)
            ->get()
            ->all();

        $payloads = [];

        foreach ($records as $item) {
            if (!$item instanceof Model) {
                continue;
            }

            $payload = $this->recordPayload($metadata, $item, $user);
            if ($payload !== null) {
                $payloads[] = $payload;
            }
        }

        return $payloads;
    }

    /**
     * @return Builder<Model>
     */
    private function relationQuery(RelationManagerMetadata $metadata, Model $record): Builder
    {
        $relation = $record->{$metadata->relationship}();
        $query = $relation->getQuery();

        if ($metadata->relatedResource !== null) {
            return $this->extensionRegistry->extendQuery($metadata->relatedResource, $query);
        }

        return $query;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function recordPayload(
        RelationManagerMetadata $metadata,
        Model $record,
        ?\Illuminate\Contracts\Auth\Authenticatable $user,
    ): ?array {
        $key = $this->scalarValue(data_get($record, $metadata->recordKeyName));

        if ($key === null) {
            return null;
        }

        $payload = [
            'key' => $key,
            'title' => (string) ($this->scalarValue(data_get($record, $metadata->titleAttribute)) ?? $key),
            'attributes' => $record->attributesToArray(),
            'links' => [
                'detail' => $this->detailUrl($metadata, $record, $user),
                'edit' => $this->editUrl($metadata, $record, $user),
            ],
        ];

        $payload['actions'] = array_values(array_filter([
            $payload['links']['detail'] !== null ? [
                'key' => 'view',
                'label' => 'View',
                'icon' => 'i-lucide-eye',
                'method' => 'get',
                'url' => $payload['links']['detail'],
            ] : null,
            $payload['links']['edit'] !== null ? [
                'key' => 'edit',
                'label' => 'Edit',
                'icon' => 'i-lucide-pencil',
                'method' => 'get',
                'url' => $payload['links']['edit'],
            ] : null,
        ]));

        return $payload;
    }

    private function detailUrl(
        RelationManagerMetadata $metadata,
        Model $record,
        ?\Illuminate\Contracts\Auth\Authenticatable $user,
    ): ?string {
        if (
            $metadata->relatedResource === null
            || !$this->resourceSurfaceResolver->hasDetailSurfaceForResource($metadata->relatedResource)
            || !$this->screenAccessResolver->canViewRecord($metadata->relatedResource, $user, $record)
        ) {
            return null;
        }

        return route(
            config('flashboard.route_name_prefix', 'flashboard.')
            . 'resources.' . $metadata->relatedResource::key() . '.detail',
            ['record' => $record->getKey()],
        );
    }

    private function editUrl(
        RelationManagerMetadata $metadata,
        Model $record,
        ?\Illuminate\Contracts\Auth\Authenticatable $user,
    ): ?string {
        if ($metadata->relatedResource === null || !$this->screenAccessResolver->canEditRecord($metadata->relatedResource, $user, $record)) {
            return null;
        }

        return route(
            config('flashboard.route_name_prefix', 'flashboard.')
            . 'resources.' . $metadata->relatedResource::key() . '.edit',
            ['record' => $record->getKey()],
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function actions(
        string $resourceClass,
        Model $record,
        RelationManagerMetadata $metadata,
        ?\Illuminate\Contracts\Auth\Authenticatable $user,
    ): array {
        $canCreateRelated = $metadata->relatedResource !== null
            && $this->resourceSurfaceResolver->hasFormSurfaceForResource($metadata->relatedResource)
            && $this->screenAccessResolver->canCreateRecord($metadata->relatedResource, $user);

        return array_values(array_filter([
            $canCreateRelated ? [
                'key' => 'create',
                'label' => 'Create',
                'icon' => 'i-lucide-plus',
                'method' => 'get',
                'url' => $this->createUrl($metadata, $resourceClass, $record),
                'visible' => true,
                'requires_confirmation' => false,
            ] : null,
            $metadata->attachable ? [
                'key' => 'attach',
                'label' => 'Attach',
                'icon' => 'i-lucide-link',
                'method' => 'post',
                'url' => $this->mutationUrl($resourceClass, $record, $metadata, 'attach'),
                'visible' => true,
                'requires_confirmation' => false,
            ] : null,
            $metadata->detachable ? [
                'key' => 'detach',
                'label' => 'Detach',
                'icon' => 'i-lucide-unlink',
                'method' => 'delete',
                'url' => $this->mutationUrl($resourceClass, $record, $metadata, 'detach'),
                'visible' => true,
                'requires_confirmation' => true,
            ] : null,
            $metadata->replaceable ? [
                'key' => 'replace',
                'label' => 'Replace',
                'icon' => 'i-lucide-refresh-cw',
                'method' => 'patch',
                'url' => $this->mutationUrl($resourceClass, $record, $metadata, 'replace'),
                'visible' => true,
                'requires_confirmation' => true,
            ] : null,
            $metadata->syncable ? [
                'key' => 'sync',
                'label' => 'Sync',
                'icon' => 'i-lucide-list-checks',
                'method' => 'patch',
                'url' => $this->mutationUrl($resourceClass, $record, $metadata, 'sync'),
                'visible' => true,
                'requires_confirmation' => true,
            ] : null,
        ]));
    }

    private function createUrl(RelationManagerMetadata $metadata, string $resourceClass, Model $record): string
    {
        if ($metadata->relatedResource === null) {
            throw new \InvalidArgumentException('Relation manager create requires related resource.');
        }

        return route(
            config('flashboard.route_name_prefix', 'flashboard.')
            . 'resources.' . $metadata->relatedResource::key() . '.create',
            [
                'parent_resource' => $resourceClass::key(),
                'parent_record' => $record->getKey(),
                'relation' => $metadata->key,
            ],
        );
    }

    /**
     * @param class-string<Resource> $resourceClass
     */
    private function recordsUrl(string $resourceClass, Model $record, RelationManagerMetadata $metadata): string
    {
        return route(
            config('flashboard.route_name_prefix', 'flashboard.') . 'resources.' . $resourceClass::key() . '.relations.records',
            ['record' => $record->getKey(), 'relation' => $metadata->key],
        );
    }

    /**
     * @param class-string<Resource> $resourceClass
     */
    private function optionsUrl(string $resourceClass, Model $record, RelationManagerMetadata $metadata): string
    {
        return route(
            config('flashboard.route_name_prefix', 'flashboard.') . 'resources.' . $resourceClass::key() . '.relations.attach-options',
            ['record' => $record->getKey(), 'relation' => $metadata->key],
        );
    }

    /**
     * @param class-string<Resource> $resourceClass
     */
    private function mutationUrl(string $resourceClass, Model $record, RelationManagerMetadata $metadata, string $action): string
    {
        return route(
            config('flashboard.route_name_prefix', 'flashboard.') . 'resources.' . $resourceClass::key() . '.relations.' . $action,
            ['record' => $record->getKey(), 'relation' => $metadata->key],
        );
    }

    /**
     * @param class-string<Resource> $resourceClass
     *
     * @return array<string, mixed>|null
     */
    private function findRelationManager(string $resourceClass, string $relationKey): ?array
    {
        foreach ($resourceClass::relations() as $relation) {
            $definition = $relation->toArray();
            if ((string) Arr::get($definition, 'key', '') !== $relationKey) {
                continue;
            }

            return $this->isRelationManager($definition) ? $definition : null;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function isRelationManager(array $definition): bool
    {
        return in_array(
            (string) Arr::get($definition, RelationManagerDefinition::ATTRIBUTE_TYPE, ''),
            [HasOne::TYPE, HasMany::TYPE],
            true
        );
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function isVisibleOnPlacement(array $definition, string $placement): bool
    {
        if ((bool) Arr::get($definition, RelationManagerDefinition::ATTRIBUTE_VISIBLE, true) === false) {
            return false;
        }
        if ($placement === 'edit') {
            return (bool) Arr::get($definition, RelationManagerDefinition::ATTRIBUTE_SHOW_ON_EDIT, false);
        }

        return (bool) Arr::get($definition, RelationManagerDefinition::ATTRIBUTE_SHOW_ON_DETAIL, true);
    }

    private function metadataResolver(): RelationManagerMetadataResolver
    {
        return new RelationManagerMetadataResolver($this->resourceRegistry);
    }

    private function scalarValue(mixed $value): string|int|bool|null
    {
        if (is_string($value) || is_int($value) || is_bool($value)) {
            return $value;
        }
        if ($value instanceof \Stringable) {
            return (string) $value;
        }

        return null;
    }
}
