<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Core\Forms\Fields;

class NumberInput extends TextInput
{
    public static function make(string $key): static
    {
        return parent::make($key)->inputType('number');
    }
}
