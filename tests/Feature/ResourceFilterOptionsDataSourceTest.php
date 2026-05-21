<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Tests\Feature;

use Illuminate\Contracts\Auth\Factory;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Database\Capsule\Manager as Capsule;
use Pepperfm\Flashboard\Core\Extensions\ExtensionRegistry;
use Pepperfm\Flashboard\Core\Runtime\Assemblers\TablePayloadAssembler;
use Pepperfm\Flashboard\Integration\Laravel\Auth\PanelAuthenticator;
use Pepperfm\Flashboard\Integration\Laravel\DataSources\ResourceFilterOptionsDataSource;
use Pepperfm\Flashboard\Tests\Fixtures\Models\LazyFilterOptionRecord;
use Pepperfm\Flashboard\Tests\Fixtures\Resources\LazyFilterOptionsResource;
use Pepperfm\Flashboard\Tests\TestCase;

final class ResourceFilterOptionsDataSourceTest extends TestCase
{
    private Capsule $database;

    protected function setUp(): void
    {
        parent::setUp();

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

        foreach (['draft', 'published', 'review', 'archived', 'published'] as $status) {
            LazyFilterOptionRecord::query()->create(['status' => $status]);
        }
    }

    public function test_default_lazy_select_options_are_distinct_searchable_and_paginated(): void
    {
        $payload = $this->dataSource()->resolve(
            LazyFilterOptionsResource::class,
            'status',
            \Illuminate\Http\Request::create('/', 'GET', [
                'page' => 1,
                'per_page' => 2,
            ]),
        );

        self::assertSame([
            [
                'label' => 'archived',
                'value' => 'archived',
            ],
            [
                'label' => 'draft',
                'value' => 'draft',
            ],
        ], $payload['items']);
        self::assertTrue($payload['meta']['has_more']);
        self::assertSame(2, $payload['meta']['next_page']);

        $searchedPayload = $this->dataSource()->resolve(
            LazyFilterOptionsResource::class,
            'status',
            \Illuminate\Http\Request::create('/', 'GET', [
                'search' => 'pub',
            ]),
        );

        self::assertSame([
            [
                'label' => 'published',
                'value' => 'published',
            ],
        ], $searchedPayload['items']);
        self::assertFalse($searchedPayload['meta']['has_more']);
        self::assertNull($searchedPayload['meta']['next_page']);
    }

    public function test_default_lazy_select_options_hydrate_selected_values(): void
    {
        $payload = $this->dataSource()->resolve(
            LazyFilterOptionsResource::class,
            'status',
            \Illuminate\Http\Request::create('/', 'GET', [
                'page' => 1,
                'per_page' => 1,
                'selected' => 'published',
            ]),
        );

        self::assertSame([
            'label' => 'published',
            'value' => 'published',
        ], $payload['items'][0]);
    }

    public function test_default_lazy_select_options_hydrate_multiple_selected_values(): void
    {
        $payload = $this->dataSource()->resolve(
            LazyFilterOptionsResource::class,
            'statuses',
            \Illuminate\Http\Request::create('/', 'GET', [
                'page' => 1,
                'per_page' => 1,
                'selected' => ['published', 'review'],
            ]),
        );

        self::assertSame([
            [
                'label' => 'published',
                'value' => 'published',
            ],
            [
                'label' => 'review',
                'value' => 'review',
            ],
        ], array_slice($payload['items'], 0, 2));
    }

    public function test_lazy_select_options_can_use_separate_label_and_value_columns(): void
    {
        $payload = $this->dataSource()->resolve(
            LazyFilterOptionsResource::class,
            'status_id',
            \Illuminate\Http\Request::create('/', 'GET', [
                'search' => 'arch',
                'page' => 1,
                'per_page' => 2,
            ]),
        );

        self::assertSame([
            [
                'label' => 'archived',
                'value' => 4,
            ],
        ], $payload['items']);
        self::assertFalse($payload['meta']['has_more']);
        self::assertNull($payload['meta']['next_page']);

        $selectedPayload = $this->dataSource()->resolve(
            LazyFilterOptionsResource::class,
            'status_id',
            \Illuminate\Http\Request::create('/', 'GET', [
                'page' => 1,
                'per_page' => 1,
                'selected' => 3,
            ]),
        );

        self::assertSame([
            'label' => 'review',
            'value' => 3,
        ], $selectedPayload['items'][0]);
    }

    public function test_custom_lazy_select_resolver_receives_query_context(): void
    {
        $payload = $this->dataSource()->resolve(
            LazyFilterOptionsResource::class,
            'sku',
            \Illuminate\Http\Request::create('/', 'GET', [
                'search' => 'needle',
                'page' => 3,
                'per_page' => 5,
                'selected' => 42,
            ]),
        );

        self::assertSame([
            [
                'label' => 'SKU needle page 3 selected 42',
                'value' => 99,
            ],
        ], $payload['items']);
        self::assertTrue($payload['meta']['has_more']);
        self::assertSame(4, $payload['meta']['next_page']);
    }

    public function test_custom_lazy_select_resolver_receives_selected_values(): void
    {
        $payload = $this->dataSource()->resolve(
            LazyFilterOptionsResource::class,
            'sku_multi',
            \Illuminate\Http\Request::create('/', 'GET', [
                'selected' => [42, 43],
            ]),
        );

        self::assertSame([
            [
                'label' => 'SKU selected 42|43 first 42',
                'value' => 100,
            ],
        ], $payload['items']);
    }

    public function test_lazy_select_options_limit_selected_values(): void
    {
        $payload = $this->dataSource()->resolve(
            LazyFilterOptionsResource::class,
            'sku_multi',
            \Illuminate\Http\Request::create('/', 'GET', [
                'selected' => range(1, 205),
            ]),
        );

        $label = $payload['items'][0]['label'];

        self::assertStringContainsString('SKU selected 1|2', $label);
        self::assertStringContainsString('|200 first 1', $label);
        self::assertStringNotContainsString('|201', $label);
    }

    public function test_non_lazy_filters_are_not_served_by_the_lazy_options_data_source(): void
    {
        $this->expectException(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);

        $this->dataSource()->resolve(
            LazyFilterOptionsResource::class,
            'eager',
            \Illuminate\Http\Request::create('/'),
        );
    }

    private function dataSource(): ResourceFilterOptionsDataSource
    {
        return new ResourceFilterOptionsDataSource(
            new TablePayloadAssembler(),
            $this->authenticator(),
            new ExtensionRegistry(),
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
