<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Contracts\Forms;

use Pepperfm\Flashboard\Contracts\Schema\KeyedSchemaNodeContract;

interface FormContract
{
    /**
     * @param list<array<string, mixed>|KeyedSchemaNodeContract> $sections
     */
    public function sections(array $sections): static;

    /**
     * @param list<array<string, mixed>|KeyedSchemaNodeContract> $tabs
     */
    public function tabs(array $tabs): static;

    /**
     * @param list<array<string, mixed>|KeyedSchemaNodeContract> $fields
     */
    public function fields(array $fields): static;

    /**
     * @param list<array<string, mixed>|KeyedSchemaNodeContract> $schema
     */
    public function schema(array $schema): static;

    /**
     * @param array<string, mixed> $rules
     */
    public function rules(array $rules): static;

    /**
     * @param array<string, mixed> $defaults
     */
    public function defaults(array $defaults): static;

    public function mutateDataUsing(?callable $callback): static;

    public function afterSave(?callable $callback): static;

    /**
     * @return list<array<string, mixed>>
     */
    public function fieldSchema(): array;

    /**
     * @return array<string, mixed>
     */
    public function defaultState(): array;

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function mutateData(array $data, ?\Illuminate\Database\Eloquent\Model $record = null): array;

    /**
     * @param array<string, mixed> $data
     */
    public function runAfterSave(\Illuminate\Database\Eloquent\Model $record, array $data): void;

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
