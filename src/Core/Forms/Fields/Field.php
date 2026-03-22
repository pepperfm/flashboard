<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Core\Forms\Fields;

use Pepperfm\Flashboard\Contracts\Forms\FieldRenderer;
use Pepperfm\Flashboard\Contracts\Forms\FormLayoutAttribute;
use Pepperfm\Flashboard\Support\Schema\SchemaNode;

class Field extends SchemaNode
{
    public const string ATTRIBUTE_HINT = 'hint';
    public const string ATTRIBUTE_HELP = 'help';
    public const string ATTRIBUTE_INPUT_TYPE = 'input_type';
    public const string ATTRIBUTE_PLACEHOLDER = 'placeholder';
    public const string ATTRIBUTE_RENDERER = 'renderer';
    public const string ATTRIBUTE_REQUIRED = 'required';
    public const string ATTRIBUTE_TYPE = 'type';

    public const string TYPE_SELECT = 'select';
    public const string TYPE_TEXT = 'text';
    public const string TYPE_TEXTAREA = 'textarea';
    public const string TYPE_TOGGLE = 'toggle';

    public function type(?string $type): static
    {
        return $this->attribute(self::ATTRIBUTE_TYPE, $type);
    }

    public function required(bool $condition = true): static
    {
        return $this->attribute(self::ATTRIBUTE_REQUIRED, $condition);
    }

    public function hint(?string $hint): static
    {
        return $this->attribute(self::ATTRIBUTE_HINT, $hint);
    }

    public function help(?string $help): static
    {
        return $this->attribute(self::ATTRIBUTE_HELP, $help);
    }

    public function placeholder(?string $placeholder): static
    {
        return $this->attribute(self::ATTRIBUTE_PLACEHOLDER, $placeholder);
    }

    public function renderer(FieldRenderer|string $renderer): static
    {
        if (is_string($renderer)) {
            $renderer = $this->normalizeRenderer($renderer);
        }

        return $this->attribute(
            self::ATTRIBUTE_RENDERER,
            $renderer->value,
        );
    }

    public function toArray(): array
    {
        $payload = parent::toArray();

        if (! array_key_exists(self::ATTRIBUTE_RENDERER, $payload)) {
            $renderer = $this->defaultRenderer();

            if ($renderer !== null) {
                $payload[self::ATTRIBUTE_RENDERER] = $renderer->value;
            }
        }

        return $payload;
    }

    /**
     * @param array<string, int|string>|int $span
     */
    public function columnSpan(array|int $span): static
    {
        return $this->attribute(FormLayoutAttribute::KEY_COLUMN_SPAN, $span);
    }

    public function fullWidth(): static
    {
        return $this->attribute(FormLayoutAttribute::KEY_COLUMN_SPAN, FormLayoutAttribute::VALUE_FULL);
    }

    protected function defaultRenderer(): ?FieldRenderer
    {
        return null;
    }

    private function normalizeRenderer(string $renderer): FieldRenderer
    {
        $normalizedRenderer = FieldRenderer::tryFrom($renderer);

        if ($normalizedRenderer !== null) {
            return $normalizedRenderer;
        }

        throw new \InvalidArgumentException(sprintf(
            'Unknown form field renderer [%s] for field [%s].',
            $renderer,
            $this->key(),
        ));
    }
}
