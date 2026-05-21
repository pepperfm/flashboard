<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Integration\Laravel\DataSources;

use Illuminate\Container\Attributes\Singleton;
use Illuminate\Support\Arr;
use Pepperfm\Flashboard\Contracts\Resources\Resource;
use Pepperfm\Flashboard\Contracts\Tables\Filters\SelectFilterOptionsQuery;
use Pepperfm\Flashboard\Contracts\Tables\Filters\SelectFilterOptionsResult;
use Pepperfm\Flashboard\Core\Extensions\ExtensionRegistry;
use Pepperfm\Flashboard\Core\Runtime\Assemblers\TablePayloadAssembler;
use Pepperfm\Flashboard\Core\Tables\Filters\SelectFilter;
use Pepperfm\Flashboard\Integration\Laravel\Auth\PanelAuthenticator;
use Pepperfm\Flashboard\Support\Schema\SchemaNodeNormalizer;

#[Singleton]
final readonly class ResourceFilterOptionsDataSource
{
    private const int MAX_PER_PAGE = 100;
    private const int MAX_SELECTED_VALUES = 200;

    public function __construct(
        private TablePayloadAssembler $tablePayloadAssembler,
        private PanelAuthenticator $authenticator,
        private ExtensionRegistry $extensionRegistry,
    ) {
    }

    /**
     * @param class-string<Resource> $resourceClass
     *
     * @return array{items: list<array{label: string, value: string|int|bool}>, meta: array{has_more: bool, next_page: int|null}}
     */
    public function resolve(string $resourceClass, string $filterKey, \Illuminate\Http\Request $request): array
    {
        $filter = $this->findLazySelectFilter($resourceClass, $filterKey);

        if ($filter === null) {
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
        }

        $payload = $filter['payload'];
        $selectFilter = $filter['filter'];
        $queryColumn = (string) Arr::get($payload, 'query_column', $filterKey);
        $optionValueColumn = (string) Arr::get($payload, 'option_value_column', $queryColumn);
        $optionLabelColumn = (string) Arr::get($payload, 'option_label_column', $optionValueColumn);
        $page = max(1, (int) $request->query('page', '1'));
        $perPage = min(
            self::MAX_PER_PAGE,
            max(1, (int) $request->query(
                'per_page',
                (string) Arr::get($payload, 'options_per_page', SelectFilter::DEFAULT_OPTIONS_PER_PAGE),
            )),
        );
        $search = trim((string) $request->query('search', ''));
        $selectedValues = $this->scalarValues($request->query('selected'));
        $selected = $selectedValues[0] ?? null;

        $optionsQuery = new SelectFilterOptionsQuery(
            resourceClass: $resourceClass,
            filterKey: $filterKey,
            queryColumn: $queryColumn,
            search: $search,
            page: $page,
            perPage: $perPage,
            selected: $selected,
            user: $this->authenticator->user(),
            request: $request,
            selectedValues: $selectedValues,
        );

        if ($selectFilter instanceof SelectFilter && $selectFilter->hasLazyOptionsResolver()) {
            return $selectFilter->resolveLazyOptions($optionsQuery)?->toArray()
                ?? SelectFilterOptionsResult::make([])->toArray();
        }

        return $this->defaultOptions(
            $resourceClass,
            $optionValueColumn,
            $optionLabelColumn,
            $optionsQuery,
        )->toArray();
    }

    /**
     * @param class-string<Resource> $resourceClass
     *
     * @return array{filter: SelectFilter|null, payload: array<string, mixed>}|null
     */
    private function findLazySelectFilter(string $resourceClass, string $filterKey): ?array
    {
        foreach ($this->tablePayloadAssembler->table($resourceClass)->rawFilters() as $filter) {
            $payload = SchemaNodeNormalizer::normalizeKeyedNode($filter);

            if (($payload['key'] ?? null) !== $filterKey) {
                continue;
            }

            if (($payload['type'] ?? null) !== 'select' || ($payload['lazy'] ?? false) !== true) {
                return null;
            }

            return [
                'filter' => $filter instanceof SelectFilter ? $filter : null,
                'payload' => $payload,
            ];
        }

        return null;
    }

    /**
     * @param class-string<Resource> $resourceClass
     */
    private function defaultOptions(
        string $resourceClass,
        string $optionValueColumn,
        string $optionLabelColumn,
        SelectFilterOptionsQuery $optionsQuery,
    ): SelectFilterOptionsResult {
        $options = $this->defaultOptionRows(
            $resourceClass,
            $optionValueColumn,
            $optionLabelColumn,
            $optionsQuery,
        );
        $hasMore = count($options) > $optionsQuery->perPage;
        $options = array_slice($options, 0, $optionsQuery->perPage);
        $items = array_values(array_filter(array_map(
            fn (array $option): ?array => $this->optionFromRow($option['value'], $option['label']),
            $options,
        )));

        if ($optionsQuery->selectedValues !== []) {
            $selectedItems = $this->selectedOptions(
                $resourceClass,
                $optionValueColumn,
                $optionLabelColumn,
                $optionsQuery->selectedValues,
            );
            $missingSelectedItems = [];

            foreach ($selectedItems as $selectedItem) {
                if (!$this->hasOptionValue($items, $selectedItem['value'])) {
                    $missingSelectedItems[] = $selectedItem;
                }
            }

            $items = array_merge($missingSelectedItems, $items);
        }

        return SelectFilterOptionsResult::make(
            $items,
            $hasMore,
            $hasMore ? $optionsQuery->page + 1 : null,
        );
    }

    /**
     * @param class-string<Resource> $resourceClass
     *
     * @return list<mixed>
     */
    private function defaultOptionRows(
        string $resourceClass,
        string $optionValueColumn,
        string $optionLabelColumn,
        SelectFilterOptionsQuery $optionsQuery,
    ): array {
        $query = $this->extensionRegistry->extendQuery($resourceClass, $resourceClass::query())
            ->select([
                $optionValueColumn . ' as flashboard_option_value',
                $optionLabelColumn . ' as flashboard_option_label',
            ])
            ->whereNotNull($optionValueColumn)
            ->distinct();

        if ($optionLabelColumn !== $optionValueColumn) {
            $query->whereNotNull($optionLabelColumn);
        }

        if ($optionsQuery->search !== '') {
            $query->where($optionLabelColumn, 'like', '%' . $optionsQuery->search . '%');
        }

        return $query
            ->orderBy($optionLabelColumn)
            ->orderBy($optionValueColumn)
            ->offset($optionsQuery->offset())
            ->limit($optionsQuery->perPage + 1)
            ->get()
            ->map(static fn (object $record): array => [
                'label' => data_get($record, 'flashboard_option_label'),
                'value' => data_get($record, 'flashboard_option_value'),
            ])
            ->all();
    }

    /**
     * @param class-string<Resource> $resourceClass
     * @param list<string|int|bool> $selectedValues
     *
     * @return list<array{label: string, value: string|int|bool}>
     */
    private function selectedOptions(
        string $resourceClass,
        string $optionValueColumn,
        string $optionLabelColumn,
        array $selectedValues,
    ): array {
        $records = $this->extensionRegistry->extendQuery($resourceClass, $resourceClass::query())
            ->select([
                $optionValueColumn . ' as flashboard_option_value',
                $optionLabelColumn . ' as flashboard_option_label',
            ])
            ->whereIn($optionValueColumn, $selectedValues)
            ->whereNotNull($optionValueColumn)
            ->get();
        $itemsByValue = [];

        foreach ($records as $record) {
            $item = $this->optionFromRow(
                data_get($record, 'flashboard_option_value'),
                data_get($record, 'flashboard_option_label'),
            );

            if ($item !== null) {
                $itemsByValue[(string) $item['value']] = $item;
            }
        }

        return array_values(array_filter(array_map(
            fn (string|int|bool $selected): ?array => $itemsByValue[(string) $selected]
                ?? $this->optionFromRow($selected, $selected),
            $selectedValues,
        )));
    }

    /**
     * @return array{label: string, value: string|int|bool}|null
     */
    private function optionFromRow(mixed $value, mixed $label): ?array
    {
        $value = $this->scalarValue($value);

        if ($value === null) {
            return null;
        }

        return [
            'label' => (string) ($this->scalarValue($label) ?? $value),
            'value' => $value,
        ];
    }

    /**
     * @param list<array{label: string, value: string|int|bool}> $items
     */
    private function hasOptionValue(array $items, string|int|bool $value): bool
    {
        foreach ($items as $item) {
            if ((string) $item['value'] === (string) $value) {
                return true;
            }
        }

        return false;
    }

    private function scalarValue(mixed $value): string|int|bool|null
    {
        if (is_string($value) || is_int($value) || is_bool($value)) {
            return $value;
        }

        return null;
    }

    /**
     * @return list<string|int|bool>
     */
    private function scalarValues(mixed $value): array
    {
        $values = is_array($value) ? $value : [$value];
        $normalized = [];

        foreach ($values as $item) {
            $scalarValue = $this->scalarValue($item);

            if ($scalarValue === null) {
                continue;
            }

            if (array_key_exists((string) $scalarValue, $normalized)) {
                continue;
            }

            $normalized[(string) $scalarValue] = $scalarValue;

            if (count($normalized) >= self::MAX_SELECTED_VALUES) {
                break;
            }
        }

        return array_values($normalized);
    }
}
