<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Contracts\Resources\Relations;

interface RelationDefinitionContract
{
    public static function make(string $key): static;

    public function model(string $model): static;

    public function label(string $label): static;

    public function titleAttribute(string $attribute): static;

    public function recordKeyName(string $key): static;

    public function visible(bool $condition): static;

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
