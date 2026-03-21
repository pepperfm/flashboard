<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Core\Forms\Fields;

use Pepperfm\Flashboard\Support\Schema\SchemaNode;

class Field extends SchemaNode
{
    public function type(?string $type): static
    {
        return $this->attribute('type', $type);
    }

    public function required(bool $condition = true): static
    {
        return $this->attribute('required', $condition);
    }

    public function hint(?string $hint): static
    {
        return $this->attribute('hint', $hint);
    }

    public function help(?string $help): static
    {
        return $this->attribute('help', $help);
    }

    public function placeholder(?string $placeholder): static
    {
        return $this->attribute('placeholder', $placeholder);
    }
}
