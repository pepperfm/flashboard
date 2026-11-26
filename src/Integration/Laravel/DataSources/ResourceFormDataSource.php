<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Integration\Laravel\DataSources;

use Illuminate\Container\Attributes\Singleton;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Pepperfm\Flashboard\Contracts\Forms\FormSchemaNodeKind;
use Pepperfm\Flashboard\Contracts\Resources\Resource;
use Pepperfm\Flashboard\Core\Authorization\Visibility\ScreenAccessResolver;
use Pepperfm\Flashboard\Core\Extensions\ExtensionRegistry;
use Pepperfm\Flashboard\Core\Forms\Builders\Form;
use Pepperfm\Flashboard\Core\Registry\ResourceRegistry;
use Pepperfm\Flashboard\Core\Resources\ResourceSurfaceResolver;
use Pepperfm\Flashboard\Core\Runtime\Assemblers\FormPayloadAssembler;
use Pepperfm\Flashboard\Core\Runtime\Payloads\FormPayload;
use Pepperfm\Flashboard\Integration\Laravel\Auth\PanelAuthenticator;
use Pepperfm\Flashboard\Integration\Laravel\Relations\RelationCreateContext;
use Pepperfm\Flashboard\Integration\Laravel\Relations\RelationCreateContextResolver;

#[Singleton]
final readonly class ResourceFormDataSource
{
    private FormFieldState $fieldState;

    private FormRelationMetadata $relationMetadata;

    public function __construct(
        private FormPayloadAssembler $formPayloadAssembler,
        private ScreenAccessResolver $screenAccessResolver,
        private PanelAuthenticator $authenticator,
        private ExtensionRegistry $extensionRegistry,
        private ResourceSurfaceResolver $resourceSurfaceResolver,
        private ResourceRegistry $resourceRegistry,
    ) {
        $this->fieldState = new FormFieldState();
        $this->relationMetadata = new FormRelationMetadata(
            $this->fieldState,
            $screenAccessResolver,
            $extensionRegistry,
            $resourceSurfaceResolver,
            $resourceRegistry,
        );
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
        $belongsToQueryModifiers = $this->relationMetadata->belongsToQueryModifiers($form);
        $belongsToManyQueryModifiers = $this->relationMetadata->belongsToManyQueryModifiers($form);
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

        $filteredSchema = $this->relationMetadata->withBelongsToMetadata($filteredSchema, $resourceClass, $record, $user, $belongsToQueryModifiers);
        $filteredSchema = $this->relationMetadata->withBelongsToManyMetadata($filteredSchema, $resourceClass, $record, $user, $belongsToManyQueryModifiers);

        $fields = $this->flattenFieldNodes($filteredSchema);
        $state = $this->fieldState->applyImplicitFieldDefaults($state, $fields);
        $relationCreateContext = $record === null && $request !== null
            ? $this->relationCreateContext($request, $resourceClass, $user)
            : null;

        if ($relationCreateContext !== null) {
            $state[$relationCreateContext->metadata->foreignKey] = $relationCreateContext->parentRecord->getAttribute(
                $relationCreateContext->metadata->localKey,
            );
        }
        if ($record !== null) {
            $state = $this->fieldState->hydrateRecord($state, $fields, $record);
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

        $state = $this->fieldState->normalizeFieldStateValues($form->mutateData($state), $fields);
        // Defaults / mutateData() may add keys not present in the authorized form.
        // Only serialize state belonging to fields the current user can actually see.
        $state = array_column($fields, 'key')
            |> (static fn($x) => array_fill_keys($x, true))
            |> (static fn($x) => array_intersect_key($state, $x));

        $payload = array_merge($schema->toArray(), [
            'schema' => $filteredSchema,
            'sections' => $this->extractRootSections($filteredSchema),
            'tabs' => $this->extractRootTabs($filteredSchema),
            'fields' => $fields,
            'defaults' => Arr::only($schema->defaults(), array_column(array_filter(
                $fields,
                fn (array $field): bool => !$this->fieldState->isPasswordField($field) && !$this->fieldState->isFileField($field),
            ), 'key')),
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

        return FormPayload::publicMetadata(
            $this->extensionRegistry->extendPayload($resourceClass, $record === null ? 'create' : 'edit', $payload),
        );
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
        if (
            !$this->screenAccessResolver->canEditRecord($context->parentResource, $user, $context->parentRecord)
            || !$this->screenAccessResolver->canViewRelation($context->parentResource, $context->relation, $user)
        ) {
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
            if (!$this->fieldState->isFileField($node)) {
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
