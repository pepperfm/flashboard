<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Ai\Generation;

final class ColumnType
{
    public static function isDate(string $type): bool
    {
        return in_array($type, [
            'date', 'datetime', 'timestamp', 'timestamptz',
            'timestamp without time zone', 'timestamp with time zone',
        ], true);
    }
}
