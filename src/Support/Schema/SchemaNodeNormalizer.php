<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Support\Schema;

use Pepperfm\Flashboard\Contracts\Schema\KeyedSchemaNodeContract;
use Pepperfm\Flashboard\Contracts\Schema\SchemaNodeContract;

final class SchemaNodeNormalizer
{
    /**
     * @param array<string, mixed>|SchemaNodeContract $node
     *
     * @return array<string, mixed>
     */
    public static function normalizeNode(array|SchemaNodeContract $node): array
    {
        return $node instanceof SchemaNodeContract ? $node->toArray() : $node;
    }

    /**
     * @param array<string, mixed>|KeyedSchemaNodeContract $node
     *
     * @return array<string, mixed>
     */
    public static function normalizeKeyedNode(array|KeyedSchemaNodeContract $node, string $fallbackKey = ''): array
    {
        $normalized = self::normalizeNode($node);
        $key = trim((string) ($normalized['key'] ?? $normalized['name'] ?? ''));

        if ($key === '' && isset($normalized['label']) && is_string($normalized['label'])) {
            $key = self::keyFromLabel($normalized['label']);
        }

        if ($key === '' && $fallbackKey !== '') {
            $key = $fallbackKey;
        }

        unset($normalized['name']);

        if ($key !== '') {
            $normalized['key'] = $key;
        }

        return $normalized;
    }

    /**
     * @param list<array<string, mixed>|KeyedSchemaNodeContract> $nodes
     *
     * @return list<array<string, mixed>>
     */
    public static function normalizeKeyedNodes(array $nodes, string $fallbackKey = ''): array
    {
        return array_values(array_map(
            static fn (array|KeyedSchemaNodeContract $node): array => self::normalizeKeyedNode($node, $fallbackKey),
            $nodes,
        ));
    }

    /**
     * @param list<array<string, mixed>|KeyedSchemaNodeContract> $groups
     *
     * @return list<array<string, mixed>>
     */
    public static function normalizeSchemaGroups(array $groups): array
    {
        return array_values(array_map(
            static function (array|KeyedSchemaNodeContract $group): array {
                $normalized = self::normalizeKeyedNode($group);
                $schema = $normalized['schema'] ?? [];

                if (is_array($schema)) {
                    $normalized['schema'] = self::normalizeKeyedNodes($schema);
                }

                return $normalized;
            },
            $groups,
        ));
    }

    private static function keyFromLabel(string $label): string
    {
        $key = preg_replace('/[^a-z0-9]+/i', '_', strtolower(trim($label)));

        return trim((string) $key, '_');
    }
}
