<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Core\Tables\Filters;

class DateFilter extends Filter
{
    public const string TYPE = 'date';

    public static function make(string $key, ?string $label = null): static
    {
        return parent::make($key, $label)->type(self::TYPE);
    }
}
