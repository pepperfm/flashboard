<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Core\Forms\Fields;

class PasswordInput extends TextInput
{
    public const string ATTRIBUTE_CONFIRMED = 'confirmed';
    public const string ATTRIBUTE_MAX_LENGTH = 'max_length';
    public const string ATTRIBUTE_MIN_LENGTH = 'min_length';

    public static function make(string $key): static
    {
        return parent::make($key)
            ->type(self::TYPE_PASSWORD)
            ->inputType('password');
    }

    public function minLength(int $length): static
    {
        return $this->attribute(self::ATTRIBUTE_MIN_LENGTH, $length);
    }

    public function maxLength(int $length): static
    {
        return $this->attribute(self::ATTRIBUTE_MAX_LENGTH, $length);
    }

    public function confirmed(bool $condition = true): static
    {
        return $this->attribute(self::ATTRIBUTE_CONFIRMED, $condition);
    }
}
