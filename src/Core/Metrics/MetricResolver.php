<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Core\Metrics;

use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Pepperfm\Flashboard\Contracts\Resources\Resource;
use Pepperfm\Flashboard\Core\Authorization\Visibility\ScreenAccessResolver;
use Pepperfm\Flashboard\Core\Extensions\ExtensionRegistry;
use Pepperfm\Flashboard\Core\Registry\ResourceRegistry;

#[Singleton]
final readonly class MetricResolver
{
    public function __construct(
        private ResourceRegistry $resources,
        private ScreenAccessResolver $access,
        private ExtensionRegistry $extensions,
    ) {
    }

    public function period(Request $request): int
    {
        $value = $request->query('metric_period', '30');

        return in_array($value, ['7', '30', '90', 7, 30, 90], true) ? (int) $value : 30;
    }

    /**
     * @return array{period_days: int, items: list<array<string, mixed>>}
     */
    public function dashboard(Request $request, ?Authenticatable $user): array
    {
        $period = $this->period($request);
        $items = [];

        foreach ($this->resources->all() as $resourceClass) {
            array_push($items, ...$this->metricsFor($resourceClass, $period, $user));
        }

        return ['period_days' => $period, 'items' => $items];
    }

    /**
     * @param class-string<Resource> $resourceClass
     *
     * @return array{period_days: int, items: list<array<string, mixed>>}
     */
    public function resource(string $resourceClass, Request $request, ?Authenticatable $user): array
    {
        $period = $this->period($request);

        return ['period_days' => $period, 'items' => $this->metricsFor($resourceClass, $period, $user)];
    }

    /**
     * @param class-string<Resource> $resourceClass
     *
     * @return list<array<string, mixed>>
     */
    private function metricsFor(string $resourceClass, int $period, ?Authenticatable $user): array
    {
        if (!$this->access->canAccessResource($resourceClass, $user)) {
            return [];
        }

        $items = [];
        foreach ($resourceClass::metrics() as $metric) {
            if (!$metric instanceof Metric) {
                throw new \InvalidArgumentException('Resource::metrics() must return Metric definitions.');
            }

            $definition = $metric->toArray();
            $column = $definition['column'];
            if ($column !== null && !$this->access->canViewField($resourceClass, $column, $user)) {
                continue;
            }

            $ability = $resourceClass::metricAbilityMap()[$definition['key']] ?? null;
            if (is_string($ability) && $ability !== '' && !$this->access->canViewMetric($resourceClass, $ability, $user)) {
                continue;
            }

            // Apply the SAME query extensions as resource lists: tenant scopes must not be bypassed.
            $query = $this->extensions->extendQuery($resourceClass, $resourceClass::query());
            $this->assertColumn($query, $column);
            $items[] = [
                'key' => $resourceClass::key() . '.' . $definition['key'],
                'label' => $definition['label'],
                'resource' => $resourceClass::name(),
                'kind' => $definition['kind'],
                'chart' => $definition['chart'],
                'span' => $definition['span'],
                ...$this->aggregate($query, $definition, $period, $metric),
            ];
        }

        return $items;
    }

    /**
     * @param array{kind: string, column: ?string} $metric
     *
     * @return array<string, mixed>
     */
    private function aggregate(Builder $query, array $metric, int $period, Metric $definition): array
    {
        if ($metric['kind'] === 'count') {
            return ['value' => $query->count()];
        }

        $column = $metric['column'];
        if ($column === null) {
            throw new \InvalidArgumentException('Trend and breakdown metrics require a column.');
        }

        $wrapped = $query->getQuery()->getGrammar()->wrap($column);

        if ($metric['kind'] === 'breakdown') {
            $rows = $query->reorder()->select($column)
                ->selectRaw('COUNT(*) AS aggregate')
                ->groupBy($column)
                ->orderByDesc('aggregate')
                ->limit(10)
                ->get();

            return [
                'points' => $rows->map(static fn ($row): array => [
                    'label' => $definition->resolveBreakdownLabel($row->$column),
                    'value' => (int) $row->aggregate,
                ])->all(),
            ];
        }

        if ($metric['kind'] !== 'trend') {
            throw new \InvalidArgumentException('Unsupported metric kind.');
        }

        $start = CarbonImmutable::now('UTC')->startOfDay()->subDays($period - 1);
        $end = CarbonImmutable::now('UTC')->startOfDay()->addDay();
        $expression = "DATE($wrapped)";

        $rows = $query->where($column, '>=', $start)
            ->where($column, '<', $end)
            ->reorder()
            ->select([])
            ->selectRaw("$expression AS bucket, COUNT(*) AS aggregate")
            ->groupByRaw($expression)
            ->oldest('bucket')
            ->get();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(string) $row->bucket] = (int) $row->aggregate;
        }

        $points = [];
        for ($day = 0; $day < $period; $day++) {
            $label = $start->addDays($day)->toDateString();
            $points[] = ['label' => $label, 'value' => $counts[$label] ?? 0];
        }

        return ['points' => $points];
    }

    private function assertColumn(Builder $query, ?string $column): void
    {
        if ($column === null) {
            return;
        }

        $model = $query->getModel();
        if (!in_array($column, $model->getConnection()->getSchemaBuilder()->getColumnListing($model->getTable()), true)) {
            throw new \InvalidArgumentException("Metric column [{$column}] does not exist on the resource model.");
        }
    }
}
