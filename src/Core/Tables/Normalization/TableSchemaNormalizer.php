<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Core\Tables\Normalization;

use Illuminate\Support\Arr;
use Pepperfm\Flashboard\Support\Schema\SchemaNodeNormalizer;

final class TableSchemaNormalizer
{
    /**
     * @param array<string, mixed> $definition
     *
     * @return array<string, mixed>
     */
    public function normalize(array $definition): array
    {
        return [
            'columns' => SchemaNodeNormalizer::normalizeKeyedNodes(
                (array) Arr::get($definition, 'columns', []),
                'value',
            ),
            'filters' => SchemaNodeNormalizer::normalizeKeyedNodes(
                (array) Arr::get($definition, 'filters', []),
            ),
            'scopes' => SchemaNodeNormalizer::normalizeKeyedNodes(
                (array) Arr::get($definition, 'scopes', []),
            ),
            'bulk_actions' => array_values((array) Arr::get($definition, 'bulk_actions', [])),
            'pagination' => max(1, (int) Arr::get($definition, 'pagination', 15)),
        ];
    }
}
