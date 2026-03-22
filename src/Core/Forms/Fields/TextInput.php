<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Core\Forms\Fields;

use Pepperfm\Flashboard\Contracts\Forms\FieldRenderer;

class TextInput extends Field
{
    public static function make(string $key): static
    {
        return parent::make($key)->type(self::TYPE_TEXT);
    }

    public function email(bool $condition = true): static
    {
        if ($condition) {
            $this->attribute(self::ATTRIBUTE_INPUT_TYPE, 'email');
        }

        return $this;
    }

    protected function defaultRenderer(): ?FieldRenderer
    {
        return FieldRenderer::Input;
    }
}
