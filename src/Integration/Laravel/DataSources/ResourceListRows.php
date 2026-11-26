<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Integration\Laravel\DataSources;

use Illuminate\Support\Arr;
use Pepperfm\Flashboard\Contracts\Actions\ActionScope;
use Pepperfm\Flashboard\Contracts\Actions\ContextualActionContract;
use Pepperfm\Flashboard\Contracts\Resources\Resource;
use Pepperfm\Flashboard\Contracts\Tables\TableActionContract;
use Pepperfm\Flashboard\Core\Authorization\Visibility\ScreenAccessResolver;
use Pepperfm\Flashboard\Core\Extensions\ExtensionRegistry;
use Pepperfm\Flashboard\Core\Resources\ResourceSurfaceResolver;
use Pepperfm\Flashboard\Core\Tables\Actions\TableAction;
use Pepperfm\Flashboard\Core\Tables\Columns\DateColumn;
use Pepperfm\Flashboard\Integration\Laravel\Runtime\ResourceActionPayloadAssembler;

/*
 * Projects paginated model records into the resource table payload.
 */
final readonly class ResourceListRows
{
    public function __construct(
        private ScreenAccessResolver $screenAccessResolver,
        private ExtensionRegistry $extensionRegistry,
        private ResourceSurfaceResolver $resourceSurfaceResolver,
    ) {
    }

    /**
     * @param class-string<Resource> $resourceClass
     * @param iterable<mixed> $records
     * @param list<array<string, mixed>> $columns
     * @param array<string, \Illuminate\Database\Query\Builder> $relatedColumns
     *
     * @return list<array<string, mixed>>
     */
    public function assemble(
        string $resourceClass,
        iterable $records,
        array $columns,
        array $relatedColumns,
        ?\Illuminate\Contracts\Auth\Authenticatable $user,
    ): array {
        $hasDetailSurface = $this->resourceSurfaceResolver->hasDetailSurfaceForResource($resourceClass);
        $configuredRowActions = $this->configuredRowActions($this->resourceRowActions($resourceClass));

        $recordActions = array_filter(
            $this->resourceSurfaceResolver->actions($resourceClass),
            static fn ($action): bool => $action instanceof ContextualActionContract
                && $action->actionScope() === ActionScope::Record,
        );
        $rows = [];

        foreach ($records as $record) {
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
                $canViewRecord,
                $canEditRecord,
            );

            $executableRowActions = [];
            foreach ($recordActions as $action) {
                if (!$this->screenAccessResolver->canExecuteAction($resourceClass, $action, $user, $record)) {
                    continue;
                }

                $payload = app(ResourceActionPayloadAssembler::class)
                    ->assemble($action, $resourceClass, $record->getKey());
                if (!in_array($action->key(), array_column($rowActions, 'key'), true)) {
                    $executableRowActions[] = $payload;
                }
            }
            foreach ($this->extensionRegistry->extendActions($resourceClass, $executableRowActions) as $action) {
                if (Arr::get($action, 'visible', true) !== false) {
                    $rowActions[] = $action;
                }
            }

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
                $row['attributes'][$key] = isset($relatedColumns[$key])
                    ? $record->getAttribute(RelatedTextPath::alias($key))
                    : $this->columnValue($record, $column);
            }

            $rows[] = $row;
        }

        return $rows;
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
        bool $canViewRecord,
        bool $canEditRecord,
    ): array {
        $rowActions = [];

        foreach ($actions as $action) {
            $key = Arr::get($action, 'key');

            if (!is_string($key) || $key === '') {
                continue;
            }

            $rowAction = match ($key) {
                TableAction::KEY_VIEW => $this->viewRowAction($resourceClass, $record, $action, $canViewRecord),
                TableAction::KEY_EDIT => $this->editRowAction($resourceClass, $record, $action, $canEditRecord),
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
        bool $canViewRecord,
    ): ?array {
        if (!$canViewRecord) {
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
        bool $canEditRecord,
    ): ?array {
        if (!$canEditRecord) {
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
}
