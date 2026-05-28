<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Core\Tables\Columns;

class TextColumn extends Column
{
    public static function make(string $key, ?string $label = null): static
    {
        return parent::make($key, $label)->type('text');
    }
}
