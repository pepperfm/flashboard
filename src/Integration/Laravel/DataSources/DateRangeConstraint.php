<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Integration\Laravel\DataSources;

use Illuminate\Support\Arr;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class DateRangeConstraint
{
    /**
     * @return array{start: string, end: string}
     */
    public function normalize(mixed $value): array
    {
        if (!is_array($value) || count($value) !== 2) {
            throw new HttpException(422, 'Date range requires start and end dates.');
        }

        $start = $this->date(Arr::get($value, 'start'));
        $end = $this->date(Arr::get($value, 'end'));

        if ($start > $end || $end->format('Y-m-d') === '9999-12-31') {
            throw new HttpException(422, 'Invalid date range boundaries.');
        }

        return ['start' => $start->format('Y-m-d'), 'end' => $end->format('Y-m-d')];
    }

    /**
     * @param array{start: string, end: string} $range
     */
    public function apply(
        \Illuminate\Database\Eloquent\Builder $query,
        string $column,
        array $range,
    ): void {
        $model = $query->getModel();
        $table = $model->getTable();
        $name = str_starts_with($column, $table.'.') ? substr($column, strlen($table) + 1) : $column;

        if (!preg_match('/^[a-zA-Z_]\w*$/D', $name)) {
            throw new \InvalidArgumentException('Date range requires a resource model column.');
        }

        $type = strtolower($model->getConnection()->getSchemaBuilder()->getColumnType($table, $name));
        $format = match ($type) {
            'date' => 'Y-m-d',
            'datetime', 'timestamp', 'timestamp without time zone' => 'Y-m-d H:i:s',
            'timestamptz', 'timestamp with time zone' => 'Y-m-d H:i:sP',
            default => throw new \InvalidArgumentException('Date range requires a date or timestamp column.'),
        };

        $query->where($model->qualifyColumn($name), '>=', $this->date($range['start'])->format($format))
            ->where($model->qualifyColumn($name), '<', $this->date($range['end'])->modify('+1 day')->format($format));
    }

    private function date(mixed $value): \DateTimeImmutable
    {
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value) || str_starts_with($value, '0000-')) {
            throw new HttpException(422, 'Date range dates must use YYYY-MM-DD.');
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value, new \DateTimeZone('UTC'));
        if ($date === false || $date->format('Y-m-d') !== $value) {
            throw new HttpException(422, 'Invalid calendar date.');
        }

        return $date;
    }
}
