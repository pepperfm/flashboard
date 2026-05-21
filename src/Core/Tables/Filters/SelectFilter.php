<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Core\Tables\Filters;

class SelectFilter extends Filter
{
    public static function make(string $key): static
    {
        return parent::make($key)->type('select');
    }

    /**
     * @param array<array-key, mixed> $options
     */
    public function options(array $options): static
    {
        return parent::options($options);
    }
}
