<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Core\Tables\Columns;

class TextColumn extends Column
{
    public static function make(string $key): static
    {
        return parent::make($key)->type('text');
    }
}
