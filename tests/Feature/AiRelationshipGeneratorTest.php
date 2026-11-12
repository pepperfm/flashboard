<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Tests\Feature;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Filesystem\Filesystem;
use Pepperfm\Flashboard\Ai\ModelCatalog;
use Pepperfm\Flashboard\Ai\ResourceChangeManager;
use Pepperfm\Flashboard\Ai\ResourceManager;
use Pepperfm\Flashboard\Core\Registry\ResourceRegistry;
use Pepperfm\Flashboard\Tests\Fixtures\Models\AiHarnessOrder;
use Pepperfm\Flashboard\Tests\Fixtures\Resources\AiHarnessCustomerResource;
use Pepperfm\Flashboard\Tests\Fixtures\Resources\AiHarnessTagResource;
use Pepperfm\Flashboard\Tests\Fixtures\Resources\AiHarnessItemResource;
use Pepperfm\Flashboard\Tests\TestCase;

final class AiRelationshipGeneratorTest extends TestCase
{
    private Capsule $database;

    private string $outputDirectory;

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

        $this->database->schema()->create('ai_harness_customers', static function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });
        $this->database->schema()->create('ai_harness_orders', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id');
            $table->string('title');
        });
        $this->database->schema()->create('ai_harness_tags', static function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });
        $this->database->schema()->create('ai_harness_items', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id');
            $table->string('name');
        });
        $this->database->schema()->create('ai_harness_order_tag', static function (Blueprint $table): void {
            $table->foreignId('order_id');
            $table->foreignId('tag_id');
        });

        $registry = new ResourceRegistry();
        $registry->registerMany([
            AiHarnessCustomerResource::class,
            AiHarnessTagResource::class,
            AiHarnessItemResource::class,
        ]);
        $this->app->instance(ResourceRegistry::class, $registry);

        $this->outputDirectory = sys_get_temp_dir() . '/flashboard-ai-rel-' . bin2hex(random_bytes(8));
    }

    protected function tearDown(): void
    {
        (new Filesystem())->deleteDirectory($this->outputDirectory);
        parent::tearDown();
    }

    public function test_full_resource_generation_supports_four_eloquent_relation_types(): void
    {
        $manager = $this->manager();
        $spec = $this->spec();
        $spec['relations'] = [
            ['name' => 'customer', 'type' => 'belongs_to', 'title_attribute' => 'name'],
            ['name' => 'tags', 'type' => 'belongs_to_many', 'title_attribute' => 'name'],
            ['name' => 'items', 'type' => 'has_many', 'show_on_edit' => true],
            ['name' => 'featuredItem', 'type' => 'has_one'],
        ];

        $preview = $manager->preview($spec);
        self::assertSame('create', $preview['operation']);
        self::assertTrue($preview['writable']);

        $created = $manager->create($spec, $preview['proposal_hash']);
        $source = (string) file_get_contents($created['path']);

        self::assertStringContainsString("BelongsTo::make('customer_id', 'Customer')->relationship('customer')", $source);
        self::assertStringContainsString("BelongsToMany::make('tags', 'Tags')", $source);
        self::assertStringContainsString("HasMany::make('items', 'Items')", $source);
        self::assertStringContainsString("HasOne::make('featuredItem', 'Featured Item')", $source);
        self::assertStringContainsString('->readOnly()->showOnEdit()', $source);
        self::assertStringNotContainsString("TextInput::make('customer_id'", $source);
        self::assertStringContainsString('public static function relations(): array', $source);
        self::assertStringContainsString('AiHarnessCustomerResource::class', $source);
        self::assertCount(4, $manager->managedSpecification('Orders')['relations']);
    }

    public function test_add_relation_tool_contract_requires_preview_hashes_and_preserves_existing_fields(): void
    {
        $manager = $this->manager();
        $spec = $this->spec();
        $preview = $manager->preview($spec);
        $created = $manager->create($spec, $preview['proposal_hash']);
        $changes = new ResourceChangeManager($manager);
        $relation = ['name' => 'customer', 'type' => 'belongs_to'];

        $relationPreview = $changes->previewRelation('Orders', $relation);
        self::assertSame('update', $relationPreview['operation']);
        self::assertTrue($relationPreview['writable']);
        self::assertStringContainsString("+                BelongsTo::make('customer_id'", $relationPreview['diff']);

        $this->expectException(\InvalidArgumentException::class);
        $changes->addRelation('Orders', $relation, str_repeat('0', 64), str_repeat('0', 64));
    }

    public function test_incremental_relation_and_field_addition_updates_the_manifest(): void
    {
        $manager = $this->manager();
        $spec = $this->spec();
        $preview = $manager->preview($spec);
        $manager->create($spec, $preview['proposal_hash']);

        $changes = new ResourceChangeManager($manager);
        $relation = ['name' => 'customer', 'type' => 'belongs_to'];
        $first = $changes->previewRelation('Orders', $relation);
        $changes->addRelation('Orders', $relation, $first['expected_sha'], $first['proposal_hash']);

        $field = ['name' => 'customer_id', 'type' => 'number', 'form' => false];
        $second = $changes->previewField('Orders', $field);
        $changes->addField('Orders', $field, $second['expected_sha'], $second['proposal_hash']);

        $updated = $manager->managedSpecification('Orders');
        self::assertCount(1, $updated['relations']);
        self::assertCount(3, $updated['fields']);
        self::assertSame('customer_id', $updated['fields'][2]['name']);
    }

    public function test_manually_edited_resource_is_not_incrementally_rewritten(): void
    {
        $manager = $this->manager();
        $spec = $this->spec();
        $preview = $manager->preview($spec);
        $created = $manager->create($spec, $preview['proposal_hash']);
        file_put_contents($created['path'], "\n// manual edit\n", FILE_APPEND);

        $this->expectException(\RuntimeException::class);
        (new ResourceChangeManager($manager))->previewRelation('Orders', ['name' => 'customer', 'type' => 'belongs_to']);
    }

    public function test_unknown_eloquent_relation_or_unregistered_related_resource_is_rejected(): void
    {
        $spec = $this->spec();
        $spec['relations'] = [['name' => 'missing', 'type' => 'belongs_to']];

        try {
            $this->manager()->preview($spec);
            self::fail('Unknown relation must be rejected.');
        } catch (\InvalidArgumentException) {
            //
        }

        $registry = new ResourceRegistry();
        $this->app->instance(ResourceRegistry::class, $registry);
        $spec['relations'] = [['name' => 'customer', 'type' => 'belongs_to']];

        $this->expectException(\InvalidArgumentException::class);
        $this->manager()->preview($spec);
    }

    /**
     * @return array<string, mixed>
     */
    private function spec(): array
    {
        return [
            'resource' => 'Orders',
            'model' => AiHarnessOrder::class,
            'fields' => [
                ['name' => 'id', 'form' => false, 'sortable' => true],
                ['name' => 'title', 'required' => true],
            ],
            'allow_delete' => false,
        ];
    }

    private function manager(): ResourceManager
    {
        $catalog = new ModelCatalog(
            __DIR__ . '/../Fixtures/Models',
            'Pepperfm\\Flashboard\\Tests\\Fixtures\\Models\\',
        );

        return new ResourceManager($catalog, new Filesystem(), $this->outputDirectory);
    }
}
