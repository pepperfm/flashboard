<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Integration\Laravel\DataSources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Pepperfm\Flashboard\Contracts\Forms\FormContract;
use Pepperfm\Flashboard\Contracts\Forms\FormSchemaNodeKind;
use Pepperfm\Flashboard\Contracts\Resources\Resource;
use Pepperfm\Flashboard\Core\Authorization\Visibility\ScreenAccessResolver;
use Pepperfm\Flashboard\Core\Extensions\ExtensionRegistry;
use Pepperfm\Flashboard\Core\Forms\Builders\Form;
use Pepperfm\Flashboard\Core\Forms\Fields\BelongsTo;
use Pepperfm\Flashboard\Core\Forms\Fields\BelongsToMany;
use Pepperfm\Flashboard\Core\Forms\Relations\BelongsToManyRelationMetadata;
use Pepperfm\Flashboard\Core\Forms\Relations\BelongsToManyRelationMetadataResolver;
use Pepperfm\Flashboard\Core\Forms\Relations\BelongsToRelationMetadata;
use Pepperfm\Flashboard\Core\Forms\Relations\BelongsToRelationMetadataResolver;
use Pepperfm\Flashboard\Core\Registry\ResourceRegistry;
use Pepperfm\Flashboard\Core\Resources\ResourceSurfaceResolver;
use Pepperfm\Flashboard\Integration\Laravel\Relations\RelationOptionAccess;

/*
 * Hydrates relation field metadata and selected options for resource forms.
 */
final readonly class FormRelationMetadata
{
    private RelationOptionAccess $optionAccess;

    public function __construct(
        private FormFieldState $fieldState,
        private ScreenAccessResolver $screenAccessResolver,
        ExtensionRegistry $extensionRegistry,
        private ResourceSurfaceResolver $resourceSurfaceResolver,
        private ResourceRegistry $resourceRegistry,
    ) {
        $this->optionAccess = new RelationOptionAccess($extensionRegistry, $screenAccessResolver);
    }

    /**
     * @param list<array<string, mixed>> $schema
     * @param \Closure(array<string, mixed>): array<string, mixed> $map
     *
     * @return list<array<string, mixed>>
     */
    private function mapFields(array $schema, \Closure $map): array
    {
        return array_values(array_map(function (array $node) use ($map): array {
            $kind = FormSchemaNodeKind::from((string) Arr::get($node, 'kind', FormSchemaNodeKind::Field->value));
            if ($kind === FormSchemaNodeKind::Field) {
                return $map($node);
            }

            $key = $kind === FormSchemaNodeKind::Tabs ? 'tabs' : 'schema';
            $node[$key] = $this->mapFields((array) Arr::get($node, $key, []), $map);

            return $node;
        }, $schema));
    }

    /**
     * @param list<array<string, mixed>> $schema
     * @param class-string<Resource> $resourceClass
     * @param array<string, \Closure> $queryModifiers
     *
     * @return list<array<string, mixed>>
     */
    public function withBelongsToMetadata(
        array $schema,
        string $resourceClass,
        ?Model $record,
        ?\Illuminate\Contracts\Auth\Authenticatable $user,
        array $queryModifiers,
    ): array {
        return $this->mapFields(
            $schema,
            fn (array $node): array => $this->fieldState->isBelongsToField($node)
                ? $this->withBelongsToFieldMetadata($node, $resourceClass, $record, $user, $queryModifiers)
                : $node,
        );
    }

    /**
     * @param array<string, mixed> $field
     * @param class-string<Resource> $resourceClass
     * @param array<string, \Closure> $queryModifiers
     *
     * @return array<string, mixed>
     */
    private function withBelongsToFieldMetadata(
        array $field,
        string $resourceClass,
        ?Model $record,
        ?\Illuminate\Contracts\Auth\Authenticatable $user,
        array $queryModifiers,
    ): array {
        $metadata = $this->belongsToRelationMetadataResolver()->resolve($resourceClass, $field);
        $field = array_merge($field, $metadata->toPayload());
        $key = trim((string) Arr::get($field, 'key', ''));

        if ($key !== '') {
            $field['options_url'] = route(
                config('flashboard.route_name_prefix', 'flashboard.')
                . 'resources.' . $resourceClass::key() . '.relations.options',
                ['field' => $key],
            );
        }
        if ($record !== null) {
            $field['selected_option'] = $this->selectedBelongsToOption(
                $metadata,
                $record,
                $user,
                $queryModifiers[$metadata->fieldKey] ?? null,
            );
        }
        if (
            $metadata->relatedResource !== null
            && $this->screenAccessResolver->canAccessResource($metadata->relatedResource, $user)
            && $this->resourceSurfaceResolver->hasDetailSurfaceForResource($metadata->relatedResource)
        ) {
            $field['related_routes'] = ['detail' => true];
        }

        return $field;
    }

    private function belongsToRelationMetadataResolver(): BelongsToRelationMetadataResolver
    {
        return new BelongsToRelationMetadataResolver($this->resourceRegistry);
    }

    /**
     * @return array{label: string, value: string|int|bool, url?: string}|null
     */
    private function selectedBelongsToOption(
        BelongsToRelationMetadata $metadata,
        Model $record,
        ?\Illuminate\Contracts\Auth\Authenticatable $user,
        ?\Closure $queryModifier,
    ): ?array {
        $selectedValue = $this->optionValue(data_get($record, $metadata->foreignKey));
        if ($selectedValue === null || !$this->optionAccess->canQuery($metadata, $user)) {
            return null;
        }

        $relatedRecord = $this->optionAccess->query($metadata, $queryModifier)
            ->where($metadata->ownerKey, $selectedValue)
            ->first();
        if (!$relatedRecord instanceof Model) {
            return null;
        }

        return $this->belongsToOptionFromRecord($metadata, $relatedRecord, $user);
    }

    /**
     * @return array<string, \Closure>
     */
    public function belongsToQueryModifiers(FormContract $form): array
    {
        if (!$form instanceof Form) {
            return [];
        }

        $modifiers = [];
        foreach ($form->fieldNodes() as $field) {
            if (!$field instanceof BelongsTo || $field->queryModifier() === null) {
                continue;
            }

            $modifiers[$field->key()] = $field->queryModifier();
        }

        return $modifiers;
    }

    /**
     * @return array{label: string, value: string|int|bool, url?: string}|null
     */
    private function belongsToOptionFromRecord(
        BelongsToRelationMetadata $metadata,
        Model $record,
        ?\Illuminate\Contracts\Auth\Authenticatable $user,
    ): ?array {
        if (!$this->optionAccess->canView($metadata, $record, $user)) {
            return null;
        }

        $value = $this->optionValue(data_get($record, $metadata->ownerKey));
        if ($value === null) {
            return null;
        }

        $option = [
            'label' => $metadata->relatedResource === null
                || $this->screenAccessResolver->canViewField($metadata->relatedResource, $metadata->titleAttribute, $user)
                ? (string) ($this->optionValue(data_get($record, $metadata->titleAttribute)) ?? $value)
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
        BelongsToRelationMetadata|BelongsToManyRelationMetadata $metadata,
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
     * @param list<array<string, mixed>> $schema
     * @param class-string<Resource> $resourceClass
     * @param array<string, \Closure> $queryModifiers
     *
     * @return list<array<string, mixed>>
     */
    public function withBelongsToManyMetadata(
        array $schema,
        string $resourceClass,
        ?Model $record,
        ?\Illuminate\Contracts\Auth\Authenticatable $user,
        array $queryModifiers,
    ): array {
        return $this->mapFields(
            $schema,
            fn (array $node): array => $this->fieldState->isBelongsToManyField($node)
                ? $this->withBelongsToManyFieldMetadata($node, $resourceClass, $record, $user, $queryModifiers)
                : $node,
        );
    }

    /**
     * @param array<string, mixed> $field
     * @param class-string<Resource> $resourceClass
     * @param array<string, \Closure> $queryModifiers
     *
     * @return array<string, mixed>
     */
    private function withBelongsToManyFieldMetadata(
        array $field,
        string $resourceClass,
        ?Model $record,
        ?\Illuminate\Contracts\Auth\Authenticatable $user,
        array $queryModifiers,
    ): array {
        $metadata = $this->belongsToManyRelationMetadataResolver()->resolve($resourceClass, $field);
        $field = array_merge($field, $metadata->toPayload());
        $key = trim((string) Arr::get($field, 'key', ''));

        if ($key !== '') {
            $field['options_url'] = route(
                config('flashboard.route_name_prefix', 'flashboard.')
                . 'resources.' . $resourceClass::key() . '.relations.options',
                ['field' => $key],
            );
        }
        $field['selected_options'] = $record === null
            ? []
            : $this->selectedBelongsToManyOptions(
                $metadata,
                $record,
                $user,
                $queryModifiers[$metadata->fieldKey] ?? null,
            );

        if (
            $metadata->relatedResource !== null
            && $this->screenAccessResolver->canAccessResource($metadata->relatedResource, $user)
            && $this->resourceSurfaceResolver->hasDetailSurfaceForResource($metadata->relatedResource)
        ) {
            $field['related_routes'] = ['detail' => true];
        }

        return $field;
    }

    private function belongsToManyRelationMetadataResolver(): BelongsToManyRelationMetadataResolver
    {
        return new BelongsToManyRelationMetadataResolver($this->resourceRegistry);
    }

    /**
     * @return list<array{label: string, value: string|int|bool, url?: string}>
     */
    private function selectedBelongsToManyOptions(
        BelongsToManyRelationMetadata $metadata,
        Model $record,
        ?\Illuminate\Contracts\Auth\Authenticatable $user,
        ?\Closure $queryModifier,
    ): array {
        if (!$this->optionAccess->canQuery($metadata, $user) || !method_exists($record, $metadata->relationship)) {
            return [];
        }

        $relation = $record->{$metadata->relationship}();
        if (!$relation instanceof \Illuminate\Database\Eloquent\Relations\BelongsToMany) {
            return [];
        }

        $selectedValues = [];
        foreach ($relation->get() as $relatedRecord) {
            if (!$relatedRecord instanceof Model) {
                continue;
            }

            $value = $this->optionValue(data_get($relatedRecord, $metadata->relatedKey));
            if ($value !== null) {
                $selectedValues[(string) $value] = $value;
            }
        }

        if ($selectedValues === []) {
            return [];
        }

        $records = $this->optionAccess->query($metadata, $queryModifier)
            ->whereIn($metadata->relatedTable . '.' . $metadata->relatedKey, array_values($selectedValues))
            ->get()
            ->all();

        return $this->belongsToManyOptionsFromRecords($metadata, $records, $user);
    }

    /**
     * @param iterable $records
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

        $value = $this->optionValue(data_get($record, $metadata->relatedKey));
        if ($value === null) {
            return null;
        }

        $option = [
            'label' => $metadata->relatedResource === null
                || $this->screenAccessResolver->canViewField($metadata->relatedResource, $metadata->titleAttribute, $user)
                ? (string) ($this->optionValue(data_get($record, $metadata->titleAttribute)) ?? $value)
                : (string) $value,
            'value' => $value,
        ];
        $url = $this->detailUrl($metadata, $record, $user);

        if ($url !== null) {
            $option['url'] = $url;
        }

        return $option;
    }

    /**
     * @return array<string, \Closure>
     */
    public function belongsToManyQueryModifiers(FormContract $form): array
    {
        if (!$form instanceof Form) {
            return [];
        }

        $modifiers = [];
        foreach ($form->fieldNodes() as $field) {
            if (!$field instanceof BelongsToMany || $field->queryModifier() === null) {
                continue;
            }

            $modifiers[$field->key()] = $field->queryModifier();
        }

        return $modifiers;
    }

    private function optionValue(mixed $value): string|int|bool|null
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
