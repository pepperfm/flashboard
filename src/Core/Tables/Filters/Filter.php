<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Core\Tables\Filters;

use Pepperfm\Flashboard\Support\Schema\SchemaNode;

class Filter extends SchemaNode
{
    public function searchable(bool $condition = true): static
    {
        return $this->attribute('searchable', $condition);
    }

    public function queryColumn(string $column): static
    {
        return $this->attribute('query_column', $column);
    }

    /**
     * @param array<array-key, mixed> $options
     */
    public function options(array $options): static
    {
        return $this->attribute('options', $options);
    }

    public function type(?string $type): static
    {
        return $this->attribute('type', $type);
    }
}
