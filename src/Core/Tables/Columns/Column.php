<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Core\Tables\Columns;

use Pepperfm\Flashboard\Support\Schema\SchemaNode;

class Column extends SchemaNode
{
    public function searchable(bool $condition = true): static
    {
        return $this->attribute('searchable', $condition);
    }

    public function sortable(bool $condition = true): static
    {
        return $this->attribute('sortable', $condition);
    }

    public function type(?string $type): static
    {
        return $this->attribute('type', $type);
    }
}
