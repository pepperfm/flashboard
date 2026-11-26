<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Integration\Laravel\DataSources;

final class AuthorizedOptionPage
{
    /**
     * Return a page plus one authorized item for has_more. For policies that
     * depend on a model, count visible rows rather than raw SQL offsets.
     *
     * @template T of array
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param \Closure(\Illuminate\Database\Eloquent\Model): (T|null) $map
     * @return list<T>
     */
    public function resolve(
        \Illuminate\Database\Eloquent\Builder $query,
        int $page,
        int $perPage,
        \Closure $map,
        bool $filterRecords,
    ): array {
        $offset = max(0, ($page - 1) * $perPage);
        $records = $filterRecords
            ? $query->lazy(max(100, $perPage + 1))
            : $query->offset($offset)->limit($perPage + 1)->get();
        $skip = $filterRecords ? $offset : 0;
        $items = [];
        foreach ($records as $record) {
            $item = $map($record);
            if ($item === null) {
                continue;
            }
            if ($skip > 0) {
                --$skip;

                continue;
            }
            $items[] = $item;
            if (count($items) > $perPage) {
                break;
            }
        }

        return $items;
    }
}
