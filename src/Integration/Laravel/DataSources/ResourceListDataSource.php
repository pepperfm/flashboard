<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Integration\Laravel\DataSources;

use Illuminate\Container\Attributes\Singleton;
use Illuminate\Support\Arr;
use Pepperfm\Flashboard\Contracts\Resources\Resource;
use Pepperfm\Flashboard\Contracts\Tables\TableActionContract;
use Pepperfm\Flashboard\Core\Authorization\Visibility\ScreenAccessResolver;
use Pepperfm\Flashboard\Core\Extensions\ExtensionRegistry;
use Pepperfm\Flashboard\Core\Hooks\RuntimeHookDispatcher;
use Pepperfm\Flashboard\Core\Resources\ResourceSurfaceResolver;
use Pepperfm\Flashboard\Core\Runtime\Assemblers\TablePayloadAssembler;
use Pepperfm\Flashboard\Core\Tables\Actions\TableAction;
use Pepperfm\Flashboard\Core\Tables\Columns\DateColumn;
use Pepperfm\Flashboard\Core\Tables\Filters\DateFilter;
use Pepperfm\Flashboard\Core\Tables\Filters\InputFilter;
use Pepperfm\Flashboard\Integration\Laravel\Auth\PanelAuthenticator;

#[Singleton]
final readonly class ResourceListDataSource
{
    private const string LIKE_ESCAPE_CHARACTER = '\\';

    private const string ISO_DATE_PATTERN = '/^\d{4}-\d{2}-\d{2}$/';

    private const int MAX_FILTER_VALUES = 200;

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
        $search = trim((string) $request->query('search', ''));
        $sort = (string) $request->query('sort', '');
        $direction = strtolower((string) $request->query('direction', 'asc')) === 'desc' ? 'desc' : 'asc';
        $perPage = max(1, (int) $request->query('per_page', (string) $table->pagination()));
        $filters = $request->query('filters', []);

        $searchableColumns = $this->searchableColumns($columns);
        $sortableColumns = $this->sortableColumns($columns);
        $tableFilters = $this->tableFilters($resourceClass, $table->filters());
        $filterDefinitions = $this->filterDefinitions($tableFilters);

        if ($search !== '' && $searchableColumns !== []) {
            $this->applyGlobalSearch($query, $searchableColumns, $search);
        }

        if (is_array($filters)) {
            foreach ($filters as $key => $value) {
                if (!is_string($key) || !array_key_exists($key, $filterDefinitions)) {
                    continue;
                }

                $filterDefinition = $filterDefinitions[$key];

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

        if ($sort !== '' && in_array($sort, $sortableColumns, true)) {
            $query->orderBy($sort, $direction);
        }

        $paginator = $query->paginate($perPage);
        $hasDetailSurface = $this->resourceSurfaceResolver->hasDetailSurfaceForResource($resourceClass);
        $configuredRowActions = $this->configuredRowActions($this->resourceRowActions($resourceClass));

        $rows = [];

        foreach ($paginator->items() as $record) {
            if (!$record instanceof \Illuminate\Database\Eloquent\Model) {
                continue;
            }

            $canViewRecord = $hasDetailSurface && $this->screenAccessResolver->canViewRecord($resourceClass, $user, $record);
            $canEditRecord = $this->screenAccessResolver->canEditRecord($resourceClass, $user, $record);
            $rowActions = $this->rowActions(
                $resourceClass,
                $record,
                $configuredRowActions,
                $hasDetailSurface,
                $user,
            );

            $row = [
                'id' => $record->getKey(),
                'attributes' => [],
                'actions' => $rowActions,
                'links' => [
                    'detail' => $canViewRecord ? $this->resourceRoute($resourceClass, 'detail', $record->getKey()) : null,
                    'edit' => $canEditRecord ? $this->resourceRoute($resourceClass, 'edit', $record->getKey()) : null,
                ],
            ];

            foreach ($columns as $column) {
                $key = (string) $column['key'];
                $row['attributes'][$key] = $this->columnValue($record, $column);
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
            'active_filters' => is_array($filters) ? $this->activeFilters($filters, $filterDefinitions) : [],
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
     *
     * @return list<array<string, mixed>>
     */
    private function resourceRowActions(string $resourceClass): array
    {
        $rowActions = [];

        foreach ($resourceClass::actions() as $action) {
            if ($action instanceof TableActionContract) {
                $rowActions[] = $action->toArray();

                continue;
            }

            if (is_array($action)) {
                $rowActions[] = $action;
            }
        }

        return $rowActions;
    }

    /**
     * @param list<array<string, mixed>> $actions
     *
     * @return list<array<string, mixed>>
     */
    private function configuredRowActions(array $actions): array
    {
        return array_values(array_filter(
            $actions,
            static fn(array $action): bool => Arr::get($action, 'visible', true) !== false,
        ));
    }

    /**
     * @param class-string<Resource> $resourceClass
     * @param list<array<string, mixed>> $actions
     *
     * @return list<array<string, mixed>>
     */
    private function rowActions(
        string $resourceClass,
        \Illuminate\Database\Eloquent\Model $record,
        array $actions,
        bool $hasDetailSurface,
        ?\Illuminate\Contracts\Auth\Authenticatable $user,
    ): array {
        $rowActions = [];

        foreach ($actions as $action) {
            $key = Arr::get($action, 'key');

            if (!is_string($key) || $key === '') {
                continue;
            }

            $rowAction = match ($key) {
                TableAction::KEY_VIEW => $this->viewRowAction($resourceClass, $record, $action, $hasDetailSurface, $user),
                TableAction::KEY_EDIT => $this->editRowAction($resourceClass, $record, $action, $user),
                TableAction::KEY_DELETE => $this->deleteRowAction($resourceClass, $record, $action, $user),
                default => $this->customRowAction($action),
            };

            if ($rowAction !== null) {
                $rowActions[] = $rowAction;
            }
        }

        return $rowActions;
    }

    /**
     * @param class-string<Resource> $resourceClass
     * @param array<string, mixed> $action
     *
     * @return array<string, mixed>|null
     */
    private function viewRowAction(
        string $resourceClass,
        \Illuminate\Database\Eloquent\Model $record,
        array $action,
        bool $hasDetailSurface,
        ?\Illuminate\Contracts\Auth\Authenticatable $user,
    ): ?array {
        if (!$hasDetailSurface || !$this->screenAccessResolver->canViewRecord($resourceClass, $user, $record)) {
            return null;
        }

        return $this->builtInRowAction($action, [
            'key' => TableAction::KEY_VIEW,
            'label' => 'View',
            'icon' => 'i-lucide-eye',
            'method' => TableAction::METHOD_GET,
            'url' => $this->resourceRoute($resourceClass, 'detail', $record->getKey()),
        ]);
    }

    /**
     * @param class-string<Resource> $resourceClass
     * @param array<string, mixed> $action
     *
     * @return array<string, mixed>|null
     */
    private function editRowAction(
        string $resourceClass,
        \Illuminate\Database\Eloquent\Model $record,
        array $action,
        ?\Illuminate\Contracts\Auth\Authenticatable $user,
    ): ?array {
        if (!$this->screenAccessResolver->canEditRecord($resourceClass, $user, $record)) {
            return null;
        }

        return $this->builtInRowAction($action, [
            'key' => TableAction::KEY_EDIT,
            'label' => 'Edit',
            'icon' => 'i-lucide-pencil',
            'method' => TableAction::METHOD_GET,
            'url' => $this->resourceRoute($resourceClass, 'edit', $record->getKey()),
        ]);
    }

    /**
     * @param class-string<Resource> $resourceClass
     * @param array<string, mixed> $action
     *
     * @return array<string, mixed>|null
     */
    private function deleteRowAction(
        string $resourceClass,
        \Illuminate\Database\Eloquent\Model $record,
        array $action,
        ?\Illuminate\Contracts\Auth\Authenticatable $user,
    ): ?array {
        if (!$this->screenAccessResolver->canDeleteRecord($resourceClass, $user, $record)) {
            return null;
        }

        return array_merge($this->builtInRowAction($action, [
            'key' => TableAction::KEY_DELETE,
            'label' => 'Delete',
            'icon' => 'i-lucide-trash-2',
            'color' => 'error',
            'method' => TableAction::METHOD_DELETE,
            'requires_confirmation' => true,
            'url' => $this->resourceRoute($resourceClass, 'destroy', $record->getKey()),
        ]), [
            'requires_confirmation' => true,
        ]);
    }

    /**
     * @param array<string, mixed> $action
     * @param array<string, mixed> $defaults
     *
     * @return array<string, mixed>
     */
    private function builtInRowAction(array $action, array $defaults): array
    {
        return array_merge($defaults, $action, [
            'key' => $defaults['key'],
            'method' => $defaults['method'],
            'url' => $defaults['url'],
            'kind' => (string) Arr::get($action, 'kind', TableAction::KIND_BUILT_IN),
            'visible' => true,
        ]);
    }

    /**
     * @param array<string, mixed> $action
     *
     * @return array<string, mixed>|null
     */
    private function customRowAction(array $action): ?array
    {
        $url = Arr::get($action, 'url');

        if (!is_string($url) || $url === '') {
            return null;
        }

        return array_merge($action, [
            'method' => strtolower((string) Arr::get($action, 'method', TableAction::METHOD_GET)),
            'requires_confirmation' => (bool) Arr::get($action, 'requires_confirmation', false),
            'visible' => true,
        ]);
    }

    /**
     * @param class-string<Resource> $resourceClass
     */
    private function resourceRoute(string $resourceClass, string $route, mixed $recordKey): string
    {
        return route(
            config('flashboard.route_name_prefix', 'flashboard.')
            . 'resources.' . $resourceClass::key() . '.' . $route,
            ['record' => $recordKey],
        );
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
     * @param array<string, mixed> $column
     */
    private function columnValue(\Illuminate\Database\Eloquent\Model $record, array $column): mixed
    {
        $key = $this->getColumnKey($column);
        $value = data_get($record, $key);

        if (!$this->usesDateColumnFormat($column)) {
            return $value;
        }

        return $this->formatDateColumnValue($value, (string) $column['format']);
    }

    /**
     * @param array<string, mixed> $column
     */
    private function usesDateColumnFormat(array $column): bool
    {
        return Arr::get($column, 'type') === DateColumn::TYPE
            && is_string(Arr::get($column, 'format'))
            && Arr::get($column, 'format') !== '';
    }

    private function formatDateColumnValue(mixed $value, string $format): mixed
    {
        if ($value === null || $value === '') {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format($format);
        }

        if (!is_string($value)) {
            return $value;
        }

        try {
            return (new \DateTimeImmutable($value))->format($format);
        } catch (\DateMalformedStringException) {
            return $value;
        }
    }

    /**
     * @param list<array<string, mixed>> $filters
     *
     * @return array<string, array{column: string, match: string, multiple: bool, type: string}>
     */
    private function filterDefinitions(array $filters): array
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
     * @param array<string, array{column: string, match: string, multiple: bool, type: string}> $filterDefinitions
     *
     * @return array<string, mixed>
     */
    private function activeFilters(array $filters, array $filterDefinitions): array
    {
        $activeFilters = [];

        foreach ($filters as $key => $value) {
            if (!is_string($key) || !array_key_exists($key, $filterDefinitions)) {
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
        $query->whereRaw(
            $this->likeExpression($query, $column),
            ['%' . $this->escapeLikeValue($value) . '%'],
        );
    }

    /**
     * @param list<string> $columns
     */
    private function applyGlobalSearch(
        \Illuminate\Database\Eloquent\Builder $query,
        array $columns,
        string $value,
    ): void {
        $search = '%' . $this->escapeLikeValue($value) . '%';

        $query->where(function (\Illuminate\Database\Eloquent\Builder $builder) use ($columns, $search): void {
            foreach ($columns as $index => $column) {
                $builder->whereRaw(
                    $this->likeExpression($builder, $column),
                    [$search],
                    $index === 0 ? 'and' : 'or',
                );
            }
        });
    }

    private function likeExpression(\Illuminate\Database\Eloquent\Builder $query, string $column): string
    {
        return $query->getQuery()->getGrammar()->wrap($column)
            . ' like ? escape '
            . $this->quotedLikeEscapeCharacter($query);
    }

    private function quotedLikeEscapeCharacter(\Illuminate\Database\Eloquent\Builder $query): string
    {
        $connection = $query->getModel()->getConnection();

        if (!$connection instanceof \Illuminate\Database\Connection) {
            return "'\\\\'";
        }

        $quoted = $connection->getPdo()->quote(self::LIKE_ESCAPE_CHARACTER);

        return is_string($quoted) ? $quoted : "'\\\\'";
    }

    private function escapeLikeValue(string $value): string
    {
        return strtr($value, [
            self::LIKE_ESCAPE_CHARACTER => self::LIKE_ESCAPE_CHARACTER . self::LIKE_ESCAPE_CHARACTER,
            '%' => self::LIKE_ESCAPE_CHARACTER . '%',
            '_' => self::LIKE_ESCAPE_CHARACTER . '_',
        ]);
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
