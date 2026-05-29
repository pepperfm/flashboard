<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Tests\Feature;

use Illuminate\Database\Capsule\Manager as Capsule;
use Pepperfm\Flashboard\Contracts\Resources\Resource;
use Pepperfm\Flashboard\Core\Forms\Fields\BelongsTo;
use Pepperfm\Flashboard\Core\Forms\Relations\BelongsToRelationMetadataResolver;
use Pepperfm\Flashboard\Core\Registry\ResourceRegistry;
use Pepperfm\Flashboard\Tests\Fixtures\Models\BelongsToCategory;
use Pepperfm\Flashboard\Tests\Fixtures\Resources\BelongsToCategoryResource;
use Pepperfm\Flashboard\Tests\Fixtures\Resources\BelongsToProductResource;
use Pepperfm\Flashboard\Tests\TestCase;

final class BelongsToRelationResolverTest extends TestCase
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

    public function test_resolves_metadata_from_eloquent_belongs_to_relation_and_registered_resource(): void
    {
        $metadata = $this->resolver()->resolve(
            BelongsToProductResource::class,
            BelongsTo::make('category_id', 'Category', 'category')
                ->titleAttribute('name')
                ->searchable(['name', 'slug'])
                ->toArray(),
        );

        self::assertSame('category_id', $metadata->fieldKey);
        self::assertSame('category', $metadata->relationship);
        self::assertSame(BelongsToCategory::class, $metadata->relatedModel);
        self::assertSame(BelongsToCategoryResource::class, $metadata->relatedResource);
        self::assertSame('category_id', $metadata->foreignKey);
        self::assertSame('id', $metadata->ownerKey);
        self::assertSame('id', $metadata->recordKeyName);
        self::assertSame('belongs_to_categories', $metadata->relatedTable);
        self::assertSame('name', $metadata->titleAttribute);
        self::assertSame(['name', 'slug'], $metadata->searchColumns);
    }

    public function test_resolves_foreign_key_when_field_key_is_relationship_name(): void
    {
        $metadata = $this->resolver()->resolve(
            BelongsToProductResource::class,
            BelongsTo::make('category', 'Category')
                ->titleAttribute('name')
                ->toArray(),
        );

        self::assertSame('category', $metadata->fieldKey);
        self::assertSame('category', $metadata->relationship);
        self::assertSame('category_id', $metadata->foreignKey);
        self::assertSame('id', $metadata->ownerKey);
    }

    public function test_infers_relationship_from_foreign_key_when_model_fallback_is_explicit(): void
    {
        $metadata = $this->resolver()->resolve(
            BelongsToProductResource::class,
            BelongsTo::make('author_uuid', 'Author')
                ->model(BelongsToCategory::class)
                ->foreignKey('author_uuid')
                ->ownerKey('uuid')
                ->recordKeyName('uuid')
                ->titleAttribute('name')
                ->toArray(),
        );

        self::assertSame('author', $metadata->relationship);
        self::assertSame(BelongsToCategory::class, $metadata->relatedModel);
        self::assertSame('author_uuid', $metadata->foreignKey);
        self::assertSame('uuid', $metadata->ownerKey);
        self::assertSame('uuid', $metadata->recordKeyName);
        self::assertTrue($metadata->allowModelFallback);
    }

    public function test_rejects_non_belongs_to_relationships(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must be an instance of');

        $this->resolver()->resolve(
            BelongsToProductResource::class,
            BelongsTo::make('name', 'Name', 'newQuery')->toArray(),
        );
    }

    public function test_rejects_missing_relationship_without_explicit_model_fallback(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('does not exist');

        $this->resolver()->resolve(
            BelongsToProductResource::class,
            BelongsTo::make('missing_id', 'Missing')->toArray(),
        );
    }

    public function test_rejects_ambiguous_auto_resource_matches(): void
    {
        $registry = new ResourceRegistry();
        $registry->register(BelongsToCategoryResource::class);
        $registry->register(DuplicateBelongsToCategoryResource::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('ambiguous');

        (new BelongsToRelationMetadataResolver($registry))->resolve(
            BelongsToProductResource::class,
            BelongsTo::make('category_id', 'Category', 'category')->toArray(),
        );
    }

    public function test_explicit_resource_override_wins_over_ambiguous_auto_resource_matches(): void
    {
        $registry = new ResourceRegistry();
        $registry->register(BelongsToCategoryResource::class);
        $registry->register(DuplicateBelongsToCategoryResource::class);

        $metadata = (new BelongsToRelationMetadataResolver($registry))->resolve(
            BelongsToProductResource::class,
            BelongsTo::make('category_id', 'Category', 'category')
                ->resource(BelongsToCategoryResource::class)
                ->toArray(),
        );

        self::assertSame(BelongsToCategoryResource::class, $metadata->relatedResource);
    }

    private function resolver(): BelongsToRelationMetadataResolver
    {
        $registry = new ResourceRegistry();
        $registry->register(BelongsToCategoryResource::class);
        $registry->register(BelongsToProductResource::class);

        return new BelongsToRelationMetadataResolver($registry);
    }
}

final class DuplicateBelongsToCategoryResource extends Resource
{
    public static function model(): string
    {
        return BelongsToCategory::class;
    }
}
