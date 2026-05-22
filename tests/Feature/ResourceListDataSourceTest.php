<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Tests\Feature;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Factory;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Database\Capsule\Manager as Capsule;
use Pepperfm\Flashboard\Core\Authorization\Visibility\ScreenAccessResolver;
use Pepperfm\Flashboard\Core\Extensions\ExtensionRegistry;
use Pepperfm\Flashboard\Core\Hooks\RuntimeHookDispatcher;
use Pepperfm\Flashboard\Core\Resources\ResourceSurfaceResolver;
use Pepperfm\Flashboard\Core\Runtime\Assemblers\TablePayloadAssembler;
use Pepperfm\Flashboard\Integration\Laravel\Auth\PanelAuthenticator;
use Pepperfm\Flashboard\Integration\Laravel\Auth\PolicyBridge;
use Pepperfm\Flashboard\Integration\Laravel\DataSources\ResourceListDataSource;
use Pepperfm\Flashboard\Tests\Fixtures\Models\LazyFilterOptionRecord;
use Pepperfm\Flashboard\Tests\Fixtures\Resources\LazyFilterOptionsResource;
use Pepperfm\Flashboard\Tests\Fixtures\Resources\VisibilityRestrictedListResource;
use Pepperfm\Flashboard\Tests\TestCase;

final class ResourceListDataSourceTest extends TestCase
{
    private Capsule $database;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance('url', new class()
        {
            public function route(string $name, array $parameters = [], bool $absolute = true): string
            {
                $suffix = $parameters === [] ? '' : '/' . implode('/', array_map('strval', $parameters));

                return '/' . ltrim($name . $suffix, '/');
            }
        });

        $this->database = new Capsule();
        $this->database->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $this->database->setAsGlobal();
        $this->database->bootEloquent();
        $this->database->schema()->create('lazy_filter_option_records', static function (\Illuminate\Database\Schema\Blueprint $table): void {
            $table->increments('id');
            $table->string('status');
            $table->string('status_label');
            $table->date('published_on')->nullable();
            $table->dateTime('reviewed_at')->nullable();
        });

        $records = [
            [
                'status' => 'draft',
                'published_on' => '2026-05-21',
                'reviewed_at' => '2026-05-20 09:00:00',
            ],
            [
                'status' => 'published',
                'published_on' => '2026-05-22',
                'reviewed_at' => '2026-05-22 15:30:00',
            ],
        ];

        foreach ($records as $record) {
            LazyFilterOptionRecord::query()->create([
                'status' => $record['status'],
                'status_label' => str($record['status'])->headline()->value(),
                'published_on' => $record['published_on'],
                'reviewed_at' => $record['reviewed_at'],
            ]);
        }
    }

    public function test_resource_lists_apply_global_search_to_searchable_columns(): void
    {
        $payload = $this->dataSource()->resolve(
            LazyFilterOptionsResource::class,
            \Illuminate\Http\Request::create('/', 'GET', [
                'search' => 'publish',
            ]),
        );

        self::assertSame('publish', $payload['search']);
        self::assertCount(1, $payload['rows']);
        self::assertSame('published', $payload['rows'][0]['attributes']['status']);
    }

    public function test_resource_lists_do_not_search_non_searchable_columns(): void
    {
        $payload = $this->dataSource()->resolve(
            LazyFilterOptionsResource::class,
            \Illuminate\Http\Request::create('/', 'GET', [
                'search' => '2026-05-21',
            ]),
        );

        self::assertSame('2026-05-21', $payload['search']);
        self::assertCount(0, $payload['rows']);
    }

    public function test_resource_lists_treat_global_search_wildcards_as_literals(): void
    {
        LazyFilterOptionRecord::query()->create([
            'status' => '50%_discount',
            'status_label' => 'Discount status',
        ]);

        $percentPayload = $this->dataSource()->resolve(
            LazyFilterOptionsResource::class,
            \Illuminate\Http\Request::create('/', 'GET', [
                'search' => '%',
            ]),
        );

        self::assertSame('%', $percentPayload['search']);
        self::assertCount(1, $percentPayload['rows']);
        self::assertSame('50%_discount', $percentPayload['rows'][0]['attributes']['status']);

        $underscorePayload = $this->dataSource()->resolve(
            LazyFilterOptionsResource::class,
            \Illuminate\Http\Request::create('/', 'GET', [
                'search' => '_',
            ]),
        );

        self::assertSame('_', $underscorePayload['search']);
        self::assertCount(1, $underscorePayload['rows']);
        self::assertSame('50%_discount', $underscorePayload['rows'][0]['attributes']['status']);
    }

    public function test_resource_lists_do_not_search_hidden_searchable_columns(): void
    {
        $this->bindGateDenying('view-hidden');
        LazyFilterOptionRecord::query()
            ->where('status', 'draft')
            ->update(['status_label' => 'secret-only']);

        $payload = $this->dataSource()->resolve(
            VisibilityRestrictedListResource::class,
            \Illuminate\Http\Request::create('/', 'GET', [
                'search' => 'secret-only',
            ]),
        );

        self::assertSame(['id', 'status'], array_column($payload['columns'], 'key'));
        self::assertSame('secret-only', $payload['search']);
        self::assertCount(0, $payload['rows']);
    }

    public function test_resource_lists_do_not_sort_hidden_sortable_columns(): void
    {
        $this->bindGateDenying('view-hidden');
        LazyFilterOptionRecord::query()
            ->where('status', 'draft')
            ->update(['status_label' => 'zzz-hidden']);
        LazyFilterOptionRecord::query()
            ->where('status', 'published')
            ->update(['status_label' => 'aaa-hidden']);

        $payload = $this->dataSource()->resolve(
            VisibilityRestrictedListResource::class,
            \Illuminate\Http\Request::create('/', 'GET', [
                'sort' => 'status_label',
                'direction' => 'asc',
            ]),
        );

        self::assertSame(['id', 'status'], array_column($payload['columns'], 'key'));
        self::assertSame('status_label', $payload['sort']);
        self::assertSame('asc', $payload['direction']);
        self::assertSame(
            [1, 2],
            array_map(static fn (array $row): int => (int) $row['id'], $payload['rows']),
        );
    }

    public function test_resource_lists_apply_sort_only_to_sortable_columns(): void
    {
        $payload = $this->dataSource()->resolve(
            LazyFilterOptionsResource::class,
            \Illuminate\Http\Request::create('/', 'GET', [
                'sort' => 'status',
                'direction' => 'desc',
            ]),
        );

        self::assertSame('status', $payload['sort']);
        self::assertSame('desc', $payload['direction']);
        self::assertSame(
            ['published', 'draft'],
            array_map(static fn (array $row): string => $row['attributes']['status'], $payload['rows']),
        );
    }

    public function test_resource_lists_ignore_undeclared_sort_columns(): void
    {
        $payload = $this->dataSource()->resolve(
            LazyFilterOptionsResource::class,
            \Illuminate\Http\Request::create('/', 'GET', [
                'sort' => 'published_on',
                'direction' => 'desc',
            ]),
        );

        self::assertSame('published_on', $payload['sort']);
        self::assertSame('desc', $payload['direction']);
        self::assertSame(
            ['draft', 'published'],
            array_map(static fn (array $row): string => $row['attributes']['status'], $payload['rows']),
        );
    }

    public function test_resource_lists_fall_back_to_ascending_sort_direction(): void
    {
        $payload = $this->dataSource()->resolve(
            LazyFilterOptionsResource::class,
            \Illuminate\Http\Request::create('/', 'GET', [
                'sort' => 'id',
                'direction' => 'sideways',
            ]),
        );

        self::assertSame('id', $payload['sort']);
        self::assertSame('asc', $payload['direction']);
        self::assertSame(
            [1, 2],
            array_map(static fn (array $row): int => (int) $row['id'], $payload['rows']),
        );
    }

    public function test_resource_lists_apply_only_declared_filter_keys(): void
    {
        $payload = $this->dataSource()->resolve(
            LazyFilterOptionsResource::class,
            \Illuminate\Http\Request::create('/', 'GET', [
                'filters' => [
                    'status' => 'draft',
                    'unknown' => 'value',
                ],
            ]),
        );

        self::assertSame(['status' => 'draft'], $payload['active_filters']);
        self::assertCount(1, $payload['rows']);
        self::assertSame('draft', $payload['rows'][0]['attributes']['status']);
    }

    public function test_resource_lists_apply_multiple_filters_with_where_in(): void
    {
        $payload = $this->dataSource()->resolve(
            LazyFilterOptionsResource::class,
            \Illuminate\Http\Request::create('/', 'GET', [
                'filters' => [
                    'statuses' => ['draft', 'published'],
                ],
            ]),
        );

        self::assertSame(['statuses' => ['draft', 'published']], $payload['active_filters']);
        self::assertCount(2, $payload['rows']);
        self::assertSame(
            ['draft', 'published'],
            array_map(static fn (array $row): string => $row['attributes']['status'], $payload['rows']),
        );
    }

    public function test_resource_lists_accept_scalar_input_for_multiple_filters(): void
    {
        $payload = $this->dataSource()->resolve(
            LazyFilterOptionsResource::class,
            \Illuminate\Http\Request::create('/', 'GET', [
                'filters' => [
                    'statuses' => 'draft',
                ],
            ]),
        );

        self::assertSame(['statuses' => ['draft']], $payload['active_filters']);
        self::assertCount(1, $payload['rows']);
        self::assertSame('draft', $payload['rows'][0]['attributes']['status']);
    }

    public function test_resource_lists_ignore_empty_multiple_filter_values(): void
    {
        $payload = $this->dataSource()->resolve(
            LazyFilterOptionsResource::class,
            \Illuminate\Http\Request::create('/', 'GET', [
                'filters' => [
                    'statuses' => ['', null, []],
                ],
            ]),
        );

        self::assertSame([], $payload['active_filters']);
        self::assertCount(2, $payload['rows']);
    }

    public function test_resource_lists_apply_exact_input_filters(): void
    {
        $payload = $this->dataSource()->resolve(
            LazyFilterOptionsResource::class,
            \Illuminate\Http\Request::create('/', 'GET', [
                'filters' => [
                    'status_label' => 'Draft',
                ],
            ]),
        );

        self::assertSame(['status_label' => 'Draft'], $payload['active_filters']);
        self::assertCount(1, $payload['rows']);
        self::assertSame('draft', $payload['rows'][0]['attributes']['status']);
    }

    public function test_resource_lists_apply_contains_input_filters_to_query_column(): void
    {
        $payload = $this->dataSource()->resolve(
            LazyFilterOptionsResource::class,
            \Illuminate\Http\Request::create('/', 'GET', [
                'filters' => [
                    'status_text' => 'publish',
                ],
            ]),
        );

        self::assertSame(['status_text' => 'publish'], $payload['active_filters']);
        self::assertCount(1, $payload['rows']);
        self::assertSame('published', $payload['rows'][0]['attributes']['status']);
    }

    public function test_resource_lists_treat_contains_input_filter_wildcards_as_literals(): void
    {
        LazyFilterOptionRecord::query()->create([
            'status' => '50%_discount',
            'status_label' => '50 percent discount',
        ]);

        $percentPayload = $this->dataSource()->resolve(
            LazyFilterOptionsResource::class,
            \Illuminate\Http\Request::create('/', 'GET', [
                'filters' => [
                    'status_text' => '%',
                ],
            ]),
        );

        self::assertSame(['status_text' => '%'], $percentPayload['active_filters']);
        self::assertCount(1, $percentPayload['rows']);
        self::assertSame('50%_discount', $percentPayload['rows'][0]['attributes']['status']);

        $underscorePayload = $this->dataSource()->resolve(
            LazyFilterOptionsResource::class,
            \Illuminate\Http\Request::create('/', 'GET', [
                'filters' => [
                    'status_text' => '_',
                ],
            ]),
        );

        self::assertSame(['status_text' => '_'], $underscorePayload['active_filters']);
        self::assertCount(1, $underscorePayload['rows']);
        self::assertSame('50%_discount', $underscorePayload['rows'][0]['attributes']['status']);
    }

    public function test_resource_lists_ignore_empty_input_filter_values(): void
    {
        $payload = $this->dataSource()->resolve(
            LazyFilterOptionsResource::class,
            \Illuminate\Http\Request::create('/', 'GET', [
                'filters' => [
                    'status_text' => '',
                ],
            ]),
        );

        self::assertSame([], $payload['active_filters']);
        self::assertCount(2, $payload['rows']);
    }

    public function test_resource_lists_apply_date_filters(): void
    {
        $payload = $this->dataSource()->resolve(
            LazyFilterOptionsResource::class,
            \Illuminate\Http\Request::create('/', 'GET', [
                'filters' => [
                    'published_on' => '2026-05-22',
                ],
            ]),
        );

        self::assertSame(['published_on' => '2026-05-22'], $payload['active_filters']);
        self::assertCount(1, $payload['rows']);
        self::assertSame('published', $payload['rows'][0]['attributes']['status']);
    }

    public function test_resource_lists_apply_date_filters_to_query_column(): void
    {
        $payload = $this->dataSource()->resolve(
            LazyFilterOptionsResource::class,
            \Illuminate\Http\Request::create('/', 'GET', [
                'filters' => [
                    'reviewed_date' => '2026-05-22',
                ],
            ]),
        );

        self::assertSame(['reviewed_date' => '2026-05-22'], $payload['active_filters']);
        self::assertCount(1, $payload['rows']);
        self::assertSame('published', $payload['rows'][0]['attributes']['status']);
    }

    public function test_resource_lists_ignore_invalid_date_filter_values(): void
    {
        $payload = $this->dataSource()->resolve(
            LazyFilterOptionsResource::class,
            \Illuminate\Http\Request::create('/', 'GET', [
                'filters' => [
                    'published_on' => '2026-02-30',
                    'reviewed_date' => 'tomorrow',
                ],
            ]),
        );

        self::assertSame([], $payload['active_filters']);
        self::assertCount(2, $payload['rows']);
    }

    public function test_resource_lists_ignore_empty_date_filter_values(): void
    {
        $payload = $this->dataSource()->resolve(
            LazyFilterOptionsResource::class,
            \Illuminate\Http\Request::create('/', 'GET', [
                'filters' => [
                    'published_on' => '',
                ],
            ]),
        );

        self::assertSame([], $payload['active_filters']);
        self::assertCount(2, $payload['rows']);
    }

    public function test_resource_lists_ignore_array_date_filter_values(): void
    {
        $payload = $this->dataSource()->resolve(
            LazyFilterOptionsResource::class,
            \Illuminate\Http\Request::create('/', 'GET', [
                'filters' => [
                    'published_on' => ['2026-05-22'],
                ],
            ]),
        );

        self::assertSame([], $payload['active_filters']);
        self::assertCount(2, $payload['rows']);
    }

    public function test_date_columns_preserve_raw_values_unless_format_is_configured(): void
    {
        $payload = $this->dataSource()->resolve(
            LazyFilterOptionsResource::class,
            \Illuminate\Http\Request::create('/'),
        );

        self::assertSame('2026-05-21', $payload['rows'][0]['attributes']['published_on']);
        self::assertSame('20.05.2026', $payload['rows'][0]['attributes']['reviewed_at']);
    }

    public function test_resource_lists_limit_multiple_filter_values(): void
    {
        $values = array_merge(
            ['draft'],
            array_map(static fn (int $index): string => 'missing-' . $index, range(1, 250)),
            ['published'],
        );

        $payload = $this->dataSource()->resolve(
            LazyFilterOptionsResource::class,
            \Illuminate\Http\Request::create('/', 'GET', [
                'filters' => [
                    'statuses' => $values,
                ],
            ]),
        );

        self::assertCount(200, $payload['active_filters']['statuses']);
        self::assertSame('missing-199', $payload['active_filters']['statuses'][199]);
        self::assertCount(1, $payload['rows']);
        self::assertSame('draft', $payload['rows'][0]['attributes']['status']);
    }

    public function test_lazy_filters_receive_options_url_in_resource_list_payload(): void
    {
        $payload = $this->dataSource()->resolve(
            LazyFilterOptionsResource::class,
            \Illuminate\Http\Request::create('/'),
        );

        self::assertSame(
            '/flashboard.resources.lazy_filter_options.filters.options/status',
            $payload['filters'][0]['options_url'],
        );
        self::assertArrayNotHasKey('options', $payload['filters'][0]);
    }

    private function dataSource(): ResourceListDataSource
    {
        $screenAccessResolver = new ScreenAccessResolver(new PolicyBridge());

        return new ResourceListDataSource(
            new TablePayloadAssembler(),
            $screenAccessResolver,
            $this->authenticator(),
            new ExtensionRegistry(),
            new RuntimeHookDispatcher(),
            new ResourceSurfaceResolver($screenAccessResolver),
        );
    }

    private function authenticator(): PanelAuthenticator
    {
        return new PanelAuthenticator(new class() implements Factory
        {
            public function guard($name = null): Guard|StatefulGuard
            {
                return new class() implements StatefulGuard
                {
                    public function check(): bool
                    {
                        return false;
                    }

                    public function guest(): bool
                    {
                        return true;
                    }

                    public function user(): ?\Illuminate\Contracts\Auth\Authenticatable
                    {
                        return null;
                    }

                    public function id(): int|string|null
                    {
                        return null;
                    }

                    public function validate(array $credentials = []): bool
                    {
                        return false;
                    }

                    public function hasUser(): bool
                    {
                        return false;
                    }

                    public function setUser(\Illuminate\Contracts\Auth\Authenticatable $user): static
                    {
                        return $this;
                    }

                    public function attempt(array $credentials = [], $remember = false): bool
                    {
                        return false;
                    }

                    public function once(array $credentials = []): bool
                    {
                        return false;
                    }

                    public function login(\Illuminate\Contracts\Auth\Authenticatable $user, $remember = false): void
                    {
                    }

                    public function loginUsingId($id, $remember = false): \Illuminate\Contracts\Auth\Authenticatable|false
                    {
                        return false;
                    }

                    public function onceUsingId($id): \Illuminate\Contracts\Auth\Authenticatable|false
                    {
                        return false;
                    }

                    public function viaRemember(): bool
                    {
                        return false;
                    }

                    public function logout(): void
                    {
                    }
                };
            }

            public function shouldUse($name): void
            {
            }
        });
    }

    private function bindGateDenying(string $deniedAbility): void
    {
        $this->app->instance(Gate::class, new class($deniedAbility) implements Gate
        {
            public function __construct(private readonly string $deniedAbility)
            {
            }

            public function has($ability): bool
            {
                return true;
            }

            public function define($ability, $callback): static
            {
                return $this;
            }

            public function resource($name, $class, ?array $abilities = null): static
            {
                return $this;
            }

            public function policy($class, $policy): static
            {
                return $this;
            }

            public function before(callable $callback): static
            {
                return $this;
            }

            public function after(callable $callback): static
            {
                return $this;
            }

            public function allows($ability, $arguments = []): bool
            {
                return $ability !== $this->deniedAbility;
            }

            public function denies($ability, $arguments = []): bool
            {
                return !$this->allows($ability, $arguments);
            }

            public function check($abilities, $arguments = []): bool
            {
                foreach ((array) $abilities as $ability) {
                    if (!$this->allows($ability, $arguments)) {
                        return false;
                    }
                }

                return true;
            }

            public function any($abilities, $arguments = []): bool
            {
                foreach ((array) $abilities as $ability) {
                    if ($this->allows($ability, $arguments)) {
                        return true;
                    }
                }

                return false;
            }

            public function authorize($ability, $arguments = []): \Illuminate\Auth\Access\Response
            {
                return $this->inspect($ability, $arguments);
            }

            public function inspect($ability, $arguments = []): \Illuminate\Auth\Access\Response
            {
                return new \Illuminate\Auth\Access\Response($this->allows($ability, $arguments));
            }

            public function raw($ability, $arguments = []): bool
            {
                return $this->allows($ability, $arguments);
            }

            public function getPolicyFor($class): mixed
            {
                return null;
            }

            public function forUser($user): static
            {
                return $this;
            }

            public function abilities(): array
            {
                return [];
            }
        });
    }
}
