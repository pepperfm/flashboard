<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Integration\Laravel\DataSources;

use Illuminate\Support\Arr;
use Pepperfm\Flashboard\Contracts\Resources\Resource;
use Pepperfm\Flashboard\Core\Authorization\Visibility\ScreenAccessResolver;
use Pepperfm\Flashboard\Core\Extensions\ExtensionRegistry;
use Pepperfm\Flashboard\Core\Hooks\RuntimeHookDispatcher;
use Pepperfm\Flashboard\Core\Resources\ResourceSurfaceResolver;
use Pepperfm\Flashboard\Core\Runtime\Assemblers\TablePayloadAssembler;
use Pepperfm\Flashboard\Integration\Laravel\Auth\PanelAuthenticator;

final readonly class ResourceListDataSource
{
    public function __construct(
        private TablePayloadAssembler $tablePayloadAssembler,
        private ScreenAccessResolver $screenAccessResolver,
        private PanelAuthenticator $authenticator,
        private ExtensionRegistry $extensionRegistry,
        private RuntimeHookDispatcher $runtimeHookDispatcher,
        private ResourceSurfaceResolver $resourceSurfaceResolver,
    ) {
    }

    /**
     * @param class-string<Resource> $resourceClass
     *
     * @return array<string, mixed>
     */
    public function resolve(string $resourceClass, \Illuminate\Http\Request $request): array
    {
        $table = $this->tablePayloadAssembler->assemble($resourceClass);
        $query = $this->extensionRegistry->extendQuery($resourceClass, $resourceClass::query());
        $this->runtimeHookDispatcher->dispatch($resourceClass, 'resource.index.query', [
            'search' => $request->query('search'),
            'filters' => $request->query('filters'),
        ]);
        $search = trim((string) $request->query('search', ''));
        $sort = (string) $request->query('sort', '');
        $direction = strtolower((string) $request->query('direction', 'asc')) === 'desc' ? 'desc' : 'asc';
        $perPage = max(1, (int) $request->query('per_page', (string) $table->pagination()));
        $filters = $request->query('filters', []);

        $searchableColumns = $table->searchableColumns();
        $sortableColumns = $table->sortableColumns();
        $tableFilters = $this->tableFilters($resourceClass, $table->filters());
        $filterColumns = $this->filterColumns($tableFilters);

        if ($search !== '' && $searchableColumns !== []) {
            $query->where(function (\Illuminate\Database\Eloquent\Builder $builder) use (
                $searchableColumns,
                $search
            ): void {
                foreach ($searchableColumns as $index => $column) {
                    if ($index === 0) {
                        $builder->where($column, 'like', "%$search%");
                        continue;
                    }

                    $builder->orWhere($column, 'like', "%$search%");
                }
            });
        }

        if (is_array($filters)) {
            foreach ($filters as $key => $value) {
                if (!is_string($key) || $value === null || $value === '') {
                    continue;
                }

                if (!array_key_exists($key, $filterColumns)) {
                    continue;
                }

                $query->where($filterColumns[$key], $value);
            }
        }

        if ($sort !== '' && in_array($sort, $sortableColumns, true)) {
            $query->orderBy($sort, $direction);
        }

        $paginator = $query->paginate($perPage);
        $user = $this->authenticator->user();
        $hasDetailSurface = $this->resourceSurfaceResolver->hasDetailSurfaceForResource($resourceClass);
        $columns = array_values(array_map(
            fn(array $column): array => array_merge($column, [
                'key' => (string) $column['key'],
                'label' => (string) ($column['label'] ?? str($this->getColumnKey($column))->headline()->value()),
                'sortable' => (bool) ($column['sortable'] ?? false),
                'searchable' => (bool) ($column['searchable'] ?? false),
            ]),
            $table->columns(),
        ));
        $columns = array_values(array_filter(
            $columns,
            fn(array $column): bool => $this->screenAccessResolver->canViewField(
                $resourceClass,
                (string) $column['key'],
                $user,
            ),
        ));

        $rows = [];

        foreach ($paginator->items() as $record) {
            if (!$record instanceof \Illuminate\Database\Eloquent\Model) {
                continue;
            }

            $row = [
                'id' => $record->getKey(),
                'attributes' => [],
                'links' => [
                    'detail' => $hasDetailSurface
                        ? route(
                            config('flashboard.route_name_prefix', 'flashboard.')
                            . 'resources.' . $resourceClass::key() . '.detail',
                            ['record' => $record->getKey()],
                        )
                        : null,
                    'edit' => route(
                        config('flashboard.route_name_prefix', 'flashboard.')
                        . 'resources.' . $resourceClass::key() . '.edit',
                        ['record' => $record->getKey()],
                    ),
                ],
            ];

            foreach ($columns as $column) {
                $key = (string) $column['key'];
                $row['attributes'][$key] = data_get($record, $key);
            }

            $rows[] = $row;
        }

        $payload = [
            'columns' => $columns,
            'rows' => $rows,
            'routes' => [
                'create' => route(
                    config('flashboard.route_name_prefix', 'flashboard.')
                    . 'resources.' . $resourceClass::key() . '.create',
                ),
            ],
            'filters' => $tableFilters,
            'active_filters' => is_array($filters) ? $this->activeFilters($filters, $filterColumns) : [],
            'scopes' => $table->scopes(),
            'search' => $search,
            'sort' => $sort,
            'direction' => $direction,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];

        return $this->extensionRegistry->extendPayload($resourceClass, 'index', $payload);
    }

    private function getColumnKey(?array $column = null): string
    {
        return (string) Arr::get($column, 'key', 'value');
    }

    /**
     * @param list<array<string, mixed>> $filters
     *
     * @return array<string, string>
     */
    private function filterColumns(array $filters): array
    {
        $columns = [];

        foreach ($filters as $filter) {
            $key = Arr::get($filter, 'key');

            if (!is_string($key) || $key === '') {
                continue;
            }

            $queryColumn = Arr::get($filter, 'query_column', $key);

            if (!is_string($queryColumn) || $queryColumn === '') {
                continue;
            }

            $columns[$key] = $queryColumn;
        }

        return $columns;
    }

    /**
     * @param class-string<Resource> $resourceClass
     * @param list<array<string, mixed>> $filters
     *
     * @return list<array<string, mixed>>
     */
    private function tableFilters(string $resourceClass, array $filters): array
    {
        return array_values(array_map(function (array $filter) use ($resourceClass): array {
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
     * @param array<string, string> $filterColumns
     *
     * @return array<string, mixed>
     */
    private function activeFilters(array $filters, array $filterColumns): array
    {
        $activeFilters = [];

        foreach ($filters as $key => $value) {
            if (!is_string($key) || !array_key_exists($key, $filterColumns)) {
                continue;
            }

            $activeFilters[$key] = $value;
        }

        return $activeFilters;
    }
}
