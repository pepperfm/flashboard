<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Core\Forms\Fields;

use Pepperfm\Flashboard\Contracts\Forms\FieldRenderer;

class Textarea extends Field
{
    public static function make(string $key): static
    {
        return parent::make($key)->type(self::TYPE_TEXTAREA);
    }

    protected function defaultRenderer(): ?FieldRenderer
    {
        return FieldRenderer::Textarea;
    }
}
