<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Core\Detail\Entries;

use Pepperfm\Flashboard\Support\Schema\SchemaNode;

class Entry extends SchemaNode
{
    public function type(?string $type): static
    {
        return $this->attribute('type', $type);
    }

    public function help(?string $help): static
    {
        return $this->attribute('help', $help);
    }
}
