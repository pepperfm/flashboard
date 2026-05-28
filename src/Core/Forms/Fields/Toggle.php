<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Core\Forms\Fields;

use Pepperfm\Flashboard\Contracts\Forms\FieldRenderer;

class Toggle extends Field
{
    public static function make(string $key, ?string $label = null): static
    {
        return parent::make($key, $label)->type(self::TYPE_TOGGLE);
    }

    protected function defaultRenderer(): ?FieldRenderer
    {
        return FieldRenderer::Switch;
    }
}
