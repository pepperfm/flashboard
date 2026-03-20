<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Contracts\Tables;

use Pepperfm\Flashboard\Contracts\Actions\ActionContract;

interface TableContract
{
    /**
     * @param list<array<string, mixed>> $columns
     */
    public function columns(array $columns): static;

    /**
     * @param list<array<string, mixed>> $filters
     */
    public function filters(array $filters): static;

    /**
     * @param list<array<string, mixed>> $scopes
     */
    public function scopes(array $scopes): static;

    /**
     * @param list<ActionContract|array<string, mixed>> $actions
     */
    public function actions(array $actions): static;

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
