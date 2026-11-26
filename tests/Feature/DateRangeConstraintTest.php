<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Tests\Feature;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Pepperfm\Flashboard\Integration\Laravel\DataSources\DateRangeConstraint;
use Pepperfm\Flashboard\Tests\Fixtures\Models\DateRangeProbe;
use Pepperfm\Flashboard\Tests\PostgresTestConnection;
use Pepperfm\Flashboard\Tests\TestCase;

final class DateRangeConstraintTest extends TestCase
{
    public function test_sqlite_date_and_timestamp_boundaries_preserve_scope(): void
    {
        $this->assertBoundaries(['driver' => 'sqlite', 'database' => ':memory:']);
    }

    public function test_postgresql_date_and_timestamp_boundaries_preserve_scope(): void
    {
        $this->assertBoundaries(PostgresTestConnection::configuration());
    }

    /** @param array<string, mixed> $connection */
    private function assertBoundaries(array $connection): void
    {
        $database = new Capsule();
        $database->addConnection($connection);
        $database->setAsGlobal();
        $database->bootEloquent();
        $schema = $database->schema();
        $schema->create('flashboard_range_probe', static function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('tenant');
            $table->date('day');
            $table->dateTime('occurred_at', 6);
            $table->timestampTz('zoned_at', 6)->nullable();
        });

        try {
            foreach ([
                [1, '2026-03-28', '2026-03-28 23:59:59.999999'],
                [1, '2026-03-29', '2026-03-29 00:00:00'],
                [1, '2026-03-29', '2026-03-29 23:59:59.999999'],
                [1, '2026-03-30', '2026-03-30 00:00:00'],
                [2, '2026-03-29', '2026-03-29 12:00:00'],
            ] as [$tenant, $day, $time]) {
                $database->table('flashboard_range_probe')->insert([
                    'tenant' => $tenant,
                    'day' => $day,
                    'occurred_at' => $time,
                    'zoned_at' => $time.'+00:00',
                ]);
            }

            $constraint = new DateRangeConstraint();
            $range = $constraint->normalize(['start' => '2026-03-29', 'end' => '2026-03-29']);

            foreach (['day', 'occurred_at'] as $column) {
                $query = DateRangeProbe::query()->where('tenant', 1);
                $constraint->apply($query, $column, $range);
                self::assertSame([2, 3], $query->orderBy('id')->pluck('id')->all());
            }

            if ($connection['driver'] === 'pgsql') {
                $database->connection()->statement("SET TIME ZONE 'Europe/Amsterdam'");
                $query = DateRangeProbe::query()->where('tenant', 1);
                $constraint->apply($query, 'zoned_at', $range);
                self::assertSame([2, 3], $query->orderBy('id')->pluck('id')->all());
            }

            $this->expectException(\InvalidArgumentException::class);
            $constraint->apply(DateRangeProbe::query(), 'tenant', $range);
        } finally {
            $schema->dropIfExists('flashboard_range_probe');
        }
    }
}
