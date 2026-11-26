<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Integration\Laravel\DataSources;

use Illuminate\Support\Arr;
use Pepperfm\Flashboard\Contracts\Resources\Resource;
use Pepperfm\Flashboard\Core\Authorization\Visibility\ScreenAccessResolver;
use Pepperfm\Flashboard\Core\Tables\Filters\DateFilter;
use Pepperfm\Flashboard\Core\Tables\Filters\DateRangeFilter;
use Pepperfm\Flashboard\Core\Tables\Filters\InputFilter;

/*
 * Resolves allowed list filters and applies their existing query semantics.
 */
final readonly class ResourceListFilters
{
    private const string ISO_DATE_PATTERN = '/^\d{4}-\d{2}-\d{2}$/';

    private const int MAX_FILTER_VALUES = 200;

    public function __construct(private ScreenAccessResolver $screenAccessResolver)
    {
    }

    /**
     * @param class-string<Resource> $resourceClass
     * @param list<array<string, mixed>> $filters
     * @return list<array<string, mixed>>
     */
    public function visibleFilters(string $resourceClass, array $filters, ?\Illuminate\Contracts\Auth\Authenticatable $user): array
    {
        return array_values(array_filter(
            $this->tableFilters($resourceClass, $filters),
            fn (array $filter): bool => $this->canViewRangeFilter($resourceClass, $filter, $user),
        ));
    }

    /**
     * @param array<string, array{column: string, match: string, multiple: bool, type: string}> $filterDefinitions
     */
    public function apply(\Illuminate\Database\Eloquent\Builder $query, mixed $filters, array $filterDefinitions): void
    {
        if (is_array($filters)) {
            foreach ($filters as $key => $value) {
                if (!is_string($key) || !array_key_exists($key, $filterDefinitions)) {
                    continue;
                }

                $filterDefinition = $filterDefinitions[$key];
                if ($filterDefinition['type'] === DateRangeFilter::TYPE) {
                    $constraint = new DateRangeConstraint();
                    $constraint->apply($query, $filterDefinition['column'], $constraint->normalize($value));

                    continue;
                }

                if ($this->usesDateFilter($filterDefinition)) {
                    $dateValue = $this->filterDateValue($value);
                    if ($dateValue === null) {
                        continue;
                    }

                    $query->whereDate($filterDefinition['column'], '=', $dateValue);

                    continue;
                }
                if ($filterDefinition['multiple']) {
                    $values = $this->filterValues($value);
                    if ($values === []) {
                        continue;
                    }

                    $query->whereIn($filterDefinition['column'], $values);

                    continue;
                }

                $scalarValue = $this->filterValue($value);
                if ($scalarValue === null) {
                    continue;
                }

                if ($this->usesContainsMatch($filterDefinition)) {
                    $this->applyContainsFilter($query, $filterDefinition['column'], (string) $scalarValue);

                    continue;
                }

                $query->where($filterDefinition['column'], $scalarValue);
            }
        }
    }

    /**
     * @param list<array<string, mixed>> $filters
     *
     * @return array<string, array{column: string, match: string, multiple: bool, type: string}>
     */
    public function filterDefinitions(array $filters): array
    {
        $definitions = [];

        foreach ($filters as $filter) {
            $key = Arr::get($filter, 'key');
            if (!is_string($key) || $key === '') {
                continue;
            }

            $queryColumn = Arr::get($filter, 'query_column', $key);
            if (!is_string($queryColumn) || $queryColumn === '') {
                continue;
            }

            $definitions[$key] = [
                'column' => $queryColumn,
                'match' => $this->filterMatch($filter),
                'multiple' => Arr::get($filter, 'multiple') === true,
                'type' => $this->filterType($filter),
            ];
        }

        return $definitions;
    }

    /**
     * @param class-string<Resource> $resourceClass
     * @param array<string, mixed> $filter
     */
    private function canViewRangeFilter(
        string $resourceClass,
        array $filter,
        ?\Illuminate\Contracts\Auth\Authenticatable $user,
    ): bool {
        if ($this->filterType($filter) !== DateRangeFilter::TYPE) {
            return true;
        }

        $key = Arr::get($filter, 'key');
        $column = Arr::get($filter, 'query_column', $key);

        if (!is_string($key) || !is_string($column)) {
            return false;
        }

        $modelClass = $resourceClass::model();
        $model = new $modelClass();
        $field = str($column)->afterLast('.')->value();

        return !in_array($field, $model->getHidden(), true)
            && $this->screenAccessResolver->canViewField($resourceClass, $key, $user)
            && $this->screenAccessResolver->canViewField($resourceClass, $field, $user);
    }

    /**
     * @param array<string, mixed> $filter
     */
    private function filterMatch(array $filter): string
    {
        $match = Arr::get($filter, 'match', InputFilter::MATCH_EXACT);
        if (!is_string($match) || $match === '') {
            return InputFilter::MATCH_EXACT;
        }

        return $match;
    }

    /**
     * @param array<string, mixed> $filter
     */
    private function filterType(array $filter): string
    {
        $type = Arr::get($filter, 'type', '');

        return is_string($type) ? $type : '';
    }

    /**
     * @param class-string<Resource> $resourceClass
     * @param list<array<string, mixed>> $filters
     *
     * @return list<array<string, mixed>>
     */
    private function tableFilters(string $resourceClass, array $filters): array
    {
        return array_values(array_map(static function (array $filter) use ($resourceClass): array {
            $key = Arr::get($filter, 'key');
            if (($filter['lazy'] ?? false) !== true || !is_string($key) || $key === '') {
                return $filter;
            }

            unset($filter['options']);

            return array_merge($filter, [
                'options_url' => route(
                    config('flashboard.route_name_prefix', 'flashboard.')
                    . 'resources.' . $resourceClass::key() . '.filters.options',
                    ['filter' => $key],
                ),
            ]);
        }, $filters));
    }

    /**
     * @param array<array-key, mixed> $filters
     * @param array<string, array{column: string, match: string, multiple: bool, type: string}> $filterDefinitions
     *
     * @return array<string, mixed>
     */
    public function activeFilters(array $filters, array $filterDefinitions): array
    {
        $activeFilters = [];

        foreach ($filters as $key => $value) {
            if (!is_string($key) || !array_key_exists($key, $filterDefinitions)) {
                continue;
            }
            if ($filterDefinitions[$key]['type'] === DateRangeFilter::TYPE) {
                $activeFilters[$key] = new DateRangeConstraint()->normalize($value);

                continue;
            }
            if ($this->usesDateFilter($filterDefinitions[$key])) {
                $dateValue = $this->filterDateValue($value);

                if ($dateValue !== null) {
                    $activeFilters[$key] = $dateValue;
                }

                continue;
            }
            if ($filterDefinitions[$key]['multiple']) {
                $values = $this->filterValues($value);
                if ($values !== []) {
                    $activeFilters[$key] = $values;
                }

                continue;
            }

            $scalarValue = $this->filterValue($value);
            if ($scalarValue !== null) {
                $activeFilters[$key] = $scalarValue;
            }
        }

        return $activeFilters;
    }

    /**
     * @return list<string|int|float|bool>
     */
    private function filterValues(mixed $value): array
    {
        $values = is_array($value) ? $value : [$value];
        $normalized = [];

        foreach ($values as $item) {
            $scalarValue = $this->filterValue($item);
            if ($scalarValue === null) {
                continue;
            }

            if (array_key_exists((string) $scalarValue, $normalized)) {
                continue;
            }

            $normalized[(string) $scalarValue] = $scalarValue;
            if (count($normalized) >= self::MAX_FILTER_VALUES) {
                break;
            }
        }

        return array_values($normalized);
    }

    private function filterValue(mixed $value): string|int|float|bool|null
    {
        if (!is_scalar($value) || $value === '') {
            return null;
        }

        return $value;
    }

    private function filterDateValue(mixed $value): ?string
    {
        if (!is_string($value) || !preg_match(self::ISO_DATE_PATTERN, $value)) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = \DateTimeImmutable::getLastErrors();

        if (
            $date === false
            || (
                is_array($errors)
                && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)
            )
        ) {
            return null;
        }

        return $date->format('Y-m-d') === $value ? $value : null;
    }

    private function applyContainsFilter(
        \Illuminate\Database\Eloquent\Builder $query,
        string $column,
        string $value,
    ): void {
        new TypedColumnSearch()->apply($query, [$column], $value);
    }

    /**
     * @param array{column: string, match: string, multiple: bool, type: string} $filterDefinition
     */
    private function usesContainsMatch(array $filterDefinition): bool
    {
        return $filterDefinition['type'] === InputFilter::TYPE
            && $filterDefinition['match'] === InputFilter::MATCH_CONTAINS;
    }

    /**
     * @param array{column: string, match: string, multiple: bool, type: string} $filterDefinition
     */
    private function usesDateFilter(array $filterDefinition): bool
    {
        return $filterDefinition['type'] === DateFilter::TYPE;
    }
}
