<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Tests\Feature;

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
        });

        foreach (['draft', 'published'] as $status) {
            LazyFilterOptionRecord::query()->create(['status' => $status]);
        }
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
}
