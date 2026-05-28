<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Integration\Laravel\Persistence;

use Illuminate\Container\Attributes\Singleton;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Pepperfm\Flashboard\Contracts\Forms\FieldRenderer;
use Pepperfm\Flashboard\Contracts\Resources\Resource;
use Pepperfm\Flashboard\Core\Forms\Builders\Form;
use Pepperfm\Flashboard\Core\Forms\Fields\Field;
use Pepperfm\Flashboard\Core\Forms\Fields\FileUpload;
use Pepperfm\Flashboard\Core\Hooks\RuntimeHookDispatcher;

#[Singleton]
final readonly class ResourceFormPersister
{
    private const string REDACTED_VALUE = '[redacted]';

    public function __construct(
        private RuntimeHookDispatcher $runtimeHookDispatcher,
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
        $data = $form->mutateData($payload);
        $data = $resourceClass::mutateFormDataBeforeSave($data, null);
        $data = $this->normalizeFileFieldData($data, $fields, isUpdate: false);
        $modelClass = $resourceClass::model();
        $record = new $modelClass();
        $record->forceFill($data);
        $record->save();

        $form->runAfterSave($record, $data);
        $resourceClass::afterSave($record, $data);
        $this->runtimeHookDispatcher->dispatch($resourceClass, 'resource.create.after', [
            'payload' => $this->safeHookPayload($data, $fields),
            'record' => $this->safeHookRecord($record, $fields),
        ]);

        return $record;
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
        $data = $form->mutateData($payload, $record);
        $data = $resourceClass::mutateFormDataBeforeSave($data, $record);
        $data = $this->normalizeFileFieldData($data, $fields, isUpdate: true);
        $record->forceFill($data);
        $record->save();

        $form->runAfterSave($record, $data);
        $resourceClass::afterSave($record, $data);
        $this->runtimeHookDispatcher->dispatch($resourceClass, 'resource.update.after', [
            'payload' => $this->safeHookPayload($data, $fields),
            'record' => $this->safeHookRecord($record, $fields),
        ]);

        return $record;
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
        return (bool) Arr::get($field, FileUpload::ATTRIBUTE_MULTIPLE, false) ? [] : null;
    }

    private function containsUploadedFile(mixed $value): bool
    {
        if ($value instanceof UploadedFile) {
            return true;
        }

        if (!is_array($value)) {
            return false;
        }

        foreach ($value as $item) {
            if ($this->containsUploadedFile($item)) {
                return true;
            }
        }

        return false;
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
        } catch (\Throwable $exception) {
            logger()->warning('Flashboard file upload storage failed.', [
                'field' => $key,
                'exception' => $exception::class,
            ]);

            throw $exception;
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
