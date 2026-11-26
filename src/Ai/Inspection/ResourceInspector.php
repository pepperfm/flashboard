<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Ai\Inspection;

use Pepperfm\Flashboard\Contracts\Resources\Resource;
use Pepperfm\Flashboard\Core\Detail\Builders\Detail;
use Pepperfm\Flashboard\Core\Forms\Builders\Form;
use Pepperfm\Flashboard\Core\Metrics\Metric;
use Pepperfm\Flashboard\Core\Tables\Builders\Table;
use Pepperfm\Flashboard\Contracts\Actions\ContextualActionContract;
use Pepperfm\Flashboard\Contracts\Actions\FormActionContract;
use Pepperfm\Flashboard\Contracts\Actions\ActionContract;
use Pepperfm\Flashboard\Contracts\Tables\TableActionContract;

final readonly class ResourceInspector
{
    private const int MAX_NODES = 200;

    public function __construct(private RegisteredResourceLocator $locator)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function inspect(string $identifier): array
    {
        $resource = $this->locator->resolve($identifier);
        $table = $resource::table(Table::make())->toArray();
        $form = $resource::form(Form::make())->fieldSchema();
        $detail = $resource::detail(Detail::make())->entrySchema();

        return [
            'class' => $resource,
            'key' => $resource::key(),
            'model' => $resource::model(),
            'route_base_path' => $resource::routeBasePath(),
            'policy' => $resource::policy(),
            'read_only' => $resource::isReadOnly(),
            'fields' => $this->nodes($form),
            'columns' => $this->nodes((array) ($table['columns'] ?? [])),
            'filters' => $this->nodes((array) ($table['filters'] ?? [])),
            'detail_entries' => $this->nodes($detail),
            'relations' => $this->nodes(array_map(static fn($relation): array => $relation->toArray(), $resource::relations())),
            'actions' => $this->actions($resource),
            'metrics' => $this->nodes(array_map(static fn(Metric $metric): array => $metric->toArray(), $resource::metrics())),
            'runtime_authorization' => 'not_evaluated',
            'schema_limit' => self::MAX_NODES,
        ];
    }

    /**
     * @param array<array-key, mixed> $nodes
     *
     * @return list<array<string, string|int|bool|null>>
     */
    private function nodes(array $nodes): array
    {
        $result = [];
        $keys = [
            'key', 'type', 'renderer', 'kind', 'column', 'chart', 'span', 'searchable', 'sortable',
            'required', 'disabled', 'visible', 'relationship', 'related_resource', 'related_model',
            'foreign_key', 'owner_key', 'title_attribute', 'read_only', 'attachable', 'detachable',
            'replaceable', 'syncable', 'show_on_edit', 'show_on_detail',
        ];

        foreach (array_slice($nodes, 0, self::MAX_NODES) as $node) {
            if (!is_array($node)) {
                continue;
            }

            $item = [];
            foreach ($keys as $key) {
                $value = $node[$key] ?? null;
                if (is_bool($value) || is_int($value) || (is_string($value) && strlen($value) <= 256)) {
                    $item[$key] = $value;
                }
            }

            $result[] = $item;
        }

        return $result;
    }

    /**
     * @param class-string<Resource> $resource
     *
     * @return list<array<string, mixed>>
     */
    private function actions(string $resource): array
    {
        $result = [];

        foreach (array_slice($resource::actions(), 0, self::MAX_NODES) as $action) {
            if (!$action instanceof ActionContract && !$action instanceof TableActionContract) {
                continue;
            }

            $result[] = [
                'key' => $action->key(),
                'class' => $action::class,
                'scope' => $action instanceof ContextualActionContract ? $action->actionScope()->value : null,
                'ability' => $action instanceof ContextualActionContract ? $action->requiredAbility() : null,
                'has_form' => $action instanceof FormActionContract && $action->hasForm(),
            ];
        }

        return $result;
    }
}
