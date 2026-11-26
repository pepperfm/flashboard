<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Integration\Laravel\DataSources;

use Illuminate\Container\Attributes\Singleton;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Pepperfm\Flashboard\Contracts\Forms\FieldRenderer;
use Pepperfm\Flashboard\Contracts\Forms\FormContract;
use Pepperfm\Flashboard\Contracts\Resources\Resource;
use Pepperfm\Flashboard\Core\Authorization\Visibility\ScreenAccessResolver;
use Pepperfm\Flashboard\Core\Extensions\ExtensionRegistry;
use Pepperfm\Flashboard\Core\Forms\Builders\Form;
use Pepperfm\Flashboard\Core\Forms\Fields\BelongsTo;
use Pepperfm\Flashboard\Core\Forms\Fields\BelongsToMany;
use Pepperfm\Flashboard\Core\Forms\Fields\Field;
use Pepperfm\Flashboard\Core\Forms\Relations\BelongsToManyRelationMetadata;
use Pepperfm\Flashboard\Core\Forms\Relations\BelongsToManyRelationMetadataResolver;
use Pepperfm\Flashboard\Core\Forms\Relations\BelongsToRelationMetadata;
use Pepperfm\Flashboard\Core\Forms\Relations\BelongsToRelationMetadataResolver;
use Pepperfm\Flashboard\Core\Registry\ResourceRegistry;
use Pepperfm\Flashboard\Core\Resources\ResourceSurfaceResolver;
use Pepperfm\Flashboard\Integration\Laravel\Auth\PanelAuthenticator;
use Pepperfm\Flashboard\Integration\Laravel\Relations\RelationOptionAccess;

#[Singleton]
final readonly class ResourceRelationOptionsDataSource
{
    private const int MAX_PER_PAGE = 100;
    private const int MAX_SELECTED_VALUES = 200;

    private RelationOptionAccess $optionAccess;

    public function __construct(
        private PanelAuthenticator $authenticator,
        private ScreenAccessResolver $screenAccessResolver,
        ExtensionRegistry $extensionRegistry,
        private ResourceRegistry $resourceRegistry,
        private ResourceSurfaceResolver $resourceSurfaceResolver,
    ) {
        $this->optionAccess = new RelationOptionAccess($extensionRegistry, $screenAccessResolver);
    }

    /**
     * @param class-string<Resource> $resourceClass
     *
     * @return array{items: list<array{label: string, value: string|int|bool, url?: string}>, meta: array{has_more: bool, next_page: int|null}}
     */
    public function resolve(string $resourceClass, string $fieldKey, \Illuminate\Http\Request $request): array
    {
        $form = $resourceClass::form(Form::make());
        $field = $this->findRelationField($form, $fieldKey);
        if ($field === null) {
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
        }

        $user = $this->authenticator->user();
        if (!$this->screenAccessResolver->canViewField($resourceClass, $fieldKey, $user)) {
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
        }
        if ($this->isBelongsToManyField($field)) {
            return $this->resolveBelongsToMany($resourceClass, $field, $fieldKey, $form, $request, $user);
        }

        $queryModifier = $this->findBelongsToQueryModifier($form, $fieldKey);
        $metadata = new BelongsToRelationMetadataResolver($this->resourceRegistry)->resolve($resourceClass, $field);
        if (!$this->optionAccess->canQuery($metadata, $user)) {
            throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException();
        }

        $page = max(1, (int) $request->query('page', '1'));
        $perPage = min(
            self::MAX_PER_PAGE,
            max(1, (int) $request->query('per_page', (string) $metadata->optionsPerPage)),
        );
        $search = trim((string) $request->query('search', ''));
        $selectedValues = $this->scalarValues($request->query('selected'));
        $items = $this->optionRows($metadata, $user, $search, $page, $perPage, $queryModifier);
        $hasMore = count($items) > $perPage;
        $items = array_slice($items, 0, $perPage);

        if ($selectedValues !== []) {
            $selectedItems = $this->selectedOptions($metadata, $selectedValues, $user, $queryModifier);
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

    /**
     * @param class-string<Resource> $resourceClass
     * @param array<string, mixed> $field
     *
     * @return array{items: list<array{label: string, value: string|int|bool, url?: string}>, meta: array{has_more: bool, next_page: int|null}}
     */
    private function resolveBelongsToMany(
        string $resourceClass,
        array $field,
        string $fieldKey,
        FormContract $form,
        \Illuminate\Http\Request $request,
        ?\Illuminate\Contracts\Auth\Authenticatable $user,
    ): array {
        $queryModifier = $this->findBelongsToManyQueryModifier($form, $fieldKey);
        $metadata = new BelongsToManyRelationMetadataResolver($this->resourceRegistry)->resolve($resourceClass, $field);
        if (!$this->optionAccess->canQuery($metadata, $user)) {
            throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException();
        }

        $page = max(1, (int) $request->query('page', '1'));
        $perPage = min(
            self::MAX_PER_PAGE,
            max(1, (int) $request->query('per_page', (string) $metadata->optionsPerPage)),
        );
        $search = trim((string) $request->query('search', ''));
        $selectedValues = $this->scalarValues($request->query('selected'));
        $items = $this->belongsToManyOptionRows($metadata, $user, $search, $page, $perPage, $queryModifier);
        $hasMore = count($items) > $perPage;
        $items = array_slice($items, 0, $perPage);

        if ($selectedValues !== []) {
            $selectedItems = $this->selectedBelongsToManyOptions($metadata, $selectedValues, $user, $queryModifier);
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

    /**
     * @return array<string, mixed>|null
     */
    private function findRelationField(FormContract $form, string $fieldKey): ?array
    {
        foreach ($form->fieldSchema() as $field) {
            if ((string) Arr::get($field, 'key', '') !== $fieldKey) {
                continue;
            }
            if (!$this->isBelongsToField($field) && !$this->isBelongsToManyField($field)) {
                return null;
            }

            return $field;
        }

        return null;
    }

    private function findBelongsToQueryModifier(FormContract $form, string $fieldKey): ?\Closure
    {
        if (!$form instanceof Form) {
            return null;
        }

        $queryModifier = null;

        foreach ($form->fieldNodes() as $field) {
            if (!$field instanceof BelongsTo || $field->key() !== $fieldKey) {
                continue;
            }

            $queryModifier = $field->queryModifier();
        }

        return $queryModifier;
    }

    private function findBelongsToManyQueryModifier(FormContract $form, string $fieldKey): ?\Closure
    {
        if (!$form instanceof Form) {
            return null;
        }

        $queryModifier = null;

        foreach ($form->fieldNodes() as $field) {
            if (!$field instanceof BelongsToMany || $field->key() !== $fieldKey) {
                continue;
            }

            $queryModifier = $field->queryModifier();
        }

        return $queryModifier;
    }

    /**
     * @param array<string, mixed> $field
     */
    private function isBelongsToField(array $field): bool
    {
        $type = (string) Arr::get($field, Field::ATTRIBUTE_TYPE, '');
        $renderer = (string) Arr::get($field, Field::ATTRIBUTE_RENDERER, '');

        return $type === Field::TYPE_BELONGS_TO
            || $renderer === FieldRenderer::RelationSelect->value
            || (
                $type !== Field::TYPE_BELONGS_TO_MANY
                && $renderer !== FieldRenderer::RelationMultiSelect->value
                && Arr::has($field, BelongsTo::ATTRIBUTE_RELATIONSHIP)
            );
    }

    /**
     * @param array<string, mixed> $field
     */
    private function isBelongsToManyField(array $field): bool
    {
        $type = (string) Arr::get($field, Field::ATTRIBUTE_TYPE, '');
        $renderer = (string) Arr::get($field, Field::ATTRIBUTE_RENDERER, '');

        return $type === Field::TYPE_BELONGS_TO_MANY
            || $renderer === FieldRenderer::RelationMultiSelect->value;
    }


    /**
     * @return list<array{label: string, value: string|int|bool, url?: string}>
     */
    private function optionRows(
        BelongsToRelationMetadata $metadata,
        ?\Illuminate\Contracts\Auth\Authenticatable $user,
        string $search,
        int $page,
        int $perPage,
        ?\Closure $queryModifier,
    ): array {
        $query = $this->optionAccess->query($metadata, $queryModifier);

        if ($search !== '' && $metadata->searchColumns !== []) {
            $columns = array_values(array_filter(
                $metadata->searchColumns,
                fn (string $column): bool => $metadata->relatedResource === null || $this->screenAccessResolver->canViewField($metadata->relatedResource, $column, $user),
            ));
            new TypedColumnSearch()->apply($query, $columns, $search);
        }

        $query->orderBy($metadata->titleAttribute)->orderBy($metadata->ownerKey);

        return new AuthorizedOptionPage()->resolve(
            $query,
            $page,
            $perPage,
            fn (Model $item): ?array => $this->optionFromRecord($metadata, $item, $user),
            $metadata->relatedResource !== null && $metadata->relatedResource::policy() !== null,
        );
    }

    /**
     * @param list<string|int|bool> $selectedValues
     *
     * @return list<array{label: string, value: string|int|bool, url?: string}>
     */
    private function selectedOptions(
        BelongsToRelationMetadata $metadata,
        array $selectedValues,
        ?\Illuminate\Contracts\Auth\Authenticatable $user,
        ?\Closure $queryModifier,
    ): array {
        $records = $this->optionAccess->query($metadata, $queryModifier)
            ->whereIn($metadata->ownerKey, $selectedValues)
            ->get()
            ->all();

        return $this->optionsFromRecords($metadata, $records, $user);
    }

    /**
     * @param array $records
     *
     * @return list<array{label: string, value: string|int|bool, url?: string}>
     */
    private function optionsFromRecords(
        BelongsToRelationMetadata $metadata,
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
        BelongsToRelationMetadata $metadata,
        Model $record,
        ?\Illuminate\Contracts\Auth\Authenticatable $user,
    ): ?array {
        if (!$this->optionAccess->canView($metadata, $record, $user)) {
            return null;
        }

        $value = $this->scalarValue(data_get($record, $metadata->ownerKey));
        if ($value === null) {
            return null;
        }

        $option = [
            'label' => $metadata->relatedResource === null
                || $this->screenAccessResolver->canViewField($metadata->relatedResource, $metadata->titleAttribute, $user)
                ? (string) ($this->scalarValue(data_get($record, $metadata->titleAttribute)) ?? $value)
                : (string) $value,
            'value' => $value,
        ];
        $url = $this->detailUrl($metadata, $record, $user);

        if ($url !== null) {
            $option['url'] = $url;
        }

        return $option;
    }

    private function detailUrl(
        BelongsToRelationMetadata $metadata,
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
     * @return list<array{label: string, value: string|int|bool, url?: string}>
     */
    private function belongsToManyOptionRows(
        BelongsToManyRelationMetadata $metadata,
        ?\Illuminate\Contracts\Auth\Authenticatable $user,
        string $search,
        int $page,
        int $perPage,
        ?\Closure $queryModifier,
    ): array {
        $query = $this->optionAccess->query($metadata, $queryModifier);

        if ($search !== '' && $metadata->searchColumns !== []) {
            $columns = array_values(array_filter(
                $metadata->searchColumns,
                fn (string $column): bool => $metadata->relatedResource === null || $this->screenAccessResolver->canViewField($metadata->relatedResource, $column, $user),
            ));
            new TypedColumnSearch()->apply($query, $columns, $search);
        }

        $query->orderBy($metadata->titleAttribute)->orderBy($metadata->relatedKey);

        return new AuthorizedOptionPage()->resolve(
            $query,
            $page,
            $perPage,
            fn (Model $item): ?array => $this->belongsToManyOptionFromRecord($metadata, $item, $user),
            $metadata->relatedResource !== null && $metadata->relatedResource::policy() !== null,
        );
    }

    /**
     * @param list<string|int|bool> $selectedValues
     *
     * @return list<array{label: string, value: string|int|bool, url?: string}>
     */
    private function selectedBelongsToManyOptions(
        BelongsToManyRelationMetadata $metadata,
        array $selectedValues,
        ?\Illuminate\Contracts\Auth\Authenticatable $user,
        ?\Closure $queryModifier,
    ): array {
        $records = $this->optionAccess->query($metadata, $queryModifier)
            ->whereIn($metadata->relatedTable . '.' . $metadata->relatedKey, $selectedValues)
            ->get()
            ->all();

        return $this->belongsToManyOptionsFromRecords($metadata, $records, $user);
    }

    /**
     * @param array $records
     *
     * @return list<array{label: string, value: string|int|bool, url?: string}>
     */
    private function belongsToManyOptionsFromRecords(
        BelongsToManyRelationMetadata $metadata,
        iterable $records,
        ?\Illuminate\Contracts\Auth\Authenticatable $user,
    ): array {
        $items = [];

        foreach ($records as $record) {
            if (!$record instanceof Model) {
                continue;
            }

            $option = $this->belongsToManyOptionFromRecord($metadata, $record, $user);
            if ($option !== null) {
                $items[] = $option;
            }
        }

        return $items;
    }

    /**
     * @return array{label: string, value: string|int|bool, url?: string}|null
     */
    private function belongsToManyOptionFromRecord(
        BelongsToManyRelationMetadata $metadata,
        Model $record,
        ?\Illuminate\Contracts\Auth\Authenticatable $user,
    ): ?array {
        if (!$this->optionAccess->canView($metadata, $record, $user)) {
            return null;
        }

        $value = $this->scalarValue(data_get($record, $metadata->relatedKey));
        if ($value === null) {
            return null;
        }

        $option = [
            'label' => $metadata->relatedResource === null
                || $this->screenAccessResolver->canViewField($metadata->relatedResource, $metadata->titleAttribute, $user)
                ? (string) ($this->scalarValue(data_get($record, $metadata->titleAttribute)) ?? $value)
                : (string) $value,
            'value' => $value,
        ];
        $url = $this->belongsToManyDetailUrl($metadata, $record, $user);
        if ($url !== null) {
            $option['url'] = $url;
        }

        return $option;
    }

    private function belongsToManyDetailUrl(
        BelongsToManyRelationMetadata $metadata,
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

    /**
     * @param list<array{label: string, value: string|int|bool, url?: string}> $items
     */
    private function hasOptionValue(array $items, string|int|bool $value): bool
    {
        return array_any($items, fn($item) => (string) $item['value'] === (string) $value);
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
