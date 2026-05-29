<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Tests\Feature;

use Illuminate\Database\Capsule\Manager as Capsule;
use Pepperfm\Flashboard\Contracts\Resources\Resource;
use Pepperfm\Flashboard\Core\Forms\Fields\BelongsToMany;
use Pepperfm\Flashboard\Core\Forms\Relations\BelongsToManyRelationMetadataResolver;
use Pepperfm\Flashboard\Core\Registry\ResourceRegistry;
use Pepperfm\Flashboard\Tests\Fixtures\Models\BelongsToManyTag;
use Pepperfm\Flashboard\Tests\Fixtures\Resources\BelongsToManyProductResource;
use Pepperfm\Flashboard\Tests\Fixtures\Resources\BelongsToManyTagResource;
use Pepperfm\Flashboard\Tests\TestCase;

final class BelongsToManyRelationResolverTest extends TestCase
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

    public function test_resolves_metadata_from_eloquent_belongs_to_many_relation_and_registered_resource(): void
    {
        $metadata = $this->resolver()->resolve(
            BelongsToManyProductResource::class,
            BelongsToMany::make('visible_tags', 'Visible tags', 'tags')
                ->titleAttribute('name')
                ->searchable(['name', 'slug'])
                ->maxItems(5)
                ->toArray(),
        );

        self::assertSame('visible_tags', $metadata->fieldKey);
        self::assertSame('tags', $metadata->relationship);
        self::assertSame(BelongsToManyTag::class, $metadata->relatedModel);
        self::assertSame(BelongsToManyTagResource::class, $metadata->relatedResource);
        self::assertSame('belongs_to_many_tags', $metadata->relatedTable);
        self::assertSame('belongs_to_many_product_tag', $metadata->pivotTable);
        self::assertSame('product_id', $metadata->foreignPivotKey);
        self::assertSame('tag_id', $metadata->relatedPivotKey);
        self::assertSame('id', $metadata->parentKey);
        self::assertSame('id', $metadata->relatedKey);
        self::assertSame('id', $metadata->recordKeyName);
        self::assertSame('name', $metadata->titleAttribute);
        self::assertSame(['name', 'slug'], $metadata->searchColumns);
        self::assertSame(5, $metadata->maxItems);
    }

    public function test_allows_explicit_model_fallback_without_registered_resource(): void
    {
        $registry = new ResourceRegistry();
        $registry->register(BelongsToManyProductResource::class);

        $metadata = (new BelongsToManyRelationMetadataResolver($registry))->resolve(
            BelongsToManyProductResource::class,
            BelongsToMany::make('tags', 'Tags')
                ->model(BelongsToManyTag::class)
                ->titleAttribute('name')
                ->toArray(),
        );

        self::assertSame(BelongsToManyTag::class, $metadata->relatedModel);
        self::assertNull($metadata->relatedResource);
        self::assertTrue($metadata->allowModelFallback);
        self::assertTrue($metadata->toPayload()['allow_model_fallback']);
    }

    public function test_rejects_non_belongs_to_many_relationships(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must be an instance of');

        $this->resolver()->resolve(
            BelongsToManyProductResource::class,
            BelongsToMany::make('name', 'Name', 'newQuery')->toArray(),
        );
    }

    public function test_rejects_missing_relationship(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('does not exist');

        $this->resolver()->resolve(
            BelongsToManyProductResource::class,
            BelongsToMany::make('missing_tags', 'Missing')->toArray(),
        );
    }

    public function test_rejects_ambiguous_auto_resource_matches(): void
    {
        $registry = new ResourceRegistry();
        $registry->register(BelongsToManyTagResource::class);
        $registry->register(DuplicateBelongsToManyTagResource::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('ambiguous');

        (new BelongsToManyRelationMetadataResolver($registry))->resolve(
            BelongsToManyProductResource::class,
            BelongsToMany::make('tags', 'Tags')->toArray(),
        );
    }

    public function test_explicit_resource_override_wins_over_ambiguous_auto_resource_matches(): void
    {
        $registry = new ResourceRegistry();
        $registry->register(BelongsToManyTagResource::class);
        $registry->register(DuplicateBelongsToManyTagResource::class);

        $metadata = (new BelongsToManyRelationMetadataResolver($registry))->resolve(
            BelongsToManyProductResource::class,
            BelongsToMany::make('tags', 'Tags')
                ->resource(BelongsToManyTagResource::class)
                ->toArray(),
        );

        self::assertSame(BelongsToManyTagResource::class, $metadata->relatedResource);
    }

    private function resolver(): BelongsToManyRelationMetadataResolver
    {
        $registry = new ResourceRegistry();
        $registry->register(BelongsToManyProductResource::class);
        $registry->register(BelongsToManyTagResource::class);

        return new BelongsToManyRelationMetadataResolver($registry);
    }
}

final class DuplicateBelongsToManyTagResource extends Resource
{
    public static function model(): string
    {
        return BelongsToManyTag::class;
    }
}
