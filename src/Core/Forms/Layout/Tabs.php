<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Core\Forms\Layout;

use Pepperfm\Flashboard\Contracts\Forms\FormSchemaNodeKind;
use Pepperfm\Flashboard\Support\Schema\SchemaNode;

class Tabs extends SchemaNode
{
    /**
     * @param list<array<string, mixed>|\Pepperfm\Flashboard\Contracts\Schema\KeyedSchemaNodeContract> $tabs
     */
    public function tabs(array $tabs): static
    {
        return $this->attribute('tabs', $tabs);
    }

    public function toArray(): array
    {
        $payload = parent::toArray();
        $payload['kind'] = $payload['kind'] ?? FormSchemaNodeKind::Tabs->value;

        return $payload;
    }
}
