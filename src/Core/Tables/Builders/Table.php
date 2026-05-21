<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Core\Tables\Builders;

use Pepperfm\Flashboard\Contracts\Actions\ActionContract;
use Pepperfm\Flashboard\Contracts\Schema\KeyedSchemaNodeContract;
use Pepperfm\Flashboard\Contracts\Tables\TableContract;
use Pepperfm\Flashboard\Core\Tables\Normalization\TableSchemaNormalizer;

final class Table implements TableContract
{
    /**
     * @var list<array<string, mixed>|KeyedSchemaNodeContract>
     */
    private array $columns = [];

    /**
     * @var list<array<string, mixed>|KeyedSchemaNodeContract>
     */
    private array $filters = [];

    /**
     * @var list<array<string, mixed>|KeyedSchemaNodeContract>
     */
    private array $scopes = [];

    /**
     * @var list<ActionContract|array<string, mixed>>
     */
    private array $actions = [];

    /**
     * @var list<ActionContract|array<string, mixed>>
     */
    private array $bulkActions = [];

    private int $pagination = 10;

    public static function make(): static
    {
        return new static();
    }

    public function columns(array $columns): static
    {
        $this->columns = $columns;

        return $this;
    }

    public function filters(array $filters): static
    {
        $this->filters = $filters;

        return $this;
    }

    /**
     * @return list<array<string, mixed>|KeyedSchemaNodeContract>
     */
    public function rawFilters(): array
    {
        return $this->filters;
    }

    public function scopes(array $scopes): static
    {
        $this->scopes = $scopes;

        return $this;
    }

    public function actions(array $actions): static
    {
        $this->actions = $actions;

        return $this;
    }

    public function bulkActions(array $bulkActions): static
    {
        $this->bulkActions = $bulkActions;

        return $this;
    }

    public function pagination(int $perPage): static
    {
        $this->pagination = $perPage;

        return $this;
    }

    public function toArray(): array
    {
        return new TableSchemaNormalizer()->normalize([
            'columns' => $this->columns,
            'filters' => $this->filters,
            'scopes' => $this->scopes,
            'actions' => $this->normalizeActions($this->actions),
            'bulk_actions' => $this->normalizeActions($this->bulkActions),
            'pagination' => $this->pagination,
        ]);
    }

    /**
     * @param list<ActionContract|array<string, mixed>> $actions
     *
     * @return list<array<string, mixed>>
     */
    private function normalizeActions(array $actions): array
    {
        return array_values(array_map(
            static fn(ActionContract|array $action): array => $action instanceof ActionContract ? $action->toArray() : $action,
            $actions,
        ));
    }
}
