<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Pepperfm\Flashboard\Contracts\Resources\Resource;
use Pepperfm\Flashboard\Core\Authorization\Visibility\ScreenAccessResolver;
use Pepperfm\Flashboard\Core\Extensions\ExtensionRegistry;
use Pepperfm\Flashboard\Core\Metrics\Metric;
use Pepperfm\Flashboard\Core\Metrics\MetricResolver;
use Pepperfm\Flashboard\Core\Registry\ResourceRegistry;
use Pepperfm\Flashboard\Integration\Laravel\Auth\PolicyBridge;
use Pepperfm\Flashboard\Tests\TestCase;

final class ResourceMetricsTest extends TestCase
{
    private Capsule $database;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow('2026-11-15 12:00:00 UTC');

        $this->database = new Capsule();
        $this->database->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $this->database->setAsGlobal();
        $this->database->bootEloquent();
        $this->database->schema()->create('metric_records', static function (Blueprint $table): void {
            $table->increments('id');
            $table->string('status');
            $table->dateTime('created_at');
        });

        MetricRecord::query()->insert([
            ['status' => 'active', 'created_at' => '2026-11-14 08:00:00'],
            ['status' => 'active', 'created_at' => '2026-11-15 09:00:00'],
            ['status' => 'hidden', 'created_at' => '2026-11-15 11:00:00'],
        ]);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_count_trend_and_breakdown_use_resource_query_and_zero_fill_missing_days(): void
    {
        $result = $this->resolver()->resource(
            MetricRecordResource::class,
            Request::create('/admin/resources/metrics', 'GET', ['metric_period' => '7']),
            null,
        );

        self::assertSame(7, $result['period_days']);
        self::assertSame(
            ['metric_records.total', 'metric_records.registrations', 'metric_records.statuses'],
            array_column($result['items'], 'key'),
        );
        self::assertSame(2, $result['items'][0]['value']);
        self::assertCount(7, $result['items'][1]['points']);
        self::assertSame(['label' => '2026-11-14', 'value' => 1], $result['items'][1]['points'][5]);
        self::assertSame(['label' => '2026-11-15', 'value' => 1], $result['items'][1]['points'][6]);
        self::assertSame([['label' => 'active', 'value' => 2]], $result['items'][2]['points']);
    }

    public function test_dashboard_skips_unavailable_resources(): void
    {
        $registry = new ResourceRegistry();
        $registry->register(MetricRecordResource::class);
        $registry->register(RestrictedMetricResource::class);

        $result = $this->resolver($registry)->dashboard(Request::create('/admin'), null);

        self::assertCount(3, $result['items']);
        self::assertSame(30, $result['period_days']);
    }

    public function test_untrusted_period_is_bounded_to_supported_values(): void
    {
        $resolver = $this->resolver();

        self::assertSame(30, $resolver->period(Request::create('/admin', 'GET', ['metric_period' => '36500'])));
        self::assertSame(30, $resolver->period(Request::create('/admin', 'GET', ['metric_period' => ['90']])) );
        self::assertSame(90, $resolver->period(Request::create('/admin', 'GET', ['metric_period' => '90'])));
    }

    public function test_unsafe_or_nonexistent_columns_are_rejected(): void
    {
        try {
            Metric::breakdown('unsafe')->column('status); DROP TABLE users; --');
            self::fail('Unsafe column should be rejected.');
        } catch (\InvalidArgumentException) {
            // Expected: the API does not accept arbitrary SQL expressions.
        }

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('does not exist');

        $this->resolver()->resource(
            MissingColumnMetricResource::class,
            Request::create('/admin/resources/missing'),
            null,
        );
    }

    private function resolver(?ResourceRegistry $registry = null): MetricResolver
    {
        $registry ??= new ResourceRegistry();
        if ($registry->all() === []) {
            $registry->register(MetricRecordResource::class);
        }

        return new MetricResolver($registry, new ScreenAccessResolver(new PolicyBridge()), new ExtensionRegistry());
    }
}

final class MetricRecord extends Model
{
    protected $table = 'metric_records';
    public $timestamps = false;
    protected $guarded = [];
}

class MetricRecordResource extends Resource
{
    public static function key(): string
    {
        return 'metric_records';
    }

    public static function model(): string
    {
        return MetricRecord::class;
    }

    public static function query(): Builder
    {
        return MetricRecord::query()->where('status', '!=', 'hidden');
    }

    public static function metrics(): array
    {
        return [
            Metric::count('total')->label('Records'),
            Metric::trend('registrations')->dateColumn('created_at')->label('Registrations'),
            Metric::breakdown('statuses')->column('status')->chart('bar'),
        ];
    }
}

final class RestrictedMetricResource extends MetricRecordResource
{
    public static function canAccess(?\Illuminate\Contracts\Auth\Authenticatable $user = null): bool
    {
        return false;
    }
}

final class MissingColumnMetricResource extends MetricRecordResource
{
    public static function metrics(): array
    {
        return [Metric::breakdown('missing')->column('absent_column')];
    }
}
