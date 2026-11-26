<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Ai\Generation;

use Pepperfm\Flashboard\Core\Metrics\NumericMetricAggregate;

final class MetricCompiler
{
    /**
     * @param list<mixed> $definitions
     * @param array<string, string> $available
     *
     * @return list<string>
     */
    public function compile(\Illuminate\Database\Eloquent\Model $model, array $definitions, array $available): array
    {
        if (count($definitions) > 10) {
            throw new \InvalidArgumentException('At most ten metrics are supported.');
        }

        $lines = [];
        $seen = [];

        foreach ($definitions as $definition) {
            if (!is_array($definition)) {
                throw new \InvalidArgumentException('Each metric must be an object.');
            }

            $key = $definition['key'] ?? null;
            $kind = $definition['kind'] ?? null;

            if (
                !is_string($key)
                || isset($seen[$key])
                || !preg_match('/^[a-z][a-z0-9_-]{0,63}$/D', $key)
                || !in_array($kind, ['count', 'trend', 'breakdown', 'sum', 'avg'], true)
            ) {
                throw new \InvalidArgumentException('Invalid or duplicate metric key or unsupported kind.');
            }

            $seen[$key] = true;
            $line = "            Metric::$kind(".var_export($key, true).')';
            $column = $definition['column'] ?? ($kind === 'trend' ? $model->getCreatedAtColumn() : null);

            if ($kind !== 'count') {
                if (
                    !is_string($column)
                    || !isset($available[$column])
                    || !preg_match('/^[a-zA-Z_]\w*$/D', $column)
                    || preg_match('/password|secret|token|api_key|private_key/i', $column)
                    || in_array($column, $model->getHidden(), true)
                ) {
                    throw new \InvalidArgumentException("Invalid or sensitive metric column for [$key].");
                }
                if ($kind === 'trend' && !ColumnType::isDate($available[$column])) {
                    throw new \InvalidArgumentException("Trend metric [$key] requires a date or timestamp column.");
                }
                if (in_array($kind, ['sum', 'avg'], true) && !NumericMetricAggregate::supportsColumnType($available[$column])) {
                    throw new \InvalidArgumentException("Numeric metric [$key] requires a numeric column.");
                }

                $method = $kind === 'trend' ? 'dateColumn' : 'column';
                $line .= "->$method(" . var_export($column, true) . ')';

                if ($kind === 'breakdown') {
                    $enum = new EnumOptions()->inspect($model, $column);
                    if ($enum !== null) {
                        $line .= '->breakdownLabels(' . $this->options($enum['options']) . ')';
                    }
                }
            } elseif ($column !== null) {
                throw new \InvalidArgumentException('Count metrics do not accept a column.');
            }

            $label = $definition['label'] ?? null;
            if ($label !== null) {
                if (!is_string($label) || strlen($label) > 100) {
                    throw new \InvalidArgumentException('Metric labels must be strings up to 100 bytes.');
                }
                $line .= '->label(' . var_export($label, true) . ')';
            }

            $chart = $definition['chart'] ?? null;
            if ($chart !== null) {
                $allowed = match ($kind) {
                    'trend' => ['line', 'area', 'bar'],
                    'breakdown' => ['bar', 'donut'],
                    default => [],
                };
                if (!in_array($chart, $allowed, true)) {
                    throw new \InvalidArgumentException('Unsupported metric chart.');
                }
                $line .= '->chart(' . var_export($chart, true) . ')';
            }

            $span = $definition['span'] ?? null;
            if ($span !== null) {
                if (!is_int($span) || $span < 1 || $span > 12) {
                    throw new \InvalidArgumentException('Metric span must be an integer from 1 to 12.');
                }
                $line .= "->span($span)";
            }

            $lines[] = $line . ',';
        }

        return $lines;
    }

    /** @param array<int|string, string> $options */
    private function options(array $options): string
    {
        return '[' . implode(', ', array_map(
            static fn(int|string $key, string $label): string => var_export($key, true) . ' => ' . var_export($label, true),
            array_keys($options),
            array_values($options),
        )) . ']';
    }
}
