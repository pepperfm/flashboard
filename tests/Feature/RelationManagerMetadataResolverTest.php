<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Tests\Feature;

use Illuminate\Database\Capsule\Manager as Capsule;
use Pepperfm\Flashboard\Contracts\Resources\Resource;
use Pepperfm\Flashboard\Core\Registry\ResourceRegistry;
use Pepperfm\Flashboard\Core\Relations\HasMany;
use Pepperfm\Flashboard\Core\Relations\HasOne;
use Pepperfm\Flashboard\Integration\Laravel\Relations\RelationManagerMetadataResolver;
use Pepperfm\Flashboard\Tests\Fixtures\Models\RelationManagerOrderItem;
use Pepperfm\Flashboard\Tests\Fixtures\Models\RelationManagerProfile;
use Pepperfm\Flashboard\Tests\Fixtures\Resources\RelationManagerOrderItemResource;
use Pepperfm\Flashboard\Tests\Fixtures\Resources\RelationManagerOrderResource;
use Pepperfm\Flashboard\Tests\Fixtures\Resources\RelationManagerProfileResource;
use Pepperfm\Flashboard\Tests\TestCase;

final class RelationManagerMetadataResolverTest extends TestCase
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
    }

    public function test_resolves_has_one_metadata_from_eloquent_relation(): void
    {
        $metadata = $this->resolver()->resolve(
            RelationManagerOrderResource::class,
            HasOne::make('profile', 'Profile')->toArray(),
        );

        self::assertSame(HasOne::TYPE, $metadata->type);
        self::assertSame('profile', $metadata->key);
        self::assertSame('Profile', $metadata->label);
        self::assertSame(RelationManagerProfile::class, $metadata->relatedModel);
        self::assertSame(RelationManagerProfileResource::class, $metadata->relatedResource);
        self::assertSame('relation_manager_profiles', $metadata->relatedTable);
        self::assertSame('id', $metadata->localKey);
        self::assertSame('order_id', $metadata->foreignKey);
        self::assertSame('id', $metadata->recordKeyName);
        self::assertSame('name', $metadata->titleAttribute);
        self::assertSame(['name'], $metadata->searchColumns);
    }

    public function test_resolves_has_many_metadata_with_overrides(): void
    {
        $metadata = $this->resolver()->resolve(
            RelationManagerOrderResource::class,
            HasMany::make('recent_items', 'Recent items', 'items')
                ->resource(RelationManagerOrderItemResource::class)
                ->localKey('uuid')
                ->foreignKey('order_uuid')
                ->recordKeyName('uuid')
                ->titleAttribute('sku')
                ->searchable(['sku'])
                ->perPage(20)
                ->attachable()
                ->detachable()
                ->syncable()
                ->toArray(),
        );

        self::assertSame(HasMany::TYPE, $metadata->type);
        self::assertSame('recent_items', $metadata->key);
        self::assertSame('items', $metadata->relationship);
        self::assertSame(RelationManagerOrderItem::class, $metadata->relatedModel);
        self::assertSame(RelationManagerOrderItemResource::class, $metadata->relatedResource);
        self::assertSame('uuid', $metadata->localKey);
        self::assertSame('order_uuid', $metadata->foreignKey);
        self::assertSame('uuid', $metadata->recordKeyName);
        self::assertSame('sku', $metadata->titleAttribute);
        self::assertSame(['sku'], $metadata->searchColumns);
        self::assertSame(20, $metadata->perPage);
        self::assertTrue($metadata->attachable);
        self::assertTrue($metadata->detachable);
        self::assertTrue($metadata->syncable);
    }

    public function test_rejects_unsupported_relation_types(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must be an instance');

        $this->resolver()->resolve(
            RelationManagerOrderResource::class,
            HasOne::make('category')->toArray(),
        );
    }

    public function test_ambiguous_related_resource_requires_explicit_resource(): void
    {
        $registry = $this->registry();
        $registry->register(DuplicateRelationManagerProfileResource::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('ambiguous');

        (new RelationManagerMetadataResolver($registry))->resolve(
            RelationManagerOrderResource::class,
            HasOne::make('profile')->toArray(),
        );
    }

    private function resolver(): RelationManagerMetadataResolver
    {
        return new RelationManagerMetadataResolver($this->registry());
    }

    private function registry(): ResourceRegistry
    {
        $registry = new ResourceRegistry();
        $registry->register(RelationManagerOrderResource::class);
        $registry->register(RelationManagerProfileResource::class);
        $registry->register(RelationManagerOrderItemResource::class);

        return $registry;
    }
}

final class DuplicateRelationManagerProfileResource extends Resource
{
    public static function model(): string
    {
        return RelationManagerProfile::class;
    }
}
