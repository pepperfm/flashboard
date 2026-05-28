<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Core\Forms\Fields;

use Pepperfm\Flashboard\Contracts\Forms\FieldRenderer;

class TextInput extends Field
{
    public static function make(string $key, ?string $label = null): static
    {
        return parent::make($key, $label)->type(self::TYPE_TEXT);
    }

    public function email(bool $condition = true): static
    {
        if ($condition) {
            $this->inputType('email');
        }

        return $this;
    }

    public function password(bool $condition = true): static
    {
        if ($condition) {
            $this->inputType('password');
        }

        return $this;
    }

    protected function defaultRenderer(): ?FieldRenderer
    {
        return FieldRenderer::Input;
    }
}
