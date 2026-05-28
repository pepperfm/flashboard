<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Integration\Laravel\DataSources;

use Illuminate\Container\Attributes\Singleton;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Pepperfm\Flashboard\Contracts\Forms\FieldRenderer;
use Pepperfm\Flashboard\Contracts\Forms\FormSchemaNodeKind;
use Pepperfm\Flashboard\Contracts\Resources\Resource;
use Pepperfm\Flashboard\Core\Authorization\Visibility\ScreenAccessResolver;
use Pepperfm\Flashboard\Core\Extensions\ExtensionRegistry;
use Pepperfm\Flashboard\Core\Forms\Fields\Field;
use Pepperfm\Flashboard\Core\Forms\Fields\FileUpload;
use Pepperfm\Flashboard\Core\Forms\Fields\RichText;
use Pepperfm\Flashboard\Core\Resources\ResourceSurfaceResolver;
use Pepperfm\Flashboard\Core\Runtime\Assemblers\FormPayloadAssembler;
use Pepperfm\Flashboard\Core\Forms\Builders\Form;
use Pepperfm\Flashboard\Integration\Laravel\Auth\PanelAuthenticator;

#[Singleton]
final readonly class ResourceFormDataSource
{
    public function __construct(
        private FormPayloadAssembler $formPayloadAssembler,
        private ScreenAccessResolver $screenAccessResolver,
        private PanelAuthenticator $authenticator,
        private ExtensionRegistry $extensionRegistry,
        private ResourceSurfaceResolver $resourceSurfaceResolver,
    ) {
    }

    /**
     * @param class-string<Resource> $resourceClass
     *
     * @return array<string, mixed>
     */
    public function resolve(string $resourceClass, ?Model $record = null): array
    {
        $form = $resourceClass::form(Form::make());
        $schema = $this->formPayloadAssembler->assemble($resourceClass);
        $state = $form->defaultState();
        $user = $this->authenticator->user();
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

        $fields = $this->flattenFieldNodes($filteredSchema);
        $state = $this->applyImplicitFieldDefaults($state, $fields);

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

                $state[$key] = data_get($record, $key);
            }
        }
        $cancelUrl = null;

        if ($record === null) {
            $cancelUrl = route(
                config('flashboard.route_name_prefix', 'flashboard.')
                . 'resources.' . $resourceClass::key() . '.index',
            );
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

            $state[$key] = $this->isBooleanField($field) ? false : null;
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
     * @param list<array<string, mixed>> $schema
     *
     * @return list<array<string, mixed>>
     */
    private function withExistingFileMetadata(array $schema, Model $record): array
    {
        return array_values(array_map(
            fn (array $node): array => $this->withExistingFileMetadataForNode($node, $record),
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
                $files = array_merge($files, $this->normalizeExistingFileMetadata($item));
            }

            return $files;
        }

        if (is_array($value)) {
            return [$this->normalizeExistingFileArray($value)];
        }

        if (is_scalar($value) || $value instanceof \Stringable) {
            $path = (string) $value;

            return [[
                'name' => basename($path),
                'path' => $path,
                'url' => null,
            ]];
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
                fn (array $node): ?array => $this->removeGeneratedPrimaryKeyNode($node, $generatedPrimaryKeyName),
                $schema,
            ),
            static fn (?array $node): bool => $node !== null,
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
                    fn (array $tab): ?array => $this->removeGeneratedPrimaryKeyNode($tab, $generatedPrimaryKeyName),
                    (array) Arr::get($node, 'tabs', []),
                ),
                static fn (?array $tab): bool => $tab !== null,
            ));

            if ($tabs === []) {
                return null;
            }

            $node['tabs'] = $tabs;

            return $node;
        }

        $nestedSchema = array_values(array_filter(
            array_map(
                fn (array $childNode): ?array => $this->removeGeneratedPrimaryKeyNode($childNode, $generatedPrimaryKeyName),
                (array) Arr::get($node, 'schema', []),
            ),
            static fn (?array $childNode): bool => $childNode !== null,
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
                $fields = array_merge(
                    $fields,
                    $this->flattenFieldNodes((array) Arr::get($node, 'tabs', [])),
                );

                continue;
            }

            $fields = array_merge(
                $fields,
                $this->flattenFieldNodes((array) Arr::get($node, 'schema', [])),
            );
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
            fn (array $node): bool => Arr::get($node, 'kind') === FormSchemaNodeKind::Section->value,
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
