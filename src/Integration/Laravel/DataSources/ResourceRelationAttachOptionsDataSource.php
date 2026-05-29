<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Integration\Laravel\DataSources;

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
use Pepperfm\Flashboard\Core\Resources\ResourceSurfaceResolver;
use Pepperfm\Flashboard\Integration\Laravel\Auth\PanelAuthenticator;
use Pepperfm\Flashboard\Integration\Laravel\Relations\RelationManagerMetadata;
use Pepperfm\Flashboard\Integration\Laravel\Relations\RelationManagerMetadataResolver;
use Pepperfm\Flashboard\Integration\Laravel\Relations\RelationQueryModifier;

#[Singleton]
final readonly class ResourceRelationAttachOptionsDataSource
{
    private const int MAX_PER_PAGE = 100;
    private const int MAX_SELECTED_VALUES = 200;

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
     * @return array{items: list<array{label: string, value: string|int|bool, url?: string}>, meta: array{has_more: bool, next_page: int|null}}
     */
    public function resolve(string $resourceClass, Model $record, string $relationKey, \Illuminate\Http\Request $request): array
    {
        $relation = $this->findRelationManager($resourceClass, $relationKey);
        if ($relation === null) {
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
        }
        $definition = $relation->toArray();

        $user = $this->authenticator->user();
        if (!$this->screenAccessResolver->canViewRelation($resourceClass, $relationKey, $user)) {
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
        }

        $metadata = $this->metadataResolver()->resolve($resourceClass, $definition);
        if (!$metadata->attachable || !$this->canQueryOptions($metadata, $user)) {
            throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException();
        }

        $page = max(1, (int) $request->query('page', '1'));
        $perPage = min(self::MAX_PER_PAGE, max(1, (int) $request->query('per_page', (string) $metadata->perPage)));
        $search = trim((string) $request->query('search', ''));
        $selectedValues = $this->scalarValues($request->query('selected'));
        $items = $this->optionRows($metadata, $record, $relation, $user, $search, $page, $perPage);
        $hasMore = count($items) > $perPage;
        $items = array_slice($items, 0, $perPage);

        if ($selectedValues !== []) {
            $selectedItems = $this->selectedOptions($metadata, $record, $relation, $selectedValues, $user);
            $missingSelectedItems = [];

            foreach ($selectedItems as $selectedItem) {
                if (!$this->hasOptionValue($items, $selectedItem['value'])) {
                    $missingSelectedItems[] = $selectedItem;
                }
            }

            $items = array_merge($missingSelectedItems, $items);
        }

        return [
            'items' => $items,
            'meta' => [
                'has_more' => $hasMore,
                'next_page' => $hasMore ? $page + 1 : null,
            ],
        ];
    }

    private function canQueryOptions(
        RelationManagerMetadata $metadata,
        ?\Illuminate\Contracts\Auth\Authenticatable $user,
    ): bool {
        if ($metadata->relatedResource === null) {
            return false;
        }

        return $this->screenAccessResolver->canAccessResource($metadata->relatedResource, $user);
    }

    /**
     * @return list<array{label: string, value: string|int|bool, url?: string}>
     */
    private function optionRows(
        RelationManagerMetadata $metadata,
        Model $record,
        RelationDefinitionContract $relation,
        ?\Illuminate\Contracts\Auth\Authenticatable $user,
        string $search,
        int $page,
        int $perPage,
    ): array {
        $query = $this->attachableQuery($metadata, $record, $relation);

        if ($search !== '' && $metadata->searchColumns !== []) {
            $query->where(function (Builder $query) use ($metadata, $search): void {
                foreach ($metadata->searchColumns as $index => $column) {
                    $method = $index === 0 ? 'where' : 'orWhere';
                    $query->{$method}($column, 'like', '%' . $search . '%');
                }
            });
        }

        $records = $query
            ->orderBy($metadata->titleAttribute)
            ->orderBy($metadata->recordKeyName)
            ->offset(max(0, ($page - 1) * $perPage))
            ->limit($perPage + 1)
            ->get()
            ->all();

        return $this->optionsFromRecords($metadata, $records, $user);
    }

    /**
     * @param list<string|int|bool> $selectedValues
     *
     * @return list<array{label: string, value: string|int|bool, url?: string}>
     */
    private function selectedOptions(
        RelationManagerMetadata $metadata,
        Model $record,
        RelationDefinitionContract $relation,
        array $selectedValues,
        ?\Illuminate\Contracts\Auth\Authenticatable $user,
    ): array {
        $records = $this->attachableQuery($metadata, $record, $relation)
            ->whereIn($metadata->recordKeyName, $selectedValues)
            ->get()
            ->all();

        return $this->optionsFromRecords($metadata, $records, $user);
    }

    /**
     * @return Builder<Model>
     */
    private function attachableQuery(
        RelationManagerMetadata $metadata,
        Model $record,
        RelationDefinitionContract $relation,
    ): Builder
    {
        $query = $metadata->relatedResource !== null
            ? $this->extensionRegistry->extendQuery($metadata->relatedResource, $metadata->relatedResource::query())
            : $metadata->relatedModel::query();
        $query = RelationQueryModifier::apply(
            $this->attachOptionsQueryModifier($relation),
            $query,
            $metadata->key . ':attach-options',
        );

        $parentKey = data_get($record, $metadata->localKey);

        if ($metadata->type === HasMany::TYPE) {
            return $query->where(function (Builder $query) use ($metadata, $parentKey): void {
                $query
                    ->whereNull($metadata->foreignKey)
                    ->orWhere($metadata->foreignKey, '!=', $parentKey);
            });
        }

        return $query->where(function (Builder $query) use ($metadata, $parentKey): void {
            $query
                ->whereNull($metadata->foreignKey)
                ->orWhere($metadata->foreignKey, '!=', $parentKey);
        });
    }

    /**
     * @param array $records
     *
     * @return list<array{label: string, value: string|int|bool, url?: string}>
     */
    private function optionsFromRecords(
        RelationManagerMetadata $metadata,
        iterable $records,
        ?\Illuminate\Contracts\Auth\Authenticatable $user,
    ): array {
        $items = [];

        foreach ($records as $record) {
            if (!$record instanceof Model) {
                continue;
            }

            $option = $this->optionFromRecord($metadata, $record, $user);
            if ($option !== null) {
                $items[] = $option;
            }
        }

        return $items;
    }

    /**
     * @return array{label: string, value: string|int|bool, url?: string}|null
     */
    private function optionFromRecord(
        RelationManagerMetadata $metadata,
        Model $record,
        ?\Illuminate\Contracts\Auth\Authenticatable $user,
    ): ?array {
        $value = $this->scalarValue(data_get($record, $metadata->recordKeyName));
        if ($value === null) {
            return null;
        }

        $option = [
            'label' => (string) ($this->scalarValue(data_get($record, $metadata->titleAttribute)) ?? $value),
            'value' => $value,
        ];
        $url = $this->detailUrl($metadata, $record, $user);

        if ($url !== null) {
            $option['url'] = $url;
        }

        return $option;
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
            config('flashboard.route_name_prefix', 'flashboard.') . 'resources.' . $metadata->relatedResource::key() . '.detail',
            ['record' => $record->getKey()],
        );
    }

    /**
     * @param list<array{label: string, value: string|int|bool, url?: string}> $items
     */
    private function hasOptionValue(array $items, string|int|bool $value): bool
    {
        return array_any($items, fn($item) => (string) $item['value'] === (string) $value);
    }

    /**
     * @param class-string<Resource> $resourceClass
     *
     * @return RelationDefinitionContract|null
     */
    private function findRelationManager(string $resourceClass, string $relationKey): ?RelationDefinitionContract
    {
        foreach ($resourceClass::relations() as $relation) {
            $definition = $relation->toArray();

            if ((string) Arr::get($definition, 'key', '') !== $relationKey) {
                continue;
            }

            return $this->isRelationManager($definition) ? $relation : null;
        }

        return null;
    }

    private function attachOptionsQueryModifier(RelationDefinitionContract $relation): ?\Closure
    {
        return $relation instanceof RelationManagerDefinition ? $relation->attachOptionsQueryModifier() : null;
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function isRelationManager(array $definition): bool
    {
        return in_array((string) Arr::get($definition, RelationManagerDefinition::ATTRIBUTE_TYPE, ''), [HasOne::TYPE, HasMany::TYPE], true);
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

    /**
     * @return list<string|int|bool>
     */
    private function scalarValues(mixed $value): array
    {
        $values = is_array($value) ? $value : [$value];
        $normalized = [];

        foreach ($values as $item) {
            $scalarValue = $this->scalarValue($item);
            if ($scalarValue === null || array_key_exists((string) $scalarValue, $normalized)) {
                continue;
            }

            $normalized[(string) $scalarValue] = $scalarValue;
            if (count($normalized) >= self::MAX_SELECTED_VALUES) {
                break;
            }
        }

        return array_values($normalized);
    }
}
