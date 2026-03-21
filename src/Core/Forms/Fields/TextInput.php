<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Core\Forms\Fields;

class TextInput extends Field
{
    public static function make(string $key): static
    {
        return parent::make($key)->type('text');
    }

    public function email(bool $condition = true): static
    {
        if ($condition) {
            $this->attribute('input_type', 'email');
        }

        return $this;
    }
}
