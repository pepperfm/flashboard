<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Core\Detail\Normalization;

use Illuminate\Support\Arr;
use Pepperfm\Flashboard\Support\Schema\SchemaNodeNormalizer;

final class DetailSchemaNormalizer
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
        $entries = SchemaNodeNormalizer::normalizeKeyedNodes(
            (array) Arr::get($definition, 'entries', []),
        );
        $flattenedEntries = array_merge($entries, $this->flattenGroupSchema($sections));

        return [
            'sections' => $sections,
            'entries' => $this->deduplicateKeyedNodes($flattenedEntries),
            'actions' => array_values((array) Arr::get($definition, 'actions', [])),
            'header_actions' => array_values((array) Arr::get($definition, 'header_actions', [])),
        ];
    }

    /**
     * @param list<array<string, mixed>> $groups
     *
     * @return list<array<string, mixed>>
     */
    private function flattenGroupSchema(array $groups): array
    {
        $entries = [];

        foreach ($groups as $group) {
            $schema = Arr::get($group, 'schema', []);

            if (!is_array($schema)) {
                continue;
            }

            foreach ($schema as $entry) {
                if (is_array($entry)) {
                    $entries[] = $entry;
                }
            }
        }

        return $entries;
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
