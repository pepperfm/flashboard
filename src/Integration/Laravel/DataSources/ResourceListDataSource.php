<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Integration\Laravel\DataSources;

use Illuminate\Container\Attributes\Singleton;
use Illuminate\Support\Arr;
use Pepperfm\Flashboard\Contracts\Resources\Resource;
use Pepperfm\Flashboard\Core\Authorization\Visibility\ScreenAccessResolver;
use Pepperfm\Flashboard\Core\Extensions\ExtensionRegistry;
use Pepperfm\Flashboard\Core\Hooks\RuntimeHookDispatcher;
use Pepperfm\Flashboard\Core\Resources\ResourceSurfaceResolver;
use Pepperfm\Flashboard\Core\Runtime\Assemblers\TablePayloadAssembler;
use Pepperfm\Flashboard\Integration\Laravel\Auth\PanelAuthenticator;

#[Singleton]
final readonly class ResourceListDataSource
{
    private const int MAX_PER_PAGE = 100;

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
        $user = $this->authenticator->user();
        $columns = $this->visibleColumns($resourceClass, $table->columns(), $user);
        $relatedPaths = new RelatedTextPath($this->screenAccessResolver, $this->extensionRegistry);
        $relatedColumns = [];
        $columns = array_values(array_filter($columns, static function (array $column) use ($relatedPaths, $resourceClass, $query, $user, &$relatedColumns): bool {
            $key = $column['key'];
            if (($column['type'] ?? null) !== 'text' || !$relatedPaths->isRelationPath($query->getModel(), $key)) {
                return true;
            }

            $subquery = $relatedPaths->query($resourceClass, $query, $key, $user);
            if ($subquery === null) {
                return false;
            }

            $relatedColumns[$key] = $subquery;

            return true;
        }));

        if ($relatedColumns !== []) {
            if ($query->getQuery()->columns === null) {
                $query->select($query->getModel()->qualifyColumn('*'));
            }

            foreach ($relatedColumns as $key => $subquery) {
                $query->selectSub($subquery, RelatedTextPath::alias($key));
            }
        }
        $search = trim((string) $request->query('search', ''));
        $sort = (string) $request->query('sort', '');
        $direction = strtolower((string) $request->query('direction', 'asc')) === 'desc' ? 'desc' : 'asc';
        $perPage = min(self::MAX_PER_PAGE, max(1, (int) $request->query('per_page', (string) $table->pagination())));
        $filters = $request->query('filters', []);

        $searchableColumns = $this->searchableColumns($columns);
        $sortableColumns = $this->sortableColumns($columns);
        $listFilters = new ResourceListFilters($this->screenAccessResolver);
        $tableFilters = $listFilters->visibleFilters($resourceClass, $table->filters(), $user);
        $filterDefinitions = $listFilters->filterDefinitions($tableFilters);

        if ($search !== '' && $searchableColumns !== []) {
            $this->applyGlobalSearch($query, $searchableColumns, $search, $relatedColumns);
        }

        $listFilters->apply($query, $filters, $filterDefinitions);

        if ($sort !== '' && in_array($sort, $sortableColumns, true)) {
            $query->orderBy($relatedColumns[$sort] ?? $sort, $direction);
        }

        $paginator = $query->paginate($perPage);
        $rows = new ResourceListRows(
            $this->screenAccessResolver,
            $this->extensionRegistry,
            $this->resourceSurfaceResolver,
        )->assemble($resourceClass, $paginator->items(), $columns, $relatedColumns, $user);

        $payload = [
            'columns' => $columns,
            'rows' => $rows,
            'routes' => [
                'create' => !$this->screenAccessResolver->canCreateRecord($resourceClass, $user) ? null : route(
                    config('flashboard.route_name_prefix', 'flashboard.')
                    . 'resources.' . $resourceClass::key() . '.create',
                ),
            ],
            'filters' => $tableFilters,
            'active_filters' => is_array($filters) ? $listFilters->activeFilters($filters, $filterDefinitions) : [],
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
     * @param class-string<Resource> $resourceClass
     * @param list<array<string, mixed>> $columns
     *
     * @return list<array<string, mixed>>
     */
    private function visibleColumns(
        string $resourceClass,
        array $columns,
        ?\Illuminate\Contracts\Auth\Authenticatable $user,
    ): array {
        $normalizedColumns = array_values(array_map(
            fn(array $column): array => array_merge($column, [
                'key' => (string) $column['key'],
                'label' => (string) ($column['label'] ?? str($this->getColumnKey($column))->headline()->value()),
                'sortable' => (bool) ($column['sortable'] ?? false),
                'searchable' => (bool) ($column['searchable'] ?? false),
            ]),
            $columns,
        ));

        return array_values(array_filter(
            $normalizedColumns,
            fn(array $column): bool => $this->screenAccessResolver->canViewField(
                $resourceClass,
                (string) $column['key'],
                $user,
            ),
        ));
    }

    /**
     * @param list<array<string, mixed>> $columns
     *
     * @return list<string>
     */
    private function searchableColumns(array $columns): array
    {
        return array_values(array_map(
            static fn (array $column): string => (string) $column['key'],
            array_values(array_filter(
                $columns,
                static fn (array $column): bool => $column['searchable'] === true,
            )),
        ));
    }

    /**
     * @param list<array<string, mixed>> $columns
     *
     * @return list<string>
     */
    private function sortableColumns(array $columns): array
    {
        return array_values(array_map(
            static fn (array $column): string => (string) $column['key'],
            array_values(array_filter(
                $columns,
                static fn (array $column): bool => $column['sortable'] === true,
            )),
        ));
    }

    /**
     * @param list<string> $columns
     * @param array<string, \Illuminate\Database\Query\Builder> $relatedColumns
     */
    private function applyGlobalSearch(
        \Illuminate\Database\Eloquent\Builder $query,
        array $columns,
        string $value,
        array $relatedColumns,
    ): void {
        $direct = $relatedColumns
            |> array_keys(...)
            |> (static fn($x) => array_diff($columns, $x))
            |> array_values(...);
        $related = array_intersect_key($relatedColumns, array_fill_keys($columns, true));

        if ($related === []) {
            new TypedColumnSearch()->apply($query, $direct, $value);

            return;
        }

        $query->where(function (\Illuminate\Database\Eloquent\Builder $builder) use ($direct, $related, $value, $query): void {
            if ($direct !== []) {
                new TypedColumnSearch()->apply($builder, $direct, $value, metadataQuery: $query);
            }

            $cast = match ($builder->getModel()->getConnection()->getDriverName()) {
                'mysql', 'mariadb' => 'CHAR',
                'sqlsrv' => 'VARCHAR(MAX)',
                default => 'TEXT',
            };
            $escaped = strtr($value, ['!' => '!!', '%' => '!%', '_' => '!_']);
            foreach ($related as $subquery) {
                $builder->orWhereRaw(
                    "CAST(({$subquery->toSql()}) AS $cast) like ? escape '!'",
                    [...$subquery->getBindings(), '%' . $escaped . '%'],
                );
            }
        });
    }
}
