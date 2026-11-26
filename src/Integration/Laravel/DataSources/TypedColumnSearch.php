<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Integration\Laravel\DataSources;

use Illuminate\Database\Eloquent\Builder;

final class TypedColumnSearch
{
    /**
     * @param list<string> $columns
     */
    public function apply(Builder $query, array $columns, string $value): void
    {
        $postgres = $query->getModel()->getConnection()->getDriverName() === 'pgsql';
        $metadata = app(ColumnMetadata::class);
        $types = [];
        if ($postgres) {
            foreach ($columns as $column) {
                $types[$column] = $metadata->typeFor($query, $column);
            }
        }

        $query->where(function (Builder $builder) use ($columns, $value, $types, $postgres): void {
            $count = 0;
            foreach ($columns as $column) {
                $type = $types[$column] ?? null;
                if ($postgres && $type === null) {
                    continue;
                }
                if ($type === 'uuid') {
                    if (!str($value)->isUuid()) {
                        continue;
                    }
                    $builder->where($column, '=', $value, $count++ === 0 ? 'and' : 'or');

                    continue;
                }
                if ($type !== null && ! in_array($type, ['text', 'varchar', 'bpchar', 'char', 'citext', 'name'], true)) {
                    // PostgreSQL does not define LIKE on arbitrary native column types.
                    continue;
                }
                $wrapped = $builder->getQuery()->getGrammar()->wrap($column);
                $escaped = strtr($value, ['!' => '!!', '%' => '!%', '_' => '!_']);
                $builder->whereRaw("$wrapped like ? escape '!'", ['%'.$escaped.'%'], $count++ === 0 ? 'and' : 'or');
            }
            if ($count === 0) {
                $builder->whereRaw('1 = 0');
            }
        });
    }
}
