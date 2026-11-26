<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Core\Metrics;

final class NumericMetricAggregate
{
    /**
     * @return array{value: string|null}
     */
    public function resolve(\Illuminate\Database\Eloquent\Builder $query, string $kind, string $column): array
    {
        $function = match ($kind) {
            'sum' => 'SUM',
            'avg' => 'AVG',
            default => throw new \InvalidArgumentException('Unsupported numeric metric kind.'),
        };

        $model = $query->getModel();
        $type = strtolower($model->getConnection()->getSchemaBuilder()->getColumnType($model->getTable(), $column));

        if (!self::supportsColumnType($type)) {
            throw new \InvalidArgumentException('Numeric metrics require a numeric database column.');
        }

        $base = clone $query->toBase();
        $this->assertSupportedQuery($base, $model->getTable());

        // Membership is scoped by the original query, not by the model's unscoped table.
        // Deduplicate by primary key, not by amount: equal amounts on different rows count twice.
        $base->reorder()->select([
            $model->getQualifiedKeyName().' as metric_record_key',
            $model->qualifyColumn($column).' as metric_value',
        ])->distinct();

        $cast = match ($model->getConnection()->getDriverName()) {
            'mysql', 'mariadb' => 'CHAR',
            'sqlsrv' => 'VARCHAR(100)',
            default => 'TEXT',
        };

        $aggregate = $base->newQuery()->fromSub($base, 'metric_records')
            ->whereNotNull('metric_record_key')
            ->selectRaw("CAST($function(metric_value) AS $cast) AS aggregate")
            ->value('aggregate');

        return ['value' => $aggregate === null ? ($kind === 'sum' ? '0' : null) : (string) $aggregate];
    }

    public static function supportsColumnType(string $type): bool
    {
        return in_array($type, [
            'smallint', 'integer', 'bigint', 'tinyint', 'mediumint', 'int', 'int2', 'int4', 'int8',
            'decimal', 'numeric', 'real', 'float', 'double', 'double precision', 'float4', 'float8',
        ], true);
    }

    private function assertSupportedQuery(\Illuminate\Database\Query\Builder $query, string $table): void
    {
        if (
            $query->from !== $table
            || $query->aggregate !== null
            || $query->groups !== null
            || $query->havings !== null
            || $query->unions !== null
            || $query->limit !== null
            || $query->offset !== null
            || $query->groupLimit !== null
            || $query->lock !== null
            || is_array($query->distinct)
        ) {
            throw new \InvalidArgumentException('Numeric metrics require an ungrouped, unlimited resource membership query.');
        }
    }
}
