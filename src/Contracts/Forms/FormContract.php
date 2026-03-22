<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Contracts\Forms;

use Pepperfm\Flashboard\Contracts\Schema\KeyedSchemaNodeContract;

interface FormContract
{
    /**
     * Compatibility helper: section nodes are normalized into the canonical schema tree.
     *
     * @param list<array<string, mixed>|KeyedSchemaNodeContract> $sections
     */
    public function sections(array $sections): static;

    /**
     * Compatibility helper: top-level tabs are normalized into the canonical schema tree.
     *
     * @param list<array<string, mixed>|KeyedSchemaNodeContract> $tabs
     */
    public function tabs(array $tabs): static;

    /**
     * Compatibility helper: plain field lists remain supported as the simplest schema root.
     *
     * @param list<array<string, mixed>|KeyedSchemaNodeContract> $fields
     */
    public function fields(array $fields): static;

    /**
     * Canonical form composition entrypoint.
     *
     * @param list<array<string, mixed>|KeyedSchemaNodeContract> $schema
     */
    public function schema(array $schema): static;

    public function layout(FormLayoutMode|string $mode): static;

    /**
     * @param array<string, int>|int $columns
     */
    public function columns(array|int $columns): static;

    /**
     * @param array<string, int>|int $gap
     */
    public function gap(array|int $gap): static;

    public function direction(FormLayoutDirection|string $direction): static;

    public function justify(FormLayoutJustify|string $justify): static;

    public function align(FormLayoutAlign|string $align): static;

    public function wrap(bool $condition = true): static;

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
