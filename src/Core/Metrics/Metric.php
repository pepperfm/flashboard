<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Core\Metrics;

/*
 * Opt-in, declarative metric definition. Never accepts raw SQL or user-supplied columns.
 */
final class Metric
{
    private string $label;
    private ?string $column = null;
    private string $chart = 'line';
    private ?int $span = null;

    /** @var array<int|string, string> */
    private array $breakdownLabels = [];

    private ?\Closure $breakdownLabel = null;

    /** @param array<int|string, string> $labels */
    public function breakdownLabels(array $labels): self
    {
        $this->breakdownLabels = $labels;

        return $this;
    }

    public function breakdownLabelUsing(callable $label): self
    {
        $this->breakdownLabel = $label(...);

        return $this;
    }

    public function resolveBreakdownLabel(mixed $value): string
    {
        $value = $value instanceof \BackedEnum ? $value->value : $value;
        if ($this->breakdownLabel !== null) {
            return (string) ($this->breakdownLabel)($value);
        }

        return $value === null ? '—' : ($this->breakdownLabels[(string) $value] ?? (string) $value);
    }

    private function __construct(
        private readonly string $kind,
        private readonly string $key,
    ) {
        if (preg_match('/^[a-z][a-z0-9_-]*$/', $key) !== 1) {
            throw new \InvalidArgumentException('Metric keys must contain lowercase letters, numbers, hyphens or underscores.');
        }

        $this->label = str($key)->headline()->value();
        if ($kind === 'breakdown') {
            $this->chart = 'bar';
        }
    }

    public static function count(string $key): self
    {
        return new self('count', $key);
    }

    public static function sum(string $key): self
    {
        return new self('sum', $key);
    }

    public static function avg(string $key): self
    {
        return new self('avg', $key);
    }

    public static function trend(string $key): self
    {
        return new self('trend', $key)->dateColumn('created_at');
    }

    public static function breakdown(string $key): self
    {
        return new self('breakdown', $key);
    }

    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function dateColumn(string $column): self
    {
        if ($this->kind !== 'trend') {
            throw new \InvalidArgumentException('dateColumn() is only supported for trend metrics.');
        }

        return $this->column($column);
    }

    public function column(string $column): self
    {
        if ($this->kind === 'count' || preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/D', $column) !== 1) {
            throw new \InvalidArgumentException('Metric columns must be simple, trusted database column names.');
        }

        $this->column = $column;

        return $this;
    }

    public function chart(string $chart): self
    {
        $allowed = $this->kind === 'trend' ? ['line', 'area', 'bar'] : ['bar', 'donut'];
        if ($this->isScalar() || !in_array($chart, $allowed, true)) {
            throw new \InvalidArgumentException('Unsupported chart type for metric kind.');
        }

        $this->chart = $chart;

        return $this;
    }

    /**
     * Number of columns occupied in the 12-column desktop metrics grid.
     */
    public function span(int $columns): self
    {
        if ($columns < 1 || $columns > 12) {
            throw new \InvalidArgumentException('Metric span must be between 1 and 12.');
        }

        $this->span = $columns;

        return $this;
    }

    /**
     * @return array{
     *     key: string,
     *     label: string,
     *     kind: string,
     *     column: ?string,
     *     chart: string,
     *     span: int
     * }
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'kind' => $this->kind,
            'column' => $this->column,
            'chart' => $this->chart,
            'span' => $this->span ?? ($this->isScalar() ? 3 : 6),
        ];
    }

    private function isScalar(): bool
    {
        return in_array($this->kind, ['count', 'sum', 'avg'], true);
    }
}
