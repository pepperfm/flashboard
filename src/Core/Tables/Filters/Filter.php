<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Core\Tables\Filters;

use Pepperfm\Flashboard\Support\Schema\SchemaNode;

class Filter extends SchemaNode
{
    /**
     * @param array<string, mixed> $options
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
