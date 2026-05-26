<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Contracts\Tables;

use Pepperfm\Flashboard\Contracts\Actions\ActionContract;
use Pepperfm\Flashboard\Contracts\Schema\KeyedSchemaNodeContract;

interface TableContract
{
    /**
     * @param list<array<string, mixed>|KeyedSchemaNodeContract> $columns
     */
    public function columns(array $columns): static;

    /**
     * @param list<array<string, mixed>|KeyedSchemaNodeContract> $filters
     */
    public function filters(array $filters): static;

    /**
     * @param list<array<string, mixed>|KeyedSchemaNodeContract> $scopes
     */
    public function scopes(array $scopes): static;

    /**
     * @param list<ActionContract|array<string, mixed>> $bulkActions
     */
    public function bulkActions(array $bulkActions): static;

    public function pagination(int $perPage): static;

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
