<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Core\Tables\Filters;

class DateFilter extends Filter
{
    public const string TYPE = 'date';

    public static function make(string $key): static
    {
        return parent::make($key)->type(self::TYPE);
    }
}
