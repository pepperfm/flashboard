<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Tests\Feature;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Pepperfm\Flashboard\Core\Metrics\NumericMetricAggregate;
use Pepperfm\Flashboard\Tests\Fixtures\Models\NumericMetricProbe;
use Pepperfm\Flashboard\Tests\PostgresTestConnection;
use Pepperfm\Flashboard\Tests\TestCase;

final class NumericMetricAggregateTest extends TestCase
{
    public function test_sqlite_scoped_numeric_aggregates(): void
    {
        $this->assertAggregates(['driver' => 'sqlite', 'database' => ':memory:']);
    }

    public function test_postgresql_scoped_numeric_aggregates(): void
    {
        $this->assertAggregates(PostgresTestConnection::configuration());
    }

    /** @param array<string, mixed> $connection */
    private function assertAggregates(array $connection): void
    {
        $database = new Capsule();
        $database->addConnection($connection);
        $database->setAsGlobal();
        $database->bootEloquent();
        $schema = $database->schema();
        $schema->create('flashboard_numeric_probe', static function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('tenant');
            $table->decimal('amount', 28, 4)->nullable();
            $table->string('label')->default('test');
        });
        $schema->create('flashboard_numeric_join', static function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('probe_id');
        });

        try {
            $database->table('flashboard_numeric_probe')->insert([
                ['id' => 1, 'tenant' => 1, 'amount' => '10.2500'],
                ['id' => 2, 'tenant' => 1, 'amount' => '10.2500'],
                ['id' => 3, 'tenant' => 1, 'amount' => null],
                ['id' => 4, 'tenant' => 2, 'amount' => '999.0000'],
                ['id' => 5, 'tenant' => 3, 'amount' => '9007199254740993.1250'],
            ]);
            $database->table('flashboard_numeric_join')->insert([
                ['probe_id' => 1], ['probe_id' => 1], ['probe_id' => 2], ['probe_id' => 3], ['probe_id' => 4],
            ]);
            $query = NumericMetricProbe::query();
            $query->join('flashboard_numeric_join', 'flashboard_numeric_join.probe_id', '=', 'flashboard_numeric_probe.id')
                ->where('tenant', 1)->select('flashboard_numeric_join.id')->orderBy('flashboard_numeric_join.id');

            $aggregate = new NumericMetricAggregate();
            $sum = $aggregate->resolve($query, 'sum', 'amount')['value'];
            $avg = $aggregate->resolve($query, 'avg', 'amount')['value'];
            self::assertIsString($sum);
            self::assertIsString($avg);
            self::assertSame(20.5, (float) $sum);
            self::assertSame(10.25, (float) $avg);
            self::assertSame(['flashboard_numeric_join.id'], $query->getQuery()->columns);

            foreach ([NumericMetricProbe::query()->where('tenant', 99), NumericMetricProbe::query()->whereKey(3)] as $empty) {
                self::assertSame(['value' => '0'], $aggregate->resolve($empty, 'sum', 'amount'));
                self::assertSame(['value' => null], $aggregate->resolve($empty, 'avg', 'amount'));
            }

            if ($connection['driver'] === 'pgsql') {
                self::assertSame(['value' => '9007199254740993.1250'], $aggregate->resolve(
                    NumericMetricProbe::query()->where('tenant', 3), 'sum', 'amount',
                ));
            }

            $limited = NumericMetricProbe::query();
            $limited->limit(1);
            $grouped = NumericMetricProbe::query();
            $grouped->groupBy('tenant');
            foreach ([
                fn () => $aggregate->resolve($limited, 'sum', 'amount'),
                fn () => $aggregate->resolve($grouped, 'avg', 'amount'),
                fn () => $aggregate->resolve(NumericMetricProbe::query(), 'sum', 'label'),
            ] as $invalid) {
                try {
                    $invalid();
                    self::fail('Unsupported numeric metric accepted.');
                } catch (\InvalidArgumentException $exception) {
                    self::assertNotSame('', $exception->getMessage());
                }
            }
        } finally {
            $schema->dropIfExists('flashboard_numeric_join');
            $schema->dropIfExists('flashboard_numeric_probe');
        }
    }
}
