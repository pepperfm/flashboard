<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Integration\Laravel\DataSources;

use Illuminate\Container\Attributes\Singleton;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Pepperfm\Flashboard\Contracts\Forms\FieldRenderer;
use Pepperfm\Flashboard\Contracts\Forms\FormContract;
use Pepperfm\Flashboard\Contracts\Forms\FormSchemaNodeKind;
use Pepperfm\Flashboard\Contracts\Resources\Resource;
use Pepperfm\Flashboard\Core\Authorization\Visibility\ScreenAccessResolver;
use Pepperfm\Flashboard\Core\Extensions\ExtensionRegistry;
use Pepperfm\Flashboard\Core\Forms\Builders\Form;
use Pepperfm\Flashboard\Core\Forms\Fields\BelongsTo;
use Pepperfm\Flashboard\Core\Forms\Fields\BelongsToMany;
use Pepperfm\Flashboard\Core\Forms\Fields\Field;
use Pepperfm\Flashboard\Core\Forms\Fields\RichText;
use Pepperfm\Flashboard\Core\Forms\Relations\BelongsToManyRelationMetadata;
use Pepperfm\Flashboard\Core\Forms\Relations\BelongsToManyRelationMetadataResolver;
use Pepperfm\Flashboard\Core\Forms\Relations\BelongsToRelationMetadata;
use Pepperfm\Flashboard\Core\Forms\Relations\BelongsToRelationMetadataResolver;
use Pepperfm\Flashboard\Core\Registry\ResourceRegistry;
use Pepperfm\Flashboard\Core\Resources\ResourceSurfaceResolver;
use Pepperfm\Flashboard\Core\Runtime\Assemblers\FormPayloadAssembler;
use Pepperfm\Flashboard\Integration\Laravel\Auth\PanelAuthenticator;
use Pepperfm\Flashboard\Integration\Laravel\Relations\RelationCreateContext;
use Pepperfm\Flashboard\Integration\Laravel\Relations\RelationCreateContextResolver;
use Pepperfm\Flashboard\Integration\Laravel\Relations\RelationQueryModifier;

#[Singleton]
final readonly class ResourceFormDataSource
{
    public function __construct(
        private FormPayloadAssembler $formPayloadAssembler,
        private ScreenAccessResolver $screenAccessResolver,
        private PanelAuthenticator $authenticator,
        private ExtensionRegistry $extensionRegistry,
        private ResourceSurfaceResolver $resourceSurfaceResolver,
        private ResourceRegistry $resourceRegistry,
    ) {
    }

    /**
     * @param class-string<Resource> $resourceClass
     *
     * @return array<string, mixed>
     */
    public function resolve(
        string $resourceClass,
        ?Model $record = null,
        ?\Illuminate\Http\Request $request = null
    ): array {
        $form = $resourceClass::form(Form::make());
        $schema = $this->formPayloadAssembler->assemble($resourceClass);
        $state = $form->defaultState();
        $user = $this->authenticator->user();
        $belongsToQueryModifiers = $this->belongsToQueryModifiers($form);
        $belongsToManyQueryModifiers = $this->belongsToManyQueryModifiers($form);
        $filteredSchema = $this->filterSchemaNodes(
            $schema->schema(),
            $resourceClass,
            $user,
        );
        if ($record === null) {
            $filteredSchema = $this->removeGeneratedPrimaryKeyNodes($filteredSchema, $resourceClass);
        } else {
            $filteredSchema = $this->withExistingFileMetadata($filteredSchema, $record);
        }

        $filteredSchema = $this->withBelongsToMetadata($filteredSchema, $resourceClass, $record, $user, $belongsToQueryModifiers);
        $filteredSchema = $this->withBelongsToManyMetadata($filteredSchema, $resourceClass, $record, $user, $belongsToManyQueryModifiers);

        $fields = $this->flattenFieldNodes($filteredSchema);
        $state = $this->applyImplicitFieldDefaults($state, $fields);
        $relationCreateContext = $record === null && $request !== null
            ? $this->relationCreateContext($request, $resourceClass, $user)
            : null;

        if ($relationCreateContext !== null) {
            $state[$relationCreateContext->metadata->foreignKey] = $relationCreateContext->parentRecord->getAttribute(
                $relationCreateContext->metadata->localKey,
            );
        }
        if ($record !== null) {
            foreach ($fields as $field) {
                $key = (string) $field['key'];
                if ($key === '') {
                    continue;
                }
                if ($this->isPasswordField($field) || $this->isFileField($field)) {
                    $state[$key] = null;
                    continue;
                }
                if ($this->isBelongsToManyField($field)) {
                    $state[$key] = array_values(array_map(
                        static fn(array $option): string|int|bool => $option['value'],
                        (array) Arr::get($field, 'selected_options', []),
                    ));
                    continue;
                }

                $state[$key] = data_get($record, $key);
            }
        }
        if ($record === null) {
            $cancelUrl = $relationCreateContext === null
                ? route(
                    config('flashboard.route_name_prefix', 'flashboard.')
                    . 'resources.' . $resourceClass::key() . '.index',
                )
                : $this->parentRecordUrl($relationCreateContext);
        } elseif ($this->resourceSurfaceResolver->hasDetailSurfaceForResource($resourceClass)) {
            $cancelUrl = route(
                config('flashboard.route_name_prefix', 'flashboard.')
                . 'resources.' . $resourceClass::key() . '.detail',
                ['record' => $record->getKey()],
            );
        } else {
            $cancelUrl = route(
                config('flashboard.route_name_prefix', 'flashboard.')
                . 'resources.' . $resourceClass::key() . '.index',
            );
        }

        $state = $this->normalizeFieldStateValues($form->mutateData($state), $fields);

        $payload = array_merge($schema->toArray(), [
            'schema' => $filteredSchema,
            'sections' => $this->extractRootSections($filteredSchema),
            'tabs' => $this->extractRootTabs($filteredSchema),
            'fields' => $fields,
            'rules' => $record === null ? $resourceClass::creationRules() : $resourceClass::updateRules($record),
            'state' => $state,
            'mode' => $record === null ? 'create' : 'edit',
            'submit' => [
                'method' => $record === null ? 'post' : 'put',
                'url' => $record === null
                    ? route(
                        config('flashboard.route_name_prefix', 'flashboard.')
                        . 'resources.' . $resourceClass::key() . '.store',
                        $relationCreateContext === null ? [] : [
                            'parent_resource' => $relationCreateContext->parentResource::key(),
                            'parent_record' => $relationCreateContext->parentRecord->getKey(),
                            'relation' => $relationCreateContext->relation,
                        ],
                    )
                    : route(
                        config('flashboard.route_name_prefix', 'flashboard.')
                        . 'resources.' . $resourceClass::key() . '.update',
                        ['record' => $record->getKey()],
                    ),
            ],
            'cancel' => [
                'url' => $cancelUrl,
            ],
        ]);

        return $this->extensionRegistry->extendPayload($resourceClass, $record === null ? 'create' : 'edit', $payload);
    }

    /**
     * @param class-string<Resource> $resourceClass
     */
    private function relationCreateContext(
        \Illuminate\Http\Request $request,
        string $resourceClass,
        ?\Illuminate\Contracts\Auth\Authenticatable $user,
    ): ?RelationCreateContext {
        $context = new RelationCreateContextResolver($this->resourceRegistry)->resolve($request, $resourceClass);

        if ($context === null) {
            return null;
        }
        if (!$this->screenAccessResolver->canViewRecord($context->parentResource, $user, $context->parentRecord)) {
            throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException();
        }

        return $context;
    }

    private function parentRecordUrl(RelationCreateContext $context): string
    {
        if ($this->resourceSurfaceResolver->hasDetailSurfaceForResource($context->parentResource)) {
            return route(
                config('flashboard.route_name_prefix', 'flashboard.')
                . 'resources.' . $context->parentResource::key() . '.detail',
                ['record' => $context->parentRecord->getKey()],
            );
        }

        return route(
            config('flashboard.route_name_prefix', 'flashboard.')
            . 'resources.' . $context->parentResource::key() . '.edit',
            ['record' => $context->parentRecord->getKey()],
        );
    }

    /**
     * @param array<string, mixed> $state
     * @param list<array<string, mixed>> $fields
     *
     * @return array<string, mixed>
     */
    private function applyImplicitFieldDefaults(array $state, array $fields): array
    {
        foreach ($fields as $field) {
            $key = trim((string) Arr::get($field, 'key', ''));
            if ($key === '' || array_key_exists($key, $state)) {
                continue;
            }

            $state[$key] = match (true) {
                $this->isBooleanField($field) => false,
                $this->isBelongsToManyField($field) => [],
                default => null,
            };
        }

        return $state;
    }

    /**
     * @param array<string, mixed> $state
     * @param list<array<string, mixed>> $fields
     *
     * @return array<string, mixed>
     */
    private function normalizeFieldStateValues(array $state, array $fields): array
    {
        foreach ($fields as $field) {
            $key = trim((string) Arr::get($field, 'key', ''));
            if ($key === '' || !array_key_exists($key, $state)) {
                continue;
            }
            if ($this->isDateField($field)) {
                $state[$key] = $this->normalizeDateFieldValue($state[$key]);
                continue;
            }
            if ($this->isJsonRichTextField($field)) {
                $state[$key] = $this->normalizeJsonRichTextFieldValue($state[$key]);
                continue;
            }
            if ($this->isStringField($field)) {
                $state[$key] = $this->normalizeStringFieldValue($state[$key]);
            }
        }

        return $state;
    }

    /**
     * @param array<string, mixed> $field
     */
    private function isStringField(array $field): bool
    {
        $type = (string) Arr::get($field, Field::ATTRIBUTE_TYPE, '');
        $renderer = (string) Arr::get($field, Field::ATTRIBUTE_RENDERER, '');
        $contentFormat = (string) Arr::get($field, RichText::ATTRIBUTE_CONTENT_FORMAT, RichText::FORMAT_HTML);

        return $type === Field::TYPE_TEXT
            || $type === Field::TYPE_TEXTAREA
            || $type === Field::TYPE_PASSWORD
            || ($type === Field::TYPE_RICH_TEXT && $contentFormat !== RichText::FORMAT_JSON)
            || $renderer === FieldRenderer::Input->value
            || $renderer === FieldRenderer::Textarea->value
            || ($renderer === FieldRenderer::RichText->value && $contentFormat !== RichText::FORMAT_JSON);
    }

    private function normalizeStringFieldValue(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }
        if ($value instanceof \BackedEnum) {
            return (string) $value->value;
        }
        if (is_scalar($value) || $value instanceof \Stringable) {
            return (string) $value;
        }

        return $value;
    }

    private function normalizeDateFieldValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        if (is_scalar($value) || $value instanceof \Stringable) {
            $value = trim((string) $value);

            return $value === '' ? null : substr($value, 0, 10);
        }

        return null;
    }

    private function normalizeJsonRichTextFieldValue(mixed $value): mixed
    {
        if ($value === null || is_array($value)) {
            return $value;
        }
        if (is_string($value)) {
            $decoded = json_decode($value, true);

            return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $field
     */
    private function isBooleanField(array $field): bool
    {
        $type = (string) Arr::get($field, Field::ATTRIBUTE_TYPE, '');
        $renderer = (string) Arr::get($field, Field::ATTRIBUTE_RENDERER, '');

        return $type === Field::TYPE_CHECKBOX
            || $type === Field::TYPE_TOGGLE
            || $renderer === FieldRenderer::Checkbox->value
            || $renderer === FieldRenderer::Switch->value;
    }

    /**
     * @param array<string, mixed> $field
     */
    private function isDateField(array $field): bool
    {
        $type = (string) Arr::get($field, Field::ATTRIBUTE_TYPE, '');
        $renderer = (string) Arr::get($field, Field::ATTRIBUTE_RENDERER, '');
        $inputType = (string) Arr::get($field, Field::ATTRIBUTE_INPUT_TYPE, '');

        return $type === Field::TYPE_DATE
            || $renderer === FieldRenderer::Date->value
            || $inputType === 'date';
    }

    /**
     * @param array<string, mixed> $field
     */
    private function isPasswordField(array $field): bool
    {
        $type = (string) Arr::get($field, Field::ATTRIBUTE_TYPE, '');
        $inputType = (string) Arr::get($field, Field::ATTRIBUTE_INPUT_TYPE, '');

        return $type === Field::TYPE_PASSWORD || $inputType === 'password';
    }

    /**
     * @param array<string, mixed> $field
     */
    private function isJsonRichTextField(array $field): bool
    {
        $type = (string) Arr::get($field, Field::ATTRIBUTE_TYPE, '');
        $renderer = (string) Arr::get($field, Field::ATTRIBUTE_RENDERER, '');
        $contentFormat = (string) Arr::get($field, RichText::ATTRIBUTE_CONTENT_FORMAT, RichText::FORMAT_HTML);

        return $contentFormat === RichText::FORMAT_JSON
            && ($type === Field::TYPE_RICH_TEXT || $renderer === FieldRenderer::RichText->value);
    }

    /**
     * @param array<string, mixed> $field
     */
    private function isFileField(array $field): bool
    {
        $type = (string) Arr::get($field, Field::ATTRIBUTE_TYPE, '');
        $renderer = (string) Arr::get($field, Field::ATTRIBUTE_RENDERER, '');
        $inputType = (string) Arr::get($field, Field::ATTRIBUTE_INPUT_TYPE, '');

        return $type === Field::TYPE_FILE
            || $renderer === FieldRenderer::FileUpload->value
            || $inputType === 'file';
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
     * @param list<array<string, mixed>> $schema
     * @param class-string<Resource> $resourceClass
     * @param array<string, \Closure> $queryModifiers
     *
     * @return list<array<string, mixed>>
     */
    private function withBelongsToMetadata(
        array $schema,
        string $resourceClass,
        ?Model $record,
        ?\Illuminate\Contracts\Auth\Authenticatable $user,
        array $queryModifiers,
    ): array {
        return array_values(array_map(
            fn(array $node): array => $this->withBelongsToMetadataForNode($node, $resourceClass, $record, $user, $queryModifiers),
            $schema,
        ));
    }

    /**
     * @param array<string, mixed> $node
     * @param class-string<Resource> $resourceClass
     * @param array<string, \Closure> $queryModifiers
     *
     * @return array<string, mixed>
     */
    private function withBelongsToMetadataForNode(
        array $node,
        string $resourceClass,
        ?Model $record,
        ?\Illuminate\Contracts\Auth\Authenticatable $user,
        array $queryModifiers,
    ): array {
        $kind = FormSchemaNodeKind::from((string) Arr::get($node, 'kind', FormSchemaNodeKind::Field->value));
        if ($kind === FormSchemaNodeKind::Field) {
            if (!$this->isBelongsToField($node)) {
                return $node;
            }

            return $this->withBelongsToFieldMetadata($node, $resourceClass, $record, $user, $queryModifiers);
        }
        if ($kind === FormSchemaNodeKind::Tabs) {
            $node['tabs'] = $this->withBelongsToMetadata(
                (array) Arr::get($node, 'tabs', []),
                $resourceClass,
                $record,
                $user,
                $queryModifiers,
            );

            return $node;
        }

        $node['schema'] = $this->withBelongsToMetadata(
            (array) Arr::get($node, 'schema', []),
            $resourceClass,
            $record,
            $user,
            $queryModifiers,
        );

        return $node;
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
        $selectedValue = $this->optionValue(data_get($record, $metadata->fieldKey));
        if ($selectedValue === null || !$this->canQueryBelongsToOptions($metadata, $user)) {
            return null;
        }

        $relatedRecord = $this->belongsToOptionsQuery($metadata, $queryModifier)
            ->where($metadata->ownerKey, $selectedValue)
            ->first();
        if (!$relatedRecord instanceof Model) {
            return null;
        }

        return $this->belongsToOptionFromRecord($metadata, $relatedRecord, $user);
    }

    private function canQueryBelongsToOptions(
        BelongsToRelationMetadata $metadata,
        ?\Illuminate\Contracts\Auth\Authenticatable $user,
    ): bool {
        if ($metadata->relatedResource === null) {
            return $metadata->allowModelFallback;
        }

        return $this->screenAccessResolver->canAccessResource($metadata->relatedResource, $user);
    }

    private function belongsToOptionsQuery(
        BelongsToRelationMetadata $metadata,
        ?\Closure $queryModifier,
    ): Builder
    {
        if ($metadata->relatedResource !== null) {
            $query = $this->extensionRegistry->extendQuery(
                $metadata->relatedResource,
                $metadata->relatedResource::query(),
            );

            return RelationQueryModifier::apply($queryModifier, $query, $metadata->fieldKey);
        }

        $modelClass = $metadata->relatedModel;

        return RelationQueryModifier::apply($queryModifier, $modelClass::query(), $metadata->fieldKey);
    }

    /**
     * @return array<string, \Closure>
     */
    private function belongsToQueryModifiers(FormContract $form): array
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
        $value = $this->optionValue(data_get($record, $metadata->ownerKey));
        if ($value === null) {
            return null;
        }

        $option = [
            'label' => (string) ($this->optionValue(data_get($record, $metadata->titleAttribute)) ?? $value),
            'value' => $value,
        ];
        $url = $this->belongsToDetailUrl($metadata, $record, $user);

        if ($url !== null) {
            $option['url'] = $url;
        }

        return $option;
    }

    private function belongsToDetailUrl(
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
    private function withBelongsToManyMetadata(
        array $schema,
        string $resourceClass,
        ?Model $record,
        ?\Illuminate\Contracts\Auth\Authenticatable $user,
        array $queryModifiers,
    ): array {
        return array_values(array_map(
            fn(array $node): array => $this->withBelongsToManyMetadataForNode($node, $resourceClass, $record, $user, $queryModifiers),
            $schema,
        ));
    }

    /**
     * @param array<string, mixed> $node
     * @param class-string<Resource> $resourceClass
     * @param array<string, \Closure> $queryModifiers
     *
     * @return array<string, mixed>
     */
    private function withBelongsToManyMetadataForNode(
        array $node,
        string $resourceClass,
        ?Model $record,
        ?\Illuminate\Contracts\Auth\Authenticatable $user,
        array $queryModifiers,
    ): array {
        $kind = FormSchemaNodeKind::from((string) Arr::get($node, 'kind', FormSchemaNodeKind::Field->value));
        if ($kind === FormSchemaNodeKind::Field) {
            if (!$this->isBelongsToManyField($node)) {
                return $node;
            }

            return $this->withBelongsToManyFieldMetadata($node, $resourceClass, $record, $user, $queryModifiers);
        }
        if ($kind === FormSchemaNodeKind::Tabs) {
            $node['tabs'] = $this->withBelongsToManyMetadata(
                (array) Arr::get($node, 'tabs', []),
                $resourceClass,
                $record,
                $user,
                $queryModifiers,
            );

            return $node;
        }

        $node['schema'] = $this->withBelongsToManyMetadata(
            (array) Arr::get($node, 'schema', []),
            $resourceClass,
            $record,
            $user,
            $queryModifiers,
        );

        return $node;
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
        if (!$this->canQueryBelongsToManyOptions($metadata, $user) || !method_exists($record, $metadata->relationship)) {
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

        $records = $this->belongsToManyOptionsQuery($metadata, $queryModifier)
            ->whereIn($metadata->relatedTable . '.' . $metadata->relatedKey, array_values($selectedValues))
            ->get()
            ->all();

        return $this->belongsToManyOptionsFromRecords($metadata, $records, $user);
    }

    private function canQueryBelongsToManyOptions(
        BelongsToManyRelationMetadata $metadata,
        ?\Illuminate\Contracts\Auth\Authenticatable $user,
    ): bool {
        if ($metadata->relatedResource === null) {
            return $metadata->allowModelFallback;
        }

        return $this->screenAccessResolver->canAccessResource($metadata->relatedResource, $user);
    }

    private function belongsToManyOptionsQuery(
        BelongsToManyRelationMetadata $metadata,
        ?\Closure $queryModifier,
    ): Builder {
        if ($metadata->relatedResource !== null) {
            $query = $this->extensionRegistry->extendQuery(
                $metadata->relatedResource,
                $metadata->relatedResource::query(),
            );

            return RelationQueryModifier::apply($queryModifier, $query, $metadata->fieldKey);
        }

        $modelClass = $metadata->relatedModel;

        return RelationQueryModifier::apply($queryModifier, $modelClass::query(), $metadata->fieldKey);
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
        $value = $this->optionValue(data_get($record, $metadata->relatedKey));
        if ($value === null) {
            return null;
        }

        $option = [
            'label' => (string) ($this->optionValue(data_get($record, $metadata->titleAttribute)) ?? $value),
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
     * @return array<string, \Closure>
     */
    private function belongsToManyQueryModifiers(FormContract $form): array
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

    /**
     * @param list<array<string, mixed>> $schema
     *
     * @return list<array<string, mixed>>
     */
    private function withExistingFileMetadata(array $schema, Model $record): array
    {
        return array_values(array_map(
            fn(array $node): array => $this->withExistingFileMetadataForNode($node, $record),
            $schema,
        ));
    }

    /**
     * @param array<string, mixed> $node
     *
     * @return array<string, mixed>
     */
    private function withExistingFileMetadataForNode(array $node, Model $record): array
    {
        $kind = FormSchemaNodeKind::from((string) Arr::get($node, 'kind', FormSchemaNodeKind::Field->value));
        if ($kind === FormSchemaNodeKind::Field) {
            if (!$this->isFileField($node)) {
                return $node;
            }

            $key = trim((string) Arr::get($node, 'key', ''));
            $node['existing_files'] = $key === ''
                ? []
                : $this->normalizeExistingFileMetadata(data_get($record, $key));

            return $node;
        }

        if ($kind === FormSchemaNodeKind::Tabs) {
            $node['tabs'] = $this->withExistingFileMetadata((array) Arr::get($node, 'tabs', []), $record);

            return $node;
        }

        $node['schema'] = $this->withExistingFileMetadata((array) Arr::get($node, 'schema', []), $record);

        return $node;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function normalizeExistingFileMetadata(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }
        if (is_array($value) && array_is_list($value)) {
            $files = [];

            foreach ($value as $item) {
                foreach ($this->normalizeExistingFileMetadata($item) as $file) {
                    $files[] = $file;
                }
            }

            return $files;
        }
        if (is_array($value)) {
            return [$this->normalizeExistingFileArray($value)];
        }
        if (is_scalar($value) || $value instanceof \Stringable) {
            $path = (string) $value;

            return [
                [
                    'name' => basename($path),
                    'path' => $path,
                    'url' => null,
                ],
            ];
        }

        return [];
    }

    /**
     * @param array<string, mixed> $value
     *
     * @return array<string, mixed>
     */
    private function normalizeExistingFileArray(array $value): array
    {
        $path = Arr::get($value, 'path', Arr::get($value, 'url'));
        $name = Arr::get($value, 'name');

        if (!is_scalar($name) && !$name instanceof \Stringable) {
            $name = is_scalar($path) || $path instanceof \Stringable ? basename((string) $path) : null;
        }

        return [
            'name' => $name === null ? null : (string) $name,
            'path' => is_scalar($path) || $path instanceof \Stringable ? (string) $path : null,
            'url' => is_scalar(Arr::get($value, 'url')) || Arr::get($value, 'url') instanceof \Stringable
                ? (string) Arr::get($value, 'url')
                : null,
        ];
    }

    /**
     * @param list<array<string, mixed>> $schema
     *
     * @return list<array<string, mixed>>
     */
    private function filterSchemaNodes(
        array $schema,
        string $resourceClass,
        ?\Illuminate\Contracts\Auth\Authenticatable $user,
    ): array {
        $filtered = [];

        foreach ($schema as $node) {
            $normalizedNode = $this->filterSchemaNode($node, $resourceClass, $user);

            if ($normalizedNode !== null) {
                $filtered[] = $normalizedNode;
            }
        }

        return $filtered;
    }

    /**
     * @param list<array<string, mixed>> $schema
     * @param class-string<Resource> $resourceClass
     *
     * @return list<array<string, mixed>>
     */
    private function removeGeneratedPrimaryKeyNodes(array $schema, string $resourceClass): array
    {
        $generatedPrimaryKeyName = $resourceClass::generatedPrimaryKeyName();

        if ($generatedPrimaryKeyName === null) {
            return $schema;
        }

        return array_values(array_filter(
            array_map(
                fn(array $node): ?array => $this->removeGeneratedPrimaryKeyNode($node, $generatedPrimaryKeyName),
                $schema,
            ),
            static fn(?array $node): bool => $node !== null,
        ));
    }

    /**
     * @param array<string, mixed> $node
     *
     * @return array<string, mixed>|null
     */
    private function removeGeneratedPrimaryKeyNode(array $node, string $generatedPrimaryKeyName): ?array
    {
        $kind = FormSchemaNodeKind::from((string) Arr::get($node, 'kind', FormSchemaNodeKind::Field->value));

        if ($kind === FormSchemaNodeKind::Field) {
            return (string) Arr::get($node, 'key', '') === $generatedPrimaryKeyName ? null : $node;
        }

        if ($kind === FormSchemaNodeKind::Tabs) {
            $tabs = array_values(array_filter(
                array_map(
                    fn(array $tab): ?array => $this->removeGeneratedPrimaryKeyNode($tab, $generatedPrimaryKeyName),
                    (array) Arr::get($node, 'tabs', []),
                ),
                static fn(?array $tab): bool => $tab !== null,
            ));

            if ($tabs === []) {
                return null;
            }

            $node['tabs'] = $tabs;

            return $node;
        }

        $nestedSchema = array_values(array_filter(
            array_map(
                fn(array $childNode): ?array => $this->removeGeneratedPrimaryKeyNode($childNode, $generatedPrimaryKeyName),
                (array) Arr::get($node, 'schema', []),
            ),
            static fn(?array $childNode): bool => $childNode !== null,
        ));

        if ($nestedSchema === []) {
            return null;
        }

        $node['schema'] = $nestedSchema;

        return $node;
    }

    /**
     * @param array<string, mixed> $node
     *
     * @return array<string, mixed>|null
     */
    private function filterSchemaNode(
        array $node,
        string $resourceClass,
        ?\Illuminate\Contracts\Auth\Authenticatable $user,
    ): ?array {
        $kind = FormSchemaNodeKind::from((string) Arr::get($node, 'kind', FormSchemaNodeKind::Field->value));

        if ($kind === FormSchemaNodeKind::Field) {
            $key = trim((string) Arr::get($node, 'key', ''));
            if ($key === '') {
                return $node;
            }

            return $this->screenAccessResolver->canViewField($resourceClass, $key, $user)
                ? $node
                : null;
        }
        if ($kind === FormSchemaNodeKind::Tabs) {
            $tabs = $this->filterSchemaNodes(
                (array) Arr::get($node, 'tabs', []),
                $resourceClass,
                $user,
            );
            if ($tabs === []) {
                return null;
            }

            $node['tabs'] = $tabs;

            return $node;
        }

        $schema = $this->filterSchemaNodes(
            (array) Arr::get($node, 'schema', []),
            $resourceClass,
            $user,
        );
        if ($schema === []) {
            return null;
        }

        $node['schema'] = $schema;

        return $node;
    }

    /**
     * @param list<array<string, mixed>> $schema
     *
     * @return list<array<string, mixed>>
     */
    private function flattenFieldNodes(array $schema): array
    {
        $fields = [];
        foreach ($schema as $node) {
            $kind = FormSchemaNodeKind::from((string) Arr::get($node, 'kind', FormSchemaNodeKind::Field->value));

            if ($kind === FormSchemaNodeKind::Field) {
                $fields[] = $node;
                continue;
            }
            if ($kind === FormSchemaNodeKind::Tabs) {
                foreach ($this->flattenFieldNodes((array) Arr::get($node, 'tabs', [])) as $field) {
                    $fields[] = $field;
                }

                continue;
            }

            foreach ($this->flattenFieldNodes((array) Arr::get($node, 'schema', [])) as $field) {
                $fields[] = $field;
            }
        }

        return $fields;
    }

    /**
     * @param list<array<string, mixed>> $schema
     *
     * @return list<array<string, mixed>>
     */
    private function extractRootSections(array $schema): array
    {
        return array_values(array_filter(
            $schema,
            static fn(array $node): bool => Arr::get($node, 'kind') === FormSchemaNodeKind::Section->value,
        ));
    }

    /**
     * @param list<array<string, mixed>> $schema
     *
     * @return list<array<string, mixed>>
     */
    private function extractRootTabs(array $schema): array
    {
        $tabs = [];
        foreach ($schema as $node) {
            if (Arr::get($node, 'kind') !== FormSchemaNodeKind::Tabs->value) {
                continue;
            }

            foreach ((array) Arr::get($node, 'tabs', []) as $tab) {
                if (is_array($tab)) {
                    $tabs[] = $tab;
                }
            }
        }

        return $tabs;
    }
}
