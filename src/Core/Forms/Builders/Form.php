<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Core\Forms\Builders;

use Illuminate\Database\Eloquent\Model;
use Pepperfm\Flashboard\Contracts\Forms\FormLayoutAlign;
use Pepperfm\Flashboard\Contracts\Forms\FormLayoutAttribute;
use Pepperfm\Flashboard\Contracts\Forms\FormLayoutDirection;
use Pepperfm\Flashboard\Contracts\Forms\FormLayoutJustify;
use Pepperfm\Flashboard\Contracts\Forms\FormLayoutMode;
use Pepperfm\Flashboard\Contracts\Schema\KeyedSchemaNodeContract;
use Pepperfm\Flashboard\Contracts\Forms\FormContract;
use Pepperfm\Flashboard\Core\Forms\Normalization\FormSchemaNormalizer;

final class Form implements FormContract
{
    /**
     * @var list<array<string, mixed>|KeyedSchemaNodeContract>
     */
    private array $sections = [];

    /**
     * @var list<array<string, mixed>|KeyedSchemaNodeContract>
     */
    private array $tabs = [];

    /**
     * @var list<array<string, mixed>|KeyedSchemaNodeContract>
     */
    private array $fields = [];

    /**
     * @var array<string, mixed>
     */
    private array $rules = [];

    /**
     * @var array<string, mixed>
     */
    private array $defaults = [];

    /**
     * @var array<string, mixed>
     */
    private array $layout = [];

    private ?\Closure $mutateDataUsing = null;

    private ?\Closure $afterSave = null;

    public static function make(): self
    {
        return new self();
    }

    public function sections(array $sections): static
    {
        $this->sections = $sections;

        return $this;
    }

    public function tabs(array $tabs): static
    {
        $this->tabs = $tabs;

        return $this;
    }

    public function fields(array $fields): static
    {
        $this->fields = $fields;

        return $this;
    }

    public function schema(array $schema): static
    {
        return $this->fields($schema);
    }

    public function layout(FormLayoutMode|string $mode): static
    {
        return $this->setLayoutAttribute(
            FormLayoutAttribute::KEY_LAYOUT,
            $mode instanceof FormLayoutMode ? $mode->value : $mode,
        );
    }

    public function columns(array|int $columns): static
    {
        return $this->setLayoutAttribute(FormLayoutAttribute::KEY_COLUMNS, $columns);
    }

    public function gap(array|int $gap): static
    {
        return $this->setLayoutAttribute(FormLayoutAttribute::KEY_GAP, $gap);
    }

    public function direction(FormLayoutDirection|string $direction): static
    {
        return $this->setLayoutAttribute(
            FormLayoutAttribute::KEY_DIRECTION,
            $direction instanceof FormLayoutDirection ? $direction->value : $direction,
        );
    }

    public function justify(FormLayoutJustify|string $justify): static
    {
        return $this->setLayoutAttribute(
            FormLayoutAttribute::KEY_JUSTIFY,
            $justify instanceof FormLayoutJustify ? $justify->value : $justify,
        );
    }

    public function align(FormLayoutAlign|string $align): static
    {
        return $this->setLayoutAttribute(
            FormLayoutAttribute::KEY_ALIGN,
            $align instanceof FormLayoutAlign ? $align->value : $align,
        );
    }

    public function wrap(bool $condition = true): static
    {
        return $this->setLayoutAttribute(FormLayoutAttribute::KEY_WRAP, $condition);
    }

    public function rules(array $rules): static
    {
        $this->rules = $rules;

        return $this;
    }

    public function defaults(array $defaults): static
    {
        $this->defaults = $defaults;

        return $this;
    }

    public function mutateDataUsing(?callable $callback): static
    {
        $this->mutateDataUsing = $callback === null ? null : $callback(...);

        return $this;
    }

    public function afterSave(?callable $callback): static
    {
        $this->afterSave = $callback === null ? null : $callback(...);

        return $this;
    }

    public function toArray(): array
    {
        return new FormSchemaNormalizer()->normalize(array_merge([
            'sections' => $this->sections,
            'tabs' => $this->tabs,
            'fields' => $this->fields,
            'rules' => $this->rules,
            'defaults' => $this->defaults,
            'has_mutate_data_using' => $this->mutateDataUsing !== null,
            'has_after_save' => $this->afterSave !== null,
        ], $this->layout));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function fieldSchema(): array
    {
        return (array) $this->toArray()['fields'];
    }

    /**
     * @return array<string, mixed>
     */
    public function defaultState(): array
    {
        return $this->defaults;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function mutateData(array $data, ?Model $record = null): array
    {
        if ($this->mutateDataUsing === null) {
            return $data;
        }

        return (array) value($this->mutateDataUsing, $data, $record);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function runAfterSave(Model $record, array $data): void
    {
        if ($this->afterSave === null) {
            return;
        }

        value($this->afterSave, $record, $data);
    }

    private function setLayoutAttribute(string $key, mixed $value): static
    {
        $this->layout[$key] = $value;

        return $this;
    }
}
