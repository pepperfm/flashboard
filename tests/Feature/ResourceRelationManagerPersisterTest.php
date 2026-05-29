<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Tests\Feature;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Builder;
use Pepperfm\Flashboard\Contracts\Extensions\QueryExtensionContract;
use Pepperfm\Flashboard\Contracts\Resources\Resource;
use Pepperfm\Flashboard\Core\Authorization\Visibility\ScreenAccessResolver;
use Pepperfm\Flashboard\Core\Extensions\ExtensionRegistry;
use Pepperfm\Flashboard\Core\Registry\ResourceRegistry;
use Pepperfm\Flashboard\Core\Relations\HasMany;
use Pepperfm\Flashboard\Integration\Laravel\Auth\PolicyBridge;
use Pepperfm\Flashboard\Integration\Laravel\Persistence\ResourceRelationManagerPersister;
use Pepperfm\Flashboard\Tests\Fixtures\Models\RelationManagerOrder;
use Pepperfm\Flashboard\Tests\Fixtures\Models\RelationManagerOrderItem;
use Pepperfm\Flashboard\Tests\Fixtures\Models\RelationManagerProfile;
use Pepperfm\Flashboard\Tests\Fixtures\Resources\RelationManagerOrderItemResource;
use Pepperfm\Flashboard\Tests\Fixtures\Resources\RelationManagerOrderResource;
use Pepperfm\Flashboard\Tests\Fixtures\Resources\RelationManagerProfileResource;
use Pepperfm\Flashboard\Tests\TestCase;

final class ResourceRelationManagerPersisterTest extends TestCase
{
    private Capsule $database;

    public function test_attach_sets_related_foreign_key_to_parent_key(): void
    {
        $this->createTables();
        $order = RelationManagerOrder::query()->create(['name' => 'Order 1']);
        $item = RelationManagerOrderItem::query()->create([
            'order_id' => null,
            'name' => 'Detached item',
        ]);

        $this->persister()->attach(RelationManagerOrderResource::class, $order, 'items', $item->getKey());

        self::assertSame($order->getKey(), $item->refresh()->getAttribute('order_id'));
    }

    public function test_detach_clears_nullable_foreign_key_without_deleting_record(): void
    {
        $this->createTables();
        $order = RelationManagerOrder::query()->create(['name' => 'Order 1']);
        $item = RelationManagerOrderItem::query()->create([
            'order_id' => $order->getKey(),
            'name' => 'Attached item',
        ]);

        $this->persister()->detach(RelationManagerOrderResource::class, $order, 'items', [$item->getKey()]);

        self::assertNull($item->refresh()->getAttribute('order_id'));
        self::assertSame(1, RelationManagerOrderItem::query()->count());
    }

    public function test_detach_rejects_non_nullable_foreign_key(): void
    {
        $this->createTables(nullableForeignKey: false);
        $order = RelationManagerOrder::query()->create(['name' => 'Order 1']);
        $item = RelationManagerOrderItem::query()->create([
            'order_id' => $order->getKey(),
            'name' => 'Attached item',
        ]);

        $this->expectException(\InvalidArgumentException::class);

        try {
            $this->persister()->detach(RelationManagerOrderResource::class, $order, 'items', [$item->getKey()]);
        } finally {
            self::assertSame($order->getKey(), $item->refresh()->getAttribute('order_id'));
        }
    }

    public function test_replace_detaches_previous_has_one_record_and_attaches_replacement(): void
    {
        $this->createTables();
        $order = RelationManagerOrder::query()->create(['name' => 'Order 1']);
        $current = RelationManagerProfile::query()->create([
            'order_id' => $order->getKey(),
            'name' => 'Current profile',
        ]);
        $replacement = RelationManagerProfile::query()->create([
            'order_id' => null,
            'name' => 'Replacement profile',
        ]);

        $this->persister()->replace(RelationManagerOrderResource::class, $order, 'profile', $replacement->getKey());

        self::assertNull($current->refresh()->getAttribute('order_id'));
        self::assertSame($order->getKey(), $replacement->refresh()->getAttribute('order_id'));
    }

    public function test_sync_moves_selected_records_and_detaches_omitted_records(): void
    {
        $this->createTables();
        $firstOrder = RelationManagerOrder::query()->create(['name' => 'Order 1']);
        $secondOrder = RelationManagerOrder::query()->create(['name' => 'Order 2']);
        $omitted = RelationManagerOrderItem::query()->create([
            'order_id' => $firstOrder->getKey(),
            'name' => 'Omitted item',
        ]);
        $moved = RelationManagerOrderItem::query()->create([
            'order_id' => $secondOrder->getKey(),
            'name' => 'Moved item',
        ]);
        $orphan = RelationManagerOrderItem::query()->create([
            'order_id' => null,
            'name' => 'Orphan item',
        ]);

        $this->persister()->sync(
            RelationManagerOrderResource::class,
            $firstOrder,
            'items',
            [$moved->getKey(), $orphan->getKey()],
        );

        self::assertNull($omitted->refresh()->getAttribute('order_id'));
        self::assertSame($firstOrder->getKey(), $moved->refresh()->getAttribute('order_id'));
        self::assertSame($firstOrder->getKey(), $orphan->refresh()->getAttribute('order_id'));
    }

    public function test_disabled_attach_mode_fails_closed(): void
    {
        $this->createTables();
        $order = RelationManagerOrder::query()->create(['name' => 'Order 1']);
        $item = RelationManagerOrderItem::query()->create([
            'order_id' => null,
            'name' => 'Detached item',
        ]);

        $this->expectException(\InvalidArgumentException::class);

        $this->persister($this->readOnlyRegistry())->attach(
            ReadOnlyRelationManagerOrderResource::class,
            $order,
            'items',
            $item->getKey(),
        );
    }

    public function test_scoped_out_selected_records_fail_before_mutation(): void
    {
        $this->createTables();
        $order = RelationManagerOrder::query()->create(['name' => 'Order 1']);
        $visible = RelationManagerOrderItem::query()->create([
            'order_id' => $order->getKey(),
            'name' => 'Visible item',
        ]);
        $hidden = RelationManagerOrderItem::query()->create([
            'order_id' => $order->getKey(),
            'name' => 'Hidden item',
        ]);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);

        try {
            $this->persister($this->scopedRegistry())->sync(
                ScopedRelationManagerOrderResource::class,
                $order,
                'items',
                [$visible->getKey(), $hidden->getKey()],
            );
        } finally {
            self::assertSame($order->getKey(), $visible->refresh()->getAttribute('order_id'));
            self::assertSame($order->getKey(), $hidden->refresh()->getAttribute('order_id'));
        }
    }

    public function test_attach_respects_relation_attach_options_query_modifier(): void
    {
        $this->createTables();
        $order = RelationManagerOrder::query()->create(['name' => 'Order 1']);
        $allowed = RelationManagerOrderItem::query()->create([
            'order_id' => null,
            'name' => 'Allowed item',
            'sku' => 'allowed',
        ]);
        $blocked = RelationManagerOrderItem::query()->create([
            'order_id' => null,
            'name' => 'Blocked item',
            'sku' => 'blocked',
        ]);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);

        try {
            $this->persister($this->modifiedRegistry())->attach(
                ModifiedPersisterRelationManagerOrderResource::class,
                $order,
                'items',
                $blocked->getKey(),
            );
        } finally {
            self::assertNull($blocked->refresh()->getAttribute('order_id'));

            $this->persister($this->modifiedRegistry())->attach(
                ModifiedPersisterRelationManagerOrderResource::class,
                $order,
                'items',
                $allowed->getKey(),
            );
            self::assertSame($order->getKey(), $allowed->refresh()->getAttribute('order_id'));
        }
    }

    public function test_detach_respects_relation_records_query_modifier(): void
    {
        $this->createTables();
        $order = RelationManagerOrder::query()->create(['name' => 'Order 1']);
        $hidden = RelationManagerOrderItem::query()->create([
            'order_id' => $order->getKey(),
            'name' => 'Hidden item',
            'sku' => 'hidden',
        ]);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);

        try {
            $this->persister($this->modifiedRegistry())->detach(
                ModifiedPersisterRelationManagerOrderResource::class,
                $order,
                'items',
                [$hidden->getKey()],
            );
        } finally {
            self::assertSame($order->getKey(), $hidden->refresh()->getAttribute('order_id'));
        }
    }

    private function createTables(bool $nullableForeignKey = true): void
    {
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
        $this->database->schema()->create('relation_manager_profiles', static function (\Illuminate\Database\Schema\Blueprint $table) use ($nullableForeignKey): void {
            $table->increments('id');
            $column = $table->unsignedInteger('order_id');

            if ($nullableForeignKey) {
                $column->nullable();
            }

            $table->string('name')->nullable();
        });
        $this->database->schema()->create('relation_manager_order_items', static function (\Illuminate\Database\Schema\Blueprint $table) use ($nullableForeignKey): void {
            $table->increments('id');
            $column = $table->unsignedInteger('order_id');

            if ($nullableForeignKey) {
                $column->nullable();
            }

            $table->string('name')->nullable();
            $table->string('sku')->nullable();
        });
    }

    private function persister(?ResourceRegistry $registry = null): ResourceRelationManagerPersister
    {
        return new ResourceRelationManagerPersister(
            $registry ?? $this->registry(),
            new ScreenAccessResolver(new PolicyBridge()),
            new ExtensionRegistry(),
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

    private function readOnlyRegistry(): ResourceRegistry
    {
        $registry = new ResourceRegistry();
        $registry->register(ReadOnlyRelationManagerOrderResource::class);
        $registry->register(RelationManagerOrderItemResource::class);

        return $registry;
    }

    private function scopedRegistry(): ResourceRegistry
    {
        $registry = new ResourceRegistry();
        $registry->register(ScopedRelationManagerOrderResource::class);
        $registry->register(ScopedRelationManagerOrderItemResource::class);

        return $registry;
    }

    private function modifiedRegistry(): ResourceRegistry
    {
        $registry = new ResourceRegistry();
        $registry->register(ModifiedPersisterRelationManagerOrderResource::class);
        $registry->register(RelationManagerOrderItemResource::class);

        return $registry;
    }
}

final class ReadOnlyRelationManagerOrderResource extends Resource
{
    public static function model(): string
    {
        return RelationManagerOrder::class;
    }

    public static function relations(): array
    {
        return [
            HasMany::make('items', 'Items')
                ->resource(RelationManagerOrderItemResource::class),
        ];
    }
}

final class ScopedRelationManagerOrderResource extends Resource
{
    public static function model(): string
    {
        return RelationManagerOrder::class;
    }

    public static function relations(): array
    {
        return [
            HasMany::make('items', 'Items')
                ->resource(ScopedRelationManagerOrderItemResource::class)
                ->attachable()
                ->detachable()
                ->syncable(),
        ];
    }
}

final class ScopedRelationManagerOrderItemResource extends Resource
{
    public static function model(): string
    {
        return RelationManagerOrderItem::class;
    }

    public static function queryExtensions(): array
    {
        return [
            new class() implements QueryExtensionContract
            {
                public function extend(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
                {
                    return $query->where('name', '!=', 'Hidden item');
                }
            },
        ];
    }
}

final class ModifiedPersisterRelationManagerOrderResource extends Resource
{
    public static function model(): string
    {
        return RelationManagerOrder::class;
    }

    public static function relations(): array
    {
        return [
            HasMany::make('items', 'Items')
                ->resource(RelationManagerOrderItemResource::class)
                ->attachable()
                ->detachable()
                ->syncable()
                ->modifyRecordsQueryUsing(static fn (Builder $query): Builder => $query->where('sku', 'visible'))
                ->modifyAttachOptionsQueryUsing(static fn (Builder $query): Builder => $query->where('sku', 'allowed')),
        ];
    }
}
