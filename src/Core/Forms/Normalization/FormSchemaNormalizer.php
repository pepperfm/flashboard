<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Core\Forms\Normalization;

use Illuminate\Support\Arr;
use Pepperfm\Flashboard\Support\Schema\SchemaNodeNormalizer;

final class FormSchemaNormalizer
{
    /**
     * @param array<string, mixed> $definition
     *
     * @return array<string, mixed>
     */
    public function normalize(array $definition): array
    {
        $sections = SchemaNodeNormalizer::normalizeSchemaGroups(
            (array) Arr::get($definition, 'sections', []),
        );
        $tabs = SchemaNodeNormalizer::normalizeSchemaGroups(
            (array) Arr::get($definition, 'tabs', []),
        );
        $fields = SchemaNodeNormalizer::normalizeKeyedNodes(
            (array) Arr::get($definition, 'fields', []),
        );
        $flattenedFields = array_merge(
            $fields,
            $this->flattenGroupSchema($sections),
            $this->flattenGroupSchema($tabs),
        );
        $deduplicatedFields = $this->deduplicateKeyedNodes($flattenedFields);
        $explicitRules = (array) Arr::get($definition, 'rules', []);

        return [
            'sections' => $sections,
            'tabs' => $tabs,
            'fields' => $deduplicatedFields,
            'rules' => $this->mergeRules(
                $this->inferRulesFromFields($deduplicatedFields),
                $explicitRules,
            ),
            'defaults' => (array) Arr::get($definition, 'defaults', []),
            'has_mutate_data_using' => (bool) Arr::get($definition, 'has_mutate_data_using', false),
            'has_after_save' => (bool) Arr::get($definition, 'has_after_save', false),
        ];
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
            $schema = Arr::get($group, 'schema', []);

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
            $isRequired = (bool) Arr::get($field, 'required', false);
            $type = (string) Arr::get($field, 'type', '');
            $inputType = (string) Arr::get($field, 'input_type', '');

            $fieldRules[] = $isRequired ? 'required' : 'nullable';

            if ($type === 'text' || $inputType === 'email') {
                $fieldRules[] = 'string';
            }

            if ($inputType === 'email') {
                $fieldRules[] = 'email';
            }

            if ($type === 'toggle') {
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
}
