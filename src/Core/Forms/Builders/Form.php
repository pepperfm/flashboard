<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Core\Forms\Builders;

use Illuminate\Database\Eloquent\Model;
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
        return new FormSchemaNormalizer()->normalize([
            'sections' => $this->sections,
            'tabs' => $this->tabs,
            'fields' => $this->fields,
            'rules' => $this->rules,
            'defaults' => $this->defaults,
            'has_mutate_data_using' => $this->mutateDataUsing !== null,
            'has_after_save' => $this->afterSave !== null,
        ]);
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
        if (!is_callable($this->mutateDataUsing)) {
            return $data;
        }

        return (array) ($this->mutateDataUsing)($data, $record);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function runAfterSave(Model $record, array $data): void
    {
        if (!is_callable($this->afterSave)) {
            return;
        }

        ($this->afterSave)($record, $data);
    }
}
