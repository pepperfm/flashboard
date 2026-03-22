<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Core\Forms\Normalization;

use Illuminate\Support\Arr;
use Pepperfm\Flashboard\Contracts\Forms\FieldRenderer;
use Pepperfm\Flashboard\Core\Forms\Fields\Field;
use Pepperfm\Flashboard\Support\Schema\SchemaNodeNormalizer;

final class FormSchemaNormalizer
{
    private const string KEY_DEFAULTS = 'defaults';
    private const string KEY_FIELDS = 'fields';
    private const string KEY_RULES = 'rules';
    private const string KEY_SCHEMA = 'schema';
    private const string KEY_SECTIONS = 'sections';
    private const string KEY_TABS = 'tabs';

    /**
     * @param array<string, mixed> $definition
     *
     * @return array<string, mixed>
     */
    public function normalize(array $definition): array
    {
        $sections = $this->normalizeGroups(
            (array) Arr::get($definition, self::KEY_SECTIONS, []),
        );
        $tabs = $this->normalizeGroups(
            (array) Arr::get($definition, self::KEY_TABS, []),
        );
        $fields = $this->normalizeFields(
            (array) Arr::get($definition, self::KEY_FIELDS, []),
        );
        $flattenedFields = array_merge(
            $fields,
            $this->flattenGroupSchema($sections),
            $this->flattenGroupSchema($tabs),
        );
        $deduplicatedFields = $this->deduplicateKeyedNodes($flattenedFields);
        $explicitRules = (array) Arr::get($definition, self::KEY_RULES, []);

        return [
            self::KEY_SECTIONS => $sections,
            self::KEY_TABS => $tabs,
            self::KEY_FIELDS => $deduplicatedFields,
            self::KEY_RULES => $this->mergeRules(
                $this->inferRulesFromFields($deduplicatedFields),
                $explicitRules,
            ),
            self::KEY_DEFAULTS => (array) Arr::get($definition, self::KEY_DEFAULTS, []),
            'has_mutate_data_using' => (bool) Arr::get($definition, 'has_mutate_data_using', false),
            'has_after_save' => (bool) Arr::get($definition, 'has_after_save', false),
        ];
    }

    /**
     * @param list<array<string, mixed>> $groups
     *
     * @return list<array<string, mixed>>
     */
    private function normalizeGroups(array $groups): array
    {
        return array_values(array_map(
            function (array $group): array {
                $schema = Arr::get($group, self::KEY_SCHEMA, []);

                if (! is_array($schema)) {
                    return $group;
                }

                $group[self::KEY_SCHEMA] = $this->normalizeFields($schema);

                return $group;
            },
            SchemaNodeNormalizer::normalizeSchemaGroups($groups),
        ));
    }

    /**
     * @param list<array<string, mixed>> $fields
     *
     * @return list<array<string, mixed>>
     */
    private function normalizeFields(array $fields): array
    {
        return array_values(array_map(
            fn (array $field): array => $this->normalizeFieldRenderer($field),
            SchemaNodeNormalizer::normalizeKeyedNodes($fields),
        ));
    }

    /**
     * @param list<array<string, mixed>> $groups
     *
     * @return list<array<string, mixed>>
     */
    private function flattenGroupSchema(array $groups): array
    {
        $fields = [];

        foreach ($groups as $group) {
            $schema = Arr::get($group, self::KEY_SCHEMA, []);

            if (!is_array($schema)) {
                continue;
            }

            foreach ($schema as $field) {
                if (is_array($field)) {
                    $fields[] = $field;
                }
            }
        }

        return $fields;
    }

    /**
     * @param list<array<string, mixed>> $nodes
     *
     * @return list<array<string, mixed>>
     */
    private function deduplicateKeyedNodes(array $nodes): array
    {
        $deduplicated = [];

        foreach ($nodes as $node) {
            $key = (string) Arr::get($node, 'key', '');

            if ($key === '') {
                $deduplicated[] = $node;
                continue;
            }

            $deduplicated[$key] = $node;
        }

        return array_values($deduplicated);
    }

    /**
     * @param list<array<string, mixed>> $fields
     *
     * @return array<string, list<mixed>>
     */
    private function inferRulesFromFields(array $fields): array
    {
        $rules = [];

        foreach ($fields as $field) {
            $key = trim((string) Arr::get($field, 'key', ''));

            if ($key === '') {
                continue;
            }

            $fieldRules = [];
            $isRequired = (bool) Arr::get($field, Field::ATTRIBUTE_REQUIRED, false);
            $type = (string) Arr::get($field, Field::ATTRIBUTE_TYPE, '');
            $inputType = (string) Arr::get($field, Field::ATTRIBUTE_INPUT_TYPE, '');
            $renderer = $this->resolveFieldRenderer($field);

            $fieldRules[] = $isRequired ? 'required' : 'nullable';

            if ($renderer === FieldRenderer::Input || $renderer === FieldRenderer::Textarea || $inputType === 'email') {
                $fieldRules[] = 'string';
            }

            if ($inputType === 'email') {
                $fieldRules[] = 'email';
            }

            if ($renderer === FieldRenderer::Switch || $type === Field::TYPE_TOGGLE) {
                $fieldRules[] = 'boolean';
            }

            $rules[$key] = array_values(array_unique($fieldRules));
        }

        return $rules;
    }

    /**
     * @param array<string, mixed> $inferredRules
     * @param array<string, mixed> $explicitRules
     *
     * @return array<string, mixed>
     */
    private function mergeRules(array $inferredRules, array $explicitRules): array
    {
        $merged = $inferredRules;

        foreach ($explicitRules as $field => $rules) {
            $inferred = $merged[$field] ?? [];

            if (!is_array($rules)) {
                $rules = [$rules];
            }

            if (!is_array($inferred)) {
                $inferred = [$inferred];
            }

            $merged[$field] = array_values(array_unique(array_merge($inferred, $rules), SORT_REGULAR));
        }

        return $merged;
    }

    /**
     * @param array<string, mixed> $field
     *
     * @return array<string, mixed>
     */
    private function normalizeFieldRenderer(array $field): array
    {
        $field[Field::ATTRIBUTE_RENDERER] = $this->resolveFieldRenderer($field)->value;

        return $field;
    }

    /**
     * @param array<string, mixed> $field
     */
    private function resolveFieldRenderer(array $field): FieldRenderer
    {
        if (Arr::has($field, Field::ATTRIBUTE_RENDERER)) {
            $renderer = FieldRenderer::tryFrom((string) Arr::get($field, Field::ATTRIBUTE_RENDERER, ''));

            if ($renderer !== null) {
                return $renderer;
            }

            throw new \InvalidArgumentException(sprintf(
                'Unknown form field renderer [%s] for field [%s].',
                (string) Arr::get($field, Field::ATTRIBUTE_RENDERER, ''),
                trim((string) Arr::get($field, 'key', '')) ?: 'unknown',
            ));
        }

        return match ((string) Arr::get($field, Field::ATTRIBUTE_TYPE, '')) {
            Field::TYPE_SELECT => FieldRenderer::Select,
            Field::TYPE_TEXTAREA => FieldRenderer::Textarea,
            Field::TYPE_TOGGLE => FieldRenderer::Switch,
            default => FieldRenderer::Input,
        };
    }
}
