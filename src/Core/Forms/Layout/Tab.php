<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Core\Forms\Layout;

use Pepperfm\Flashboard\Support\Schema\SchemaNode;

class Tab extends SchemaNode
{
    /**
     * @param list<array<string, mixed>|\Pepperfm\Flashboard\Contracts\Schema\KeyedSchemaNodeContract> $schema
     */
    public function schema(array $schema): static
    {
        return $this->attribute('schema', $schema);
    }

    public function icon(?string $icon): static
    {
        return $this->attribute('icon', $icon);
    }
}
