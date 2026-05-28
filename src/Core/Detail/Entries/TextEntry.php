<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Core\Detail\Entries;

class TextEntry extends Entry
{
    public static function make(string $key, ?string $label = null): static
    {
        return parent::make($key, $label)->type('text');
    }
}
