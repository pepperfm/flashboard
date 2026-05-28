<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Core\Forms\Fields;

use Pepperfm\Flashboard\Contracts\Forms\FieldRenderer;

class Select extends Field
{
    public static function make(string $key, ?string $label = null): static
    {
        return parent::make($key, $label)->type(self::TYPE_SELECT);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function options(array $options): static
    {
        return $this->attribute('options', $options);
    }

    protected function defaultRenderer(): ?FieldRenderer
    {
        return FieldRenderer::Select;
    }
}
