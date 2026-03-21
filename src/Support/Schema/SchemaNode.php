<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Support\Schema;

use Pepperfm\Flashboard\Contracts\Schema\KeyedSchemaNodeContract;

abstract class SchemaNode implements KeyedSchemaNodeContract
{
    /**
     * @var array<string, mixed>
     */
    private array $attributes = [];

    /**
     * @var array<string, mixed>
     */
    private array $meta = [];

    private ?string $label = null;

    final protected function __construct(
        private readonly string $key,
    ) {
    }

    public static function make(string $key): static
    {
        return new static($key);
    }

    public function key(): string
    {
        return $this->key;
    }

    public function label(?string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function attribute(string $key, mixed $value): static
    {
        $this->attributes[$key] = $value;

        return $this;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function attributes(array $attributes): static
    {
        $this->attributes = array_merge($this->attributes, $attributes);

        return $this;
    }

    /**
     * @param array<string, mixed> $meta
     */
    public function meta(array $meta): static
    {
        $this->meta = array_merge($this->meta, $meta);

        return $this;
    }

    public function toArray(): array
    {
        $payload = ['key' => $this->key];

        if ($this->label !== null) {
            $payload['label'] = $this->label;
        }

        $payload = array_merge($payload, $this->attributes);

        if ($this->meta !== []) {
            $payload['meta'] = $this->meta;
        }

        return $payload;
    }
}
