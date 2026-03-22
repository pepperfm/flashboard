<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Core\Forms\Layout;

use Pepperfm\Flashboard\Contracts\Forms\FormLayoutAlign;
use Pepperfm\Flashboard\Contracts\Forms\FormLayoutAttribute;
use Pepperfm\Flashboard\Contracts\Forms\FormLayoutDirection;
use Pepperfm\Flashboard\Contracts\Forms\FormLayoutJustify;
use Pepperfm\Flashboard\Contracts\Forms\FormLayoutMode;
use Pepperfm\Flashboard\Contracts\Forms\FormSchemaNodeKind;
use Pepperfm\Flashboard\Support\Schema\SchemaNode;

class Section extends SchemaNode
{
    /**
     * @param list<array<string, mixed>|\Pepperfm\Flashboard\Contracts\Schema\KeyedSchemaNodeContract> $schema
     */
    public function schema(array $schema): static
    {
        return $this->attribute('schema', $schema);
    }

    public function description(?string $description): static
    {
        return $this->attribute('description', $description);
    }

    public function layout(FormLayoutMode|string $mode): static
    {
        return $this->attribute(
            FormLayoutAttribute::KEY_LAYOUT,
            $mode instanceof FormLayoutMode ? $mode->value : $mode,
        );
    }

    /**
     * @param array<string, int>|int $columns
     */
    public function columns(array|int $columns = 2): static
    {
        return $this->attribute(FormLayoutAttribute::KEY_COLUMNS, $columns);
    }

    /**
     * @param array<string, int>|int $gap
     */
    public function gap(array|int $gap): static
    {
        return $this->attribute(FormLayoutAttribute::KEY_GAP, $gap);
    }

    public function direction(FormLayoutDirection|string $direction): static
    {
        return $this->attribute(
            FormLayoutAttribute::KEY_DIRECTION,
            $direction instanceof FormLayoutDirection ? $direction->value : $direction,
        );
    }

    public function justify(FormLayoutJustify|string $justify): static
    {
        return $this->attribute(
            FormLayoutAttribute::KEY_JUSTIFY,
            $justify instanceof FormLayoutJustify ? $justify->value : $justify,
        );
    }

    public function align(FormLayoutAlign|string $align): static
    {
        return $this->attribute(
            FormLayoutAttribute::KEY_ALIGN,
            $align instanceof FormLayoutAlign ? $align->value : $align,
        );
    }

    public function wrap(bool $condition = true): static
    {
        return $this->attribute(FormLayoutAttribute::KEY_WRAP, $condition);
    }

    public function toArray(): array
    {
        $payload = parent::toArray();
        $payload['kind'] = $payload['kind'] ?? FormSchemaNodeKind::Section->value;

        return $payload;
    }
}
