<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Integration\Laravel\DataSources;

use Illuminate\Support\Arr;
use Pepperfm\Flashboard\Contracts\Forms\FieldRenderer;
use Pepperfm\Flashboard\Core\Forms\Fields\BelongsTo;
use Pepperfm\Flashboard\Core\Forms\Fields\Field;
use Pepperfm\Flashboard\Core\Forms\Fields\RichText;

/*
 * Normalizes authorized form fields and model state for the browser.
 */
final class FormFieldState
{
    /**
     * @param array<string, mixed> $state
     * @param list<array<string, mixed>> $fields
     *
     * @return array<string, mixed>
     */
    public function hydrateRecord(array $state, array $fields, \Illuminate\Database\Eloquent\Model $record): array
    {
        foreach ($fields as $field) {
            $key = (string) $field['key'];
            if ($key === '') {
                continue;
            }
            if ($this->isPasswordField($field) || $this->isFileField($field)) {
                $state[$key] = null;
                continue;
            }
            if ($this->isBelongsToField($field)) {
                $foreignKey = trim((string) Arr::get($field, BelongsTo::ATTRIBUTE_FOREIGN_KEY, $key));
                $state[$key] = data_get($record, $foreignKey === '' ? $key : $foreignKey);
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

        return $state;
    }

    /**
     * @param array<string, mixed> $state
     * @param list<array<string, mixed>> $fields
     *
     * @return array<string, mixed>
     */
    public function applyImplicitFieldDefaults(array $state, array $fields): array
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
    public function normalizeFieldStateValues(array $state, array $fields): array
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
    public function isPasswordField(array $field): bool
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
    public function isFileField(array $field): bool
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
    public function isBelongsToField(array $field): bool
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
    public function isBelongsToManyField(array $field): bool
    {
        $type = (string) Arr::get($field, Field::ATTRIBUTE_TYPE, '');
        $renderer = (string) Arr::get($field, Field::ATTRIBUTE_RENDERER, '');

        return $type === Field::TYPE_BELONGS_TO_MANY
            || $renderer === FieldRenderer::RelationMultiSelect->value;
    }
}
