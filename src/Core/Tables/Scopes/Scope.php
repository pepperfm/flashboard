<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Core\Tables\Scopes;

use Pepperfm\Flashboard\Support\Schema\SchemaNode;

class Scope extends SchemaNode
{
    public function active(bool $condition = true): static
    {
        return $this->attribute('active', $condition);
    }
}
