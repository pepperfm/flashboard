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

        return [
            'sections' => $sections,
            'tabs' => $tabs,
            'fields' => $this->deduplicateKeyedNodes($flattenedFields),
            'rules' => (array) Arr::get($definition, 'rules', []),
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
}
