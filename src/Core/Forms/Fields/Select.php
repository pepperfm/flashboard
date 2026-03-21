<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Core\Forms\Fields;

class Select extends Field
{
    public static function make(string $key): static
    {
        return parent::make($key)->type('select');
    }

    /**
     * @param array<string, mixed> $options
     */
    public function options(array $options): static
    {
        return $this->attribute('options', $options);
    }
}
