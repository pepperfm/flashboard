<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Core\Forms\Fields;

class Toggle extends Field
{
    public static function make(string $key): static
    {
        return parent::make($key)->type('toggle');
    }
}
