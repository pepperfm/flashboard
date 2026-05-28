<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Core\Tables\Filters;

class InputFilter extends Filter
{
    public const string MATCH_CONTAINS = 'contains';

    public const string MATCH_EXACT = 'exact';

    public const string TYPE = 'input';

    public static function make(string $key, ?string $label = null): static
    {
        return parent::make($key, $label)->type(self::TYPE);
    }

    public function contains(bool $condition = true): static
    {
        return $this->attribute('match', $condition ? self::MATCH_CONTAINS : self::MATCH_EXACT);
    }

    public function exact(): static
    {
        return $this->attribute('match', self::MATCH_EXACT);
    }
}
