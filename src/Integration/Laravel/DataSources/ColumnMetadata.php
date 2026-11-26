<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Integration\Laravel\DataSources;

use Illuminate\Container\Attributes\Scoped;

#[Scoped]
final class ColumnMetadata
{
    /**
     * @var array<string, array<string, string>>
     */
    private array $tables = [];

    /**
     * @return array<string, string>
     */
    public function types(\Illuminate\Database\Connection $connection, string $table): array
    {
        $key = spl_object_id($connection) . ':' . $connection->getDatabaseName() . ':' . $table;
        if (isset($this->tables[$key])) {
            return $this->tables[$key];
        }

        $types = [];
        foreach ($connection->getSchemaBuilder()->getColumns($table) as $column) {
            $types[$column['name']] = $column['type_name'];
        }

        if ($types !== []) {
            $this->tables[$key] = $types;
        }

        return $types;
    }

    /**
     * @return array<string, string>
     */
    public function queryTables(\Illuminate\Database\Eloquent\Builder $query): array
    {
        $base = $query->getQuery();
        $tables = [];
        foreach ([$base->from, ...array_map(static fn ($join) => $join->table, $base->joins ?? [])] as $source) {
            if (!is_string($source) || preg_match('/^([a-zA-Z_][\w.]*)\s*(?:(?:as\s+)?([a-zA-Z_]\w*))?$/iD', $source, $matches) !== 1) {
                continue;
            }

            $table = $matches[1];
            $alias = $matches[2] ?? null;
            if ($alias !== null) {
                $tables[$alias] = $table;
            } else {
                $tables[$table] = $table;
            }
        }

        return $tables;
    }

    public function typeFor(\Illuminate\Database\Eloquent\Builder $query, string $column): ?string
    {
        $connection = $query->getModel()->getConnection();
        $tables = $this->queryTables($query);
        $name = str($column)->afterLast('.')->value();
        if (str_contains($column, '.')) {
            $qualifier = str($column)->beforeLast('.')->value();
            $table = $tables[$qualifier] ?? null;
            if ($table === null) {
                $matches = array_filter($tables, static fn (string $name, string $key): bool =>
                    $name === $key && str($key)->afterLast('.')->value() === $qualifier, ARRAY_FILTER_USE_BOTH);
                $table = count($matches) === 1 ? reset($matches) : null;
            }
        } else {
            // Unknown derived-table columns cannot be resolved safely by name.
            if (count($tables) !== 1 + count($query->getQuery()->joins ?? [])) {
                return null;
            }
            // Unqualified names must resolve unambiguously across the joined tables.
            $matches = array_values(array_filter(
                $tables,
                fn (string $table): bool => array_key_exists($name, $this->types($connection, $table)),
            ));
            $table = count($matches) === 1 ? $matches[0] : null;
        }

        return $table !== null ? ($this->types($connection, $table)[$name] ?? null) : null;
    }
}
