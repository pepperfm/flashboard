<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Tests\Feature;

use Illuminate\Contracts\Auth\Factory;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Database\Capsule\Manager as Capsule;
use Pepperfm\Flashboard\Contracts\Resources\Resource;
use Pepperfm\Flashboard\Core\Authorization\Visibility\ScreenAccessResolver;
use Pepperfm\Flashboard\Core\Extensions\ExtensionRegistry;
use Pepperfm\Flashboard\Core\Registry\ResourceRegistry;
use Pepperfm\Flashboard\Core\Relations\RelationDefinition;
use Pepperfm\Flashboard\Core\Relations\RelationPayloadFactory;
use Pepperfm\Flashboard\Core\Resources\ResourceSurfaceResolver;
use Pepperfm\Flashboard\Integration\Laravel\Auth\PanelAuthenticator;
use Pepperfm\Flashboard\Integration\Laravel\Auth\PolicyBridge;
use Pepperfm\Flashboard\Integration\Laravel\DataSources\ResourceRelationAttachOptionsDataSource;
use Pepperfm\Flashboard\Integration\Laravel\DataSources\ResourceRelationRecordsDataSource;
use Pepperfm\Flashboard\Tests\Fixtures\Models\RelationManagerOrder;
use Pepperfm\Flashboard\Tests\Fixtures\Models\RelationManagerOrderItem;
use Pepperfm\Flashboard\Tests\Fixtures\Models\RelationManagerProfile;
use Pepperfm\Flashboard\Tests\Fixtures\Resources\RelationManagerOrderItemResource;
use Pepperfm\Flashboard\Tests\Fixtures\Resources\RelationManagerOrderResource;
use Pepperfm\Flashboard\Tests\Fixtures\Resources\RelationManagerProfileResource;
use Pepperfm\Flashboard\Tests\TestCase;

final class ResourceRelationRecordsDataSourceTest extends TestCase
{
    private Capsule $database;

    private RelationManagerOrder $order;

    private RelationManagerOrderItem $attachedItem;

    private RelationManagerOrderItem $orphanItem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeUrlGenerator();

        $this->database = new Capsule();
        $this->database->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $this->database->setAsGlobal();
        $this->database->bootEloquent();
        $this->database->schema()->create('relation_manager_orders', static function (\Illuminate\Database\Schema\Blueprint $table): void {
            $table->increments('id');
            $table->string('name')->nullable();
        });
        $this->database->schema()->create('relation_manager_profiles', static function (\Illuminate\Database\Schema\Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('order_id')->nullable();
            $table->string('name')->nullable();
        });
        $this->database->schema()->create('relation_manager_order_items', static function (\Illuminate\Database\Schema\Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('order_id')->nullable();
            $table->string('name')->nullable();
            $table->string('sku')->nullable();
        });

        $this->order = RelationManagerOrder::query()->create(['name' => 'Order 1']);
        RelationManagerProfile::query()->create([
            'order_id' => $this->order->getKey(),
            'name' => 'Profile A',
        ]);
        $this->attachedItem = RelationManagerOrderItem::query()->create([
            'order_id' => $this->order->getKey(),
            'name' => 'Attached item',
            'sku' => 'A-1',
        ]);
        $this->orphanItem = RelationManagerOrderItem::query()->create([
            'order_id' => null,
            'name' => 'Orphan item',
            'sku' => 'O-1',
        ]);
    }

    public function test_initial_payloads_include_has_one_and_has_many_contracts(): void
    {
        $payloads = $this->recordsDataSource()->initialPayloads(RelationManagerOrderResource::class, $this->order, null);
        $payloadsByKey = [];

        foreach ($payloads as $payload) {
            $payloadsByKey[$payload['key']] = $payload;
        }

        self::assertSame('has_one', $payloadsByKey['profile']['type']);
        self::assertSame('Profile A', $payloadsByKey['profile']['selected_record']['title']);
        self::assertSame('/flashboard.resources.relation_manager_order.relations.records', $payloadsByKey['profile']['records_url']);
        self::assertSame('/flashboard.resources.relation_manager_order.relations.attach-options', $payloadsByKey['profile']['options_url']);
        self::assertSame('has_many', $payloadsByKey['items']['type']);
        self::assertSame('Attached item', $payloadsByKey['items']['records'][0]['title']);
        self::assertSame(1, $payloadsByKey['items']['pagination']['current_page']);
    }

    public function test_records_endpoint_loads_paginated_has_many_records(): void
    {
        $payload = $this->recordsDataSource()->resolve(
            RelationManagerOrderResource::class,
            $this->order,
            'items',
            \Illuminate\Http\Request::create('/', 'GET', ['per_page' => 1]),
        );

        self::assertSame('items', $payload['key']);
        self::assertSame('Attached item', $payload['records'][0]['title']);
        self::assertFalse($payload['pagination']['has_more']);
    }

    public function test_attach_options_exclude_currently_attached_records_and_support_search(): void
    {
        $payload = $this->attachOptionsDataSource()->resolve(
            RelationManagerOrderResource::class,
            $this->order,
            'items',
            \Illuminate\Http\Request::create('/', 'GET', ['search' => 'orphan']),
        );

        self::assertSame([
            [
                'label' => 'Orphan item',
                'value' => $this->orphanItem->getKey(),
            ],
        ], $payload['items']);
        self::assertNotContains($this->attachedItem->getKey(), array_column($payload['items'], 'value'));
    }

    public function test_legacy_relation_payload_factory_stays_backward_compatible(): void
    {
        $payload = (new RelationPayloadFactory())->make(LegacyRelationManagerOrderResource::class, $this->order);

        self::assertSame('items', $payload[0]['key']);
        self::assertSame('Items', $payload[0]['label']);
        self::assertSame([
            [
                'key' => $this->attachedItem->getKey(),
                'title' => 'Attached item',
            ],
        ], $payload[0]['records']);
    }

    private function recordsDataSource(): ResourceRelationRecordsDataSource
    {
        $screenAccessResolver = new ScreenAccessResolver(new PolicyBridge());

        return new ResourceRelationRecordsDataSource(
            $this->authenticator(),
            $screenAccessResolver,
            new ExtensionRegistry(),
            $this->registry(),
            new ResourceSurfaceResolver($screenAccessResolver),
        );
    }

    private function attachOptionsDataSource(): ResourceRelationAttachOptionsDataSource
    {
        $screenAccessResolver = new ScreenAccessResolver(new PolicyBridge());

        return new ResourceRelationAttachOptionsDataSource(
            $this->authenticator(),
            $screenAccessResolver,
            new ExtensionRegistry(),
            $this->registry(),
            new ResourceSurfaceResolver($screenAccessResolver),
        );
    }

    private function registry(): ResourceRegistry
    {
        $registry = new ResourceRegistry();
        $registry->register(RelationManagerOrderResource::class);
        $registry->register(RelationManagerProfileResource::class);
        $registry->register(RelationManagerOrderItemResource::class);

        return $registry;
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

    private function fakeUrlGenerator(): void
    {
        $this->app->instance('url', new class()
        {
            public function route(string $name, array $parameters = [], bool $absolute = true): string
            {
                return '/' . ltrim($name, '/');
            }
        });
    }
}

final class LegacyRelationManagerOrderResource extends Resource
{
    public static function model(): string
    {
        return RelationManagerOrder::class;
    }

    public static function relations(): array
    {
        return [
            RelationDefinition::make('items', 'Items')
                ->titleAttribute('name'),
        ];
    }
}
