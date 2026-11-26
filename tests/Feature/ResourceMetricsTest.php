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
use Pepperfm\Flashboard\Contracts\Extensions\QueryExtensionContract;
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
            $table->decimal('amount', 12, 2)->nullable();
            $table->decimal('private_amount', 12, 2)->nullable();
            $table->decimal('restricted_amount', 12, 2)->nullable();
        });

        MetricRecord::query()->insert([
            ['status' => 'active', 'created_at' => '2026-11-14 08:00:00', 'amount' => '10.25'],
            ['status' => 'active', 'created_at' => '2026-11-15 09:00:00', 'amount' => '20.50'],
            ['status' => 'hidden', 'created_at' => '2026-11-15 11:00:00', 'amount' => '999.00'],
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

    public function test_metric_query_count_and_payload_size_do_not_grow_with_record_count(): void
    {
        $connection = $this->database->getConnection();
        $request = Request::create('/admin/resources/metrics', 'GET', ['metric_period' => '7']);
        $resolver = $this->resolver();
        $connection->enableQueryLog();
        try {
            $small = $resolver->resource(MetricRecordResource::class, $request, null);
            $smallCount = count($connection->getQueryLog());
            $connection->disableQueryLog();
            MetricRecord::query()->insert(array_fill(0, 100, [
                'status' => 'active',
                'created_at' => '2026-11-15 09:00:00',
                'amount' => '1.00',
            ]));
            MetricRecord::query()->insert(array_fill(0, 100, [
                'status' => 'hidden',
                'created_at' => '2026-11-15 09:00:00',
                'amount' => '999.00',
            ]));
            $connection->flushQueryLog();
            $connection->enableQueryLog();
            $large = $resolver->resource(MetricRecordResource::class, $request, null);
            $largeCount = count($connection->getQueryLog());
        } finally {
            $connection->disableQueryLog();
            $connection->flushQueryLog();
        }

        self::assertSame(2, $small['items'][0]['value']);
        self::assertSame(102, $large['items'][0]['value']);
        self::assertCount(7, $large['items'][1]['points']);
        self::assertSame([['label' => 'active', 'value' => 102]], $large['items'][2]['points']);
        self::assertSame($smallCount, $largeCount);
        self::assertLessThanOrEqual(7, $largeCount);
        self::assertLessThanOrEqual(750, strlen(json_encode($large, JSON_THROW_ON_ERROR)));
    }

    public function test_breakdown_labels_normalize_backed_enums_and_null(): void
    {
        $metric = Metric::breakdown('status')->breakdownLabels(['active' => 'Active', 0 => 'Zero']);

        self::assertSame('Active', $metric->resolveBreakdownLabel(MetricTestStatus::Active));
        self::assertSame('Zero', $metric->resolveBreakdownLabel(0));
        self::assertSame('—', $metric->resolveBreakdownLabel(null));
        self::assertSame('other', $metric->resolveBreakdownLabel('other'));
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

    public function test_query_extensions_also_scope_metrics(): void
    {
        $result = $this->resolver()->resource(
            ExtendedMetricResource::class,
            Request::create('/admin/resources/metrics'),
            null,
        );

        self::assertSame(2, $result['items'][0]['value']);
    }

    public function test_metric_grid_spans_have_safe_defaults_and_allow_explicit_widths(): void
    {
        self::assertSame(3, Metric::count('default_count')->toArray()['span']);
        self::assertSame(6, Metric::trend('default_trend')->toArray()['span']);
        self::assertSame(6, Metric::breakdown('default_breakdown')->toArray()['span']);
        self::assertSame(8, Metric::trend('wide')->span(8)->toArray()['span']);
        self::assertSame(12, Metric::count('full')->span(12)->toArray()['span']);

        foreach ([0, -1, 13] as $invalid) {
            try {
                Metric::trend('invalid_span')->span($invalid);
                self::fail("Span {$invalid} must be rejected.");
            } catch (\InvalidArgumentException) {
                // Expected: avoid invalid CSS grid spans.
            }
        }
    }

    public function test_metric_span_is_present_in_resource_payload(): void
    {
        $result = $this->resolver()->resource(
            MetricRecordResource::class,
            Request::create('/admin/resources/metrics'),
            null,
        );

        self::assertSame([3, 8, 6], array_column($result['items'], 'span'));
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

    public function test_numeric_metrics_use_extensions_and_respect_field_and_metric_abilities(): void
    {
        $gate = $this->createStub(\Illuminate\Contracts\Auth\Access\Gate::class);
        $gate->method('getPolicyFor')->willReturn(new \stdClass());
        $gate->method('forUser')->willReturnSelf();
        $gate->method('allows')->willReturnCallback(
            static fn (string $ability): bool => !in_array($ability, ['view-restricted', 'view-sensitive-metric'], true),
        );
        $this->app->instance(\Illuminate\Contracts\Auth\Access\Gate::class, $gate);

        $result = $this->resolver()->resource(NumericMetricResource::class, Request::create('/admin'), null);

        self::assertSame(['metric_records.revenue', 'metric_records.average'], array_column($result['items'], 'key'));
        self::assertSame(['30.75', '15.375'], array_column($result['items'], 'value'));
        self::assertSame([3, 3], array_column($result['items'], 'span'));
    }

    public function test_numeric_metric_definition_rejects_chart_and_untrusted_column(): void
    {
        foreach ([
            fn () => Metric::sum('revenue')->chart('bar'),
            fn () => Metric::avg('average')->column('SUM(amount)'),
        ] as $invalid) {
            try {
                $invalid();
                self::fail('Invalid numeric metric accepted.');
            } catch (\InvalidArgumentException $exception) {
                self::assertNotSame('', $exception->getMessage());
            }
        }
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
    protected $hidden = ['private_amount'];
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
            Metric::trend('registrations')->dateColumn('created_at')->label('Registrations')->span(8),
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

final class MetricStatusExtension implements QueryExtensionContract
{
    public function extend(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }
}

final class ExtendedMetricResource extends MetricRecordResource
{
    public static function query(): Builder
    {
        return MetricRecord::query();
    }

    public static function queryExtensions(): array
    {
        return [new MetricStatusExtension()];
    }
}

enum MetricTestStatus: string
{
    case Active = 'active';
}

final class NumericMetricResource extends MetricRecordResource
{
    public static function policy(): string
    {
        return \stdClass::class;
    }

    public static function query(): Builder
    {
        return MetricRecord::query();
    }

    public static function queryExtensions(): array
    {
        return [new MetricStatusExtension()];
    }

    public static function fieldAbilityMap(): array
    {
        return ['restricted_amount' => 'view-restricted'];
    }

    public static function metricAbilityMap(): array
    {
        return ['sensitive' => 'view-sensitive-metric'];
    }

    public static function metrics(): array
    {
        return [
            Metric::sum('revenue')->column('amount'),
            Metric::avg('average')->column('amount'),
            Metric::sum('private')->column('private_amount'),
            Metric::sum('restricted')->column('restricted_amount'),
            Metric::avg('sensitive')->column('amount'),
        ];
    }
}
