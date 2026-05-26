<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Core\Forms\Fields;

use Pepperfm\Flashboard\Contracts\Forms\FieldRenderer;

class Checkbox extends Field
{
    public static function make(string $key): static
    {
        return parent::make($key)->type(self::TYPE_CHECKBOX);
    }

    protected function defaultRenderer(): ?FieldRenderer
    {
        return FieldRenderer::Checkbox;
    }
}
