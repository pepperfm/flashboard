<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Core\Tables\Columns;

class DateColumn extends Column
{
    public const string TYPE = 'date';

    public static function make(string $key, ?string $label = null): static
    {
        return parent::make($key, $label)->type(self::TYPE);
    }

    public function format(string $format): static
    {
        return $this->attribute('format', $format);
    }
}
