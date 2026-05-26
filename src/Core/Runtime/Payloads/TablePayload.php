<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Core\Runtime\Payloads;

final readonly class TablePayload
{
    private const string KEY_COLUMNS = 'columns';
    private const string KEY_FILTERS = 'filters';
    private const string KEY_SCOPES = 'scopes';
    private const string KEY_BULK_ACTIONS = 'bulk_actions';
    private const string KEY_PAGINATION = 'pagination';

    /**
     * @param array<string, mixed> $schema
     */
    public function __construct(
        private array $schema,
    ) {
    }

    public function toArray(): array
    {
        return $this->schema;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function columns(): array
    {
        return (array) $this->schema[self::KEY_COLUMNS];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function filters(): array
    {
        return (array) $this->schema[self::KEY_FILTERS];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function scopes(): array
    {
        return (array) $this->schema[self::KEY_SCOPES];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function bulkActions(): array
    {
        return (array) $this->schema[self::KEY_BULK_ACTIONS];
    }

    public function pagination(): int
    {
        return (int) $this->schema[self::KEY_PAGINATION];
    }

    /**
     * @return list<string>
     */
    public function searchableColumns(): array
    {
        return array_values(array_map(
            static fn (array $column): string => (string) $column['key'],
            array_values(array_filter(
                $this->columns(),
                static fn (array $column): bool => ($column['searchable'] ?? false) === true,
            )),
        ));
    }

    /**
     * @return list<string>
     */
    public function sortableColumns(): array
    {
        return array_values(array_map(
            static fn (array $column): string => (string) $column['key'],
            array_values(array_filter(
                $this->columns(),
                static fn (array $column): bool => ($column['sortable'] ?? false) === true,
            )),
        ));
    }
}
