<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Integration\Laravel\Persistence;

use Illuminate\Container\Attributes\Singleton;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Pepperfm\Flashboard\Contracts\Forms\FieldRenderer;
use Pepperfm\Flashboard\Contracts\Forms\FormContract;
use Pepperfm\Flashboard\Contracts\Resources\Resource;
use Pepperfm\Flashboard\Core\Extensions\ExtensionRegistry;
use Pepperfm\Flashboard\Core\Forms\Builders\Form;
use Pepperfm\Flashboard\Core\Forms\Fields\BelongsTo;
use Pepperfm\Flashboard\Core\Forms\Fields\BelongsToMany;
use Pepperfm\Flashboard\Core\Forms\Fields\Field;
use Pepperfm\Flashboard\Core\Forms\Fields\FileUpload;
use Pepperfm\Flashboard\Core\Forms\Relations\BelongsToManyRelationMetadata;
use Pepperfm\Flashboard\Core\Forms\Relations\BelongsToManyRelationMetadataResolver;
use Pepperfm\Flashboard\Core\Hooks\RuntimeHookDispatcher;
use Pepperfm\Flashboard\Core\Registry\ResourceRegistry;
use Pepperfm\Flashboard\Integration\Laravel\Relations\RelationQueryModifier;

#[Singleton]
final readonly class ResourceFormPersister
{
    private const string REDACTED_VALUE = '[redacted]';
    private const string SYNC_FIELD_KEY = 'field_key';
    private const string SYNC_IDS = 'ids';
    private const string SYNC_RELATIONSHIP = 'relationship';

    public function __construct(
        private RuntimeHookDispatcher $runtimeHookDispatcher,
        private ?ResourceRegistry $resourceRegistry = null,
        private ?ExtensionRegistry $extensionRegistry = null,
    ) {
    }

    /**
     * @param class-string<Resource> $resourceClass
     */
    public function create(string $resourceClass, array $payload): Model
    {
        $form = $resourceClass::form(Form::make());
        $fields = $form->fieldSchema();

        $this->runtimeHookDispatcher->dispatch($resourceClass, 'resource.create.before', [
            'payload' => $this->safeHookPayload($payload, $fields),
        ]);

        $payload = $this->withoutEmptyPasswordFields($payload, $fields);
        $payload = $this->withoutPasswordConfirmationFields($payload, $fields);
        $payload = $this->normalizeBelongsToFieldData($payload, $fields);
        $payload = $this->normalizeBelongsToManyFieldData($payload, $fields);
        $data = $form->mutateData($payload);
        $data = $resourceClass::mutateFormDataBeforeSave($data, null);
        $data = $this->normalizeFileFieldData($data, $fields, isUpdate: false);
        [$data, $belongsToManySyncs] = $this->extractBelongsToManySyncs(
            $resourceClass,
            $form,
            $data,
            $fields,
        );
        $modelClass = $resourceClass::model();
        $record = new $modelClass();

        return $record->getConnection()->transaction(function () use ($record, $data, $belongsToManySyncs, $form, $resourceClass, $fields): Model {
            $record->forceFill($data);
            $record->save();

            $this->syncBelongsToManyRelations($record, $belongsToManySyncs);

            $form->runAfterSave($record, $data);
            $resourceClass::afterSave($record, $data);
            $this->runtimeHookDispatcher->dispatch($resourceClass, 'resource.create.after', [
                'payload' => $this->safeHookPayload($data, $fields),
                'record' => $this->safeHookRecord($record, $fields),
            ]);

            return $record;
        });
    }

    /**
     * @param class-string<Resource> $resourceClass
     */
    public function update(string $resourceClass, Model $record, array $payload): Model
    {
        $form = $resourceClass::form(Form::make());
        $fields = $form->fieldSchema();

        $this->runtimeHookDispatcher->dispatch($resourceClass, 'resource.update.before', [
            'payload' => $this->safeHookPayload($payload, $fields),
            'record' => $this->safeHookRecord($record, $fields),
        ]);

        $payload = $this->withoutEmptyPasswordFields($payload, $fields);
        $payload = $this->withoutPasswordConfirmationFields($payload, $fields);
        $payload = $this->normalizeBelongsToFieldData($payload, $fields);
        $payload = $this->normalizeBelongsToManyFieldData($payload, $fields);
        $data = $form->mutateData($payload, $record);
        $data = $resourceClass::mutateFormDataBeforeSave($data, $record);
        $data = $this->normalizeFileFieldData($data, $fields, isUpdate: true);
        [$data, $belongsToManySyncs] = $this->extractBelongsToManySyncs(
            $resourceClass,
            $form,
            $data,
            $fields,
        );

        return $record->getConnection()->transaction(function () use ($record, $data, $belongsToManySyncs, $form, $resourceClass, $fields): Model {
            $record->forceFill($data);
            $record->save();

            $this->syncBelongsToManyRelations($record, $belongsToManySyncs);

            $form->runAfterSave($record, $data);
            $resourceClass::afterSave($record, $data);
            $this->runtimeHookDispatcher->dispatch($resourceClass, 'resource.update.after', [
                'payload' => $this->safeHookPayload($data, $fields),
                'record' => $this->safeHookRecord($record, $fields),
            ]);

            return $record;
        });
    }

    /**
     * @param class-string<Resource> $resourceClass
     */
    public function delete(string $resourceClass, Model $record): void
    {
        $recordKey = $record->getKey();

        $this->runtimeHookDispatcher->dispatch($resourceClass, 'resource.delete.before', [
            'record_key' => $recordKey,
        ]);

        $record->delete();

        $this->runtimeHookDispatcher->dispatch($resourceClass, 'resource.delete.after', [
            'record_key' => $recordKey,
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<array<string, mixed>> $fields
     *
     * @return array<string, mixed>
     */
    private function withoutEmptyPasswordFields(array $payload, array $fields): array
    {
        foreach ($fields as $field) {
            if (!$this->isPasswordField($field)) {
                continue;
            }

            $key = trim((string) Arr::get($field, 'key', ''));

            if ($key === '' || !array_key_exists($key, $payload)) {
                continue;
            }

            if ($payload[$key] === null || $payload[$key] === '') {
                unset($payload[$key]);
            }
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<array<string, mixed>> $fields
     *
     * @return array<string, mixed>
     */
    private function withoutPasswordConfirmationFields(array $payload, array $fields): array
    {
        foreach ($fields as $field) {
            if (!$this->isPasswordField($field)) {
                continue;
            }

            $key = trim((string) Arr::get($field, 'key', ''));

            if ($key === '') {
                continue;
            }

            unset($payload[$key . '_confirmation']);

            if (str_ends_with($key, '_confirmation')) {
                unset($payload[$key]);
            }
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $data
     * @param list<array<string, mixed>> $fields
     *
     * @return array<string, mixed>
     */
    private function normalizeFileFieldData(array $data, array $fields, bool $isUpdate): array
    {
        foreach ($fields as $field) {
            if (!$this->isFileField($field)) {
                continue;
            }

            $key = trim((string) Arr::get($field, 'key', ''));

            if ($key === '') {
                continue;
            }

            $removeKey = $key . FileUpload::REMOVE_REQUEST_SUFFIX;
            $shouldRemove = $this->isTruthyRemoveValue(Arr::get($data, $removeKey, false));
            unset($data[$removeKey]);

            if (!array_key_exists($key, $data)) {
                if ($shouldRemove) {
                    $data[$key] = $this->removedFileFieldValue($field);
                }

                continue;
            }

            if ($this->containsUploadedFile($data[$key])) {
                if ((bool) Arr::get($field, FileUpload::ATTRIBUTE_STORE_FILES, false)) {
                    $data[$key] = $this->storeUploadedFileValue($data[$key], $field);
                    continue;
                }

                unset($data[$key]);
                continue;
            }

            if ($shouldRemove) {
                $data[$key] = $this->removedFileFieldValue($field);
                continue;
            }

            if ($this->isEmptyFileFieldValue($data[$key]) && $isUpdate) {
                unset($data[$key]);
            }
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<array<string, mixed>> $fields
     *
     * @return array<string, mixed>
     */
    private function normalizeBelongsToFieldData(array $payload, array $fields): array
    {
        foreach ($fields as $field) {
            if (!$this->isBelongsToField($field)) {
                continue;
            }

            $key = trim((string) Arr::get($field, 'key', ''));
            if ($key === '' || !array_key_exists($key, $payload)) {
                continue;
            }
            if ($payload[$key] === '') {
                $payload[$key] = null;
            }
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<array<string, mixed>> $fields
     *
     * @return array<string, mixed>
     */
    private function normalizeBelongsToManyFieldData(array $payload, array $fields): array
    {
        foreach ($fields as $field) {
            if (!$this->isBelongsToManyField($field)) {
                continue;
            }

            $key = trim((string) Arr::get($field, 'key', ''));
            if ($key === '' || !array_key_exists($key, $payload)) {
                continue;
            }

            $payload[$key] = $this->normalizeBelongsToManyValues(
                $payload[$key],
                $key,
                $this->positiveIntegerFieldValue($field, BelongsToMany::ATTRIBUTE_MAX_ITEMS),
            );
        }

        return $payload;
    }

    /**
     * @param class-string<Resource> $resourceClass
     * @param array<string, mixed> $data
     * @param list<array<string, mixed>> $fields
     *
     * @return array{0: array<string, mixed>, 1: list<array{field_key: string, relationship: string, ids: list<string|int|bool>}>}
     */
    private function extractBelongsToManySyncs(
        string $resourceClass,
        FormContract $form,
        array $data,
        array $fields,
    ): array {
        $syncs = [];
        $queryModifiers = $this->belongsToManyQueryModifiers($form);
        $resolver = new BelongsToManyRelationMetadataResolver($this->resourceRegistry);

        foreach ($fields as $field) {
            if (!$this->isBelongsToManyField($field)) {
                continue;
            }

            $key = trim((string) Arr::get($field, 'key', ''));
            if ($key === '' || !array_key_exists($key, $data)) {
                continue;
            }

            $metadata = $resolver->resolve($resourceClass, $field);
            $values = $this->normalizeBelongsToManyValues($data[$key], $key, $metadata->maxItems);
            unset($data[$key]);

            $syncs[] = [
                self::SYNC_FIELD_KEY => $key,
                self::SYNC_RELATIONSHIP => $metadata->relationship,
                self::SYNC_IDS => $this->authorizedBelongsToManyIds(
                    $metadata,
                    $values,
                    $queryModifiers[$key] ?? null,
                ),
            ];
        }

        return [$data, $syncs];
    }

    /**
     * @return list<string|int|bool>
     */
    private function normalizeBelongsToManyValues(mixed $value, string $fieldKey, ?int $maxItems): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        $values = is_array($value) ? $value : [$value];
        $normalized = [];

        foreach ($values as $item) {
            if ($item === null || $item === '') {
                continue;
            }
            if (!is_scalar($item) && !$item instanceof \Stringable) {
                throw ValidationException::withMessages([
                    $fieldKey => ['The selected records are invalid.'],
                ]);
            }

            $isIntItem = is_int($item) ? $item : (string) $item;
            $normalizedValue = is_bool($item) ? $item : $isIntItem;
            $normalized[(string) $normalizedValue] = $normalizedValue;
        }

        if ($maxItems !== null && count($normalized) > $maxItems) {
            throw ValidationException::withMessages([
                $fieldKey => ["The selected records may not have more than $maxItems items."],
            ]);
        }

        return array_values($normalized);
    }

    /**
     * @param list<string|int|bool> $values
     *
     * @return list<string|int|bool>
     */
    private function authorizedBelongsToManyIds(
        BelongsToManyRelationMetadata $metadata,
        array $values,
        ?\Closure $queryModifier,
    ): array {
        if ($values === []) {
            return [];
        }

        $records = $this->belongsToManyOptionsQuery($metadata, $queryModifier)
            ->whereIn($metadata->relatedTable . '.' . $metadata->relatedKey, $values)
            ->get()
            ->all();
        $allowedValues = [];

        foreach ($records as $record) {
            if (!$record instanceof Model) {
                continue;
            }

            $value = $this->scalarValue(data_get($record, $metadata->relatedKey));
            if ($value !== null) {
                $allowedValues[(string) $value] = $value;
            }
        }

        $orderedValues = [];
        foreach ($values as $value) {
            if (!array_key_exists((string) $value, $allowedValues)) {
                throw ValidationException::withMessages([
                    $metadata->fieldKey => ['The selected records are invalid.'],
                ]);
            }

            $orderedValues[] = $allowedValues[(string) $value];
        }

        return $orderedValues;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Model>
     */
    private function belongsToManyOptionsQuery(
        BelongsToManyRelationMetadata $metadata,
        ?\Closure $queryModifier,
    ): \Illuminate\Database\Eloquent\Builder {
        if ($metadata->relatedResource !== null) {
            $query = $metadata->relatedResource::query();

            if ($this->extensionRegistry !== null) {
                $query = $this->extensionRegistry->extendQuery($metadata->relatedResource, $query);
            }

            return RelationQueryModifier::apply($queryModifier, $query, $metadata->fieldKey);
        }

        $modelClass = $metadata->relatedModel;

        return RelationQueryModifier::apply($queryModifier, $modelClass::query(), $metadata->fieldKey);
    }

    /**
     * @param list<array{field_key: string, relationship: string, ids: list<string|int|bool>}> $syncs
     */
    private function syncBelongsToManyRelations(Model $record, array $syncs): void
    {
        foreach ($syncs as $sync) {
            if (!method_exists($record, $sync[self::SYNC_RELATIONSHIP])) {
                throw ValidationException::withMessages([
                    $sync[self::SYNC_FIELD_KEY] => ['The selected relationship is invalid.'],
                ]);
            }

            $relation = $record->{$sync[self::SYNC_RELATIONSHIP]}();
            if (!$relation instanceof \Illuminate\Database\Eloquent\Relations\BelongsToMany) {
                throw ValidationException::withMessages([
                    $sync[self::SYNC_FIELD_KEY] => ['The selected relationship is invalid.'],
                ]);
            }

            $relation->sync($sync[self::SYNC_IDS]);
        }
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
    private function isFileField(array $field): bool
    {
        $type = (string) Arr::get($field, Field::ATTRIBUTE_TYPE, '');
        $inputType = (string) Arr::get($field, Field::ATTRIBUTE_INPUT_TYPE, '');
        $renderer = (string) Arr::get($field, Field::ATTRIBUTE_RENDERER, '');

        return $type === Field::TYPE_FILE
            || $inputType === 'file'
            || $renderer === FieldRenderer::FileUpload->value;
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
     * @param array<string, mixed> $field
     */
    private function positiveIntegerFieldValue(array $field, string $key): ?int
    {
        $value = Arr::get($field, $key);

        return is_int($value) && $value > 0 ? $value : null;
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

    private function isEmptyFileFieldValue(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }

    private function isTruthyRemoveValue(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value)) {
            return $value === 1;
        }
        if (is_string($value)) {
            return in_array(strtolower($value), ['1', 'true', 'on', 'yes'], true);
        }

        return false;
    }

    /**
     * @param array<string, mixed> $field
     */
    private function removedFileFieldValue(array $field): mixed
    {
        return Arr::get($field, FileUpload::ATTRIBUTE_MULTIPLE, false) ? [] : null;
    }

    private function containsUploadedFile(mixed $value): bool
    {
        if ($value instanceof UploadedFile) {
            return true;
        }
        if (!is_array($value)) {
            return false;
        }

        return array_any($value, fn($item) => $this->containsUploadedFile($item));
    }

    /**
     * @param array<string, mixed> $field
     */
    private function storeUploadedFileValue(mixed $value, array $field): mixed
    {
        if ($value instanceof UploadedFile) {
            return $this->storeUploadedFile($value, $field);
        }
        if (!is_array($value)) {
            return $value;
        }

        $stored = [];

        foreach ($value as $item) {
            if ($item instanceof UploadedFile) {
                $stored[] = $this->storeUploadedFile($item, $field);
            }
        }

        return $stored;
    }

    /**
     * @param array<string, mixed> $field
     */
    private function storeUploadedFile(UploadedFile $file, array $field): string
    {
        $key = trim((string) Arr::get($field, 'key', ''));
        $directory = trim((string) Arr::get($field, FileUpload::ATTRIBUTE_DIRECTORY, $key));
        $disk = Arr::get($field, FileUpload::ATTRIBUTE_DISK);
        $options = [];

        if (is_string($disk) && $disk !== '') {
            $options['disk'] = $disk;
        }

        try {
            $path = $file->store($directory, $options);

            if ($path === false) {
                throw new \RuntimeException('Uploaded file could not be stored.');
            }

            return $path;
        } catch (\Throwable $e) {
            logger()->warning('Flashboard file upload storage failed.', [
                'field' => $key,
                'exception' => $e::class,
            ]);

            throw $e;
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<array<string, mixed>> $fields
     *
     * @return array<string, mixed>
     */
    private function safeHookPayload(array $payload, array $fields): array
    {
        foreach ($fields as $field) {
            $key = trim((string) Arr::get($field, 'key', ''));

            if ($key === '') {
                continue;
            }
            if ($this->isPasswordField($field)) {
                $payload = $this->redactPasswordPayloadKeys($payload, $key);
                continue;
            }
            if (!array_key_exists($key, $payload)) {
                continue;
            }
            if (!$this->isFileField($field)) {
                continue;
            }
            if ($this->containsUploadedFile($payload[$key])) {
                $payload[$key] = $this->safeUploadedFilePayload($payload[$key]);
                continue;
            }
            if (!$this->isEmptyFileFieldValue($payload[$key])) {
                $payload[$key] = $this->safeStoredFilePayload($payload[$key]);
            }
        }

        return $payload;
    }

    /**
     * @param list<array<string, mixed>> $fields
     *
     * @return array<string, mixed>
     */
    private function safeHookRecord(Model $record, array $fields): array
    {
        return $this->safeHookPayload($record->attributesToArray(), $fields);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function redactPasswordPayloadKeys(array $payload, string $key): array
    {
        if (array_key_exists($key, $payload)) {
            $payload[$key] = $this->redactedPasswordValue($payload[$key]);
        }

        $confirmationKey = $key . '_confirmation';
        if (array_key_exists($confirmationKey, $payload)) {
            $payload[$confirmationKey] = $this->redactedPasswordValue($payload[$confirmationKey]);
        }

        return $payload;
    }

    private function redactedPasswordValue(mixed $value): mixed
    {
        return $value === null || $value === '' ? $value : self::REDACTED_VALUE;
    }

    private function safeUploadedFilePayload(mixed $value): mixed
    {
        if ($value instanceof UploadedFile) {
            return [
                'uploaded' => true,
                'mime_type' => $value->getClientMimeType(),
                'size' => $value->getSize(),
            ];
        }

        if (!is_array($value)) {
            return $value;
        }

        return array_map(fn (mixed $item): mixed => $this->safeUploadedFilePayload($item), $value);
    }

    /**
     * @return array{stored: true, count?: int}
     */
    private function safeStoredFilePayload(mixed $value): array
    {
        if (is_array($value) && array_is_list($value)) {
            return [
                'stored' => true,
                'count' => count($value),
            ];
        }

        return ['stored' => true];
    }
}
