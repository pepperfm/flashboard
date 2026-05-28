<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Core\Forms\Fields;

use Pepperfm\Flashboard\Contracts\Forms\FieldRenderer;

class RichText extends Field
{
    public const string ATTRIBUTE_CONTENT_FORMAT = 'content_format';
    public const string ATTRIBUTE_MAX_LENGTH = 'max_length';
    public const string ATTRIBUTE_MIN_LENGTH = 'min_length';

    public const string FORMAT_HTML = 'html';
    public const string FORMAT_JSON = 'json';
    public const string FORMAT_MARKDOWN = 'markdown';

    public static function make(string $key, ?string $label = null): static
    {
        return parent::make($key, $label)
            ->type(self::TYPE_RICH_TEXT)
            ->attribute(self::ATTRIBUTE_CONTENT_FORMAT, self::FORMAT_HTML);
    }

    public function html(): static
    {
        return $this->contentFormat(self::FORMAT_HTML);
    }

    public function markdown(): static
    {
        return $this->contentFormat(self::FORMAT_MARKDOWN);
    }

    public function json(): static
    {
        return $this->contentFormat(self::FORMAT_JSON);
    }

    public function contentFormat(string $format): static
    {
        if (!in_array($format, [self::FORMAT_HTML, self::FORMAT_MARKDOWN, self::FORMAT_JSON], true)) {
            throw new \InvalidArgumentException(sprintf('Unknown rich text content format [%s].', $format));
        }

        return $this->attribute(self::ATTRIBUTE_CONTENT_FORMAT, $format);
    }

    public function minLength(int $length): static
    {
        return $this->attribute(self::ATTRIBUTE_MIN_LENGTH, $length);
    }

    public function maxLength(int $length): static
    {
        return $this->attribute(self::ATTRIBUTE_MAX_LENGTH, $length);
    }

    protected function defaultRenderer(): ?FieldRenderer
    {
        return FieldRenderer::RichText;
    }
}
