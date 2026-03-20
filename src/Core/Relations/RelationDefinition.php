<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Core\Relations;

use Pepperfm\Flashboard\Contracts\Resources\Relations\RelationDefinitionContract;

final class RelationDefinition implements RelationDefinitionContract
{
    private ?string $model = null;

    private ?string $label = null;

    private string $titleAttribute = 'name';

    private string $recordKeyName = 'id';

    private bool $visible = true;

    private function __construct(
        private readonly string $key,
    ) {}

    public static function make(string $key): static
    {
        return new static($key);
    }

    public function model(string $model): static
    {
        $this->model = $model;

        return $this;
    }

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function titleAttribute(string $attribute): static
    {
        $this->titleAttribute = $attribute;

        return $this;
    }

    public function recordKeyName(string $key): static
    {
        $this->recordKeyName = $key;

        return $this;
    }

    public function visible(bool $condition): static
    {
        $this->visible = $condition;

        return $this;
    }

    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'model' => $this->model,
            'label' => $this->label ?? str($this->key)->headline()->toString(),
            'title_attribute' => $this->titleAttribute,
            'record_key_name' => $this->recordKeyName,
            'visible' => $this->visible,
        ];
    }
}
