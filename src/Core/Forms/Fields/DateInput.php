<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Core\Forms\Fields;

use Pepperfm\Flashboard\Contracts\Forms\FieldRenderer;

class DateInput extends Field
{
    public const string ATTRIBUTE_MAX_DATE = 'max_date';
    public const string ATTRIBUTE_MIN_DATE = 'min_date';
    public const string ATTRIBUTE_NATIVE = 'native';

    public static function make(string $key, ?string $label = null): static
    {
        return parent::make($key, $label)->type(self::TYPE_DATE);
    }

    public function minDate(\DateTimeInterface|string|null $date): static
    {
        return $this->attribute(self::ATTRIBUTE_MIN_DATE, $this->normalizeDate($date));
    }

    public function maxDate(\DateTimeInterface|string|null $date): static
    {
        return $this->attribute(self::ATTRIBUTE_MAX_DATE, $this->normalizeDate($date));
    }

    public function native(bool $condition = true): static
    {
        return $this->attribute(self::ATTRIBUTE_NATIVE, $condition);
    }

    protected function defaultRenderer(): ?FieldRenderer
    {
        return FieldRenderer::Date;
    }

    private function normalizeDate(\DateTimeInterface|string|null $date): ?string
    {
        if ($date instanceof \DateTimeInterface) {
            return $date->format('Y-m-d');
        }

        return $date;
    }
}
