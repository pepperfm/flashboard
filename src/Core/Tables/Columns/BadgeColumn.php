<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Core\Tables\Columns;

class BadgeColumn extends Column
{
    public static function make(string $key, ?string $label = null): static
    {
        return parent::make($key, $label)->type('badge');
    }
}
