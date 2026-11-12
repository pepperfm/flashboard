<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Tests\Feature;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Filesystem\Filesystem;
use InvalidArgumentException;
use Pepperfm\Flashboard\Ai\ModelCatalog;
use Pepperfm\Flashboard\Ai\ResourceManager;
use Pepperfm\Flashboard\Tests\Fixtures\Models\LazyFilterOptionRecord;
use Pepperfm\Flashboard\Tests\TestCase;
use RuntimeException;

final class AiResourceManagerTest extends TestCase
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

        $this->database->schema()->create('lazy_filter_option_records', static function (Blueprint $table): void {
            $table->increments('id');
            $table->string('status');
            $table->string('status_label')->nullable();
            $table->string('password')->nullable();
        });

        $this->outputDirectory = sys_get_temp_dir() . '/flashboard-ai-' . bin2hex(random_bytes(8));
    }

    protected function tearDown(): void
    {
        (new Filesystem())->deleteDirectory($this->outputDirectory);
        parent::tearDown();
    }

    public function test_catalog_inspects_schema_without_reading_model_records(): void
    {
        $catalog = $this->catalog();
        $inspection = $catalog->inspect(LazyFilterOptionRecord::class);

        self::assertSame('lazy_filter_option_records', $inspection['table']);
        self::assertSame('id', $inspection['primary_key']);
        self::assertContains('status', array_column($inspection['fields'], 'name'));
        self::assertSame([], $inspection['relations']);
    }

    public function test_resource_can_be_previewed_created_and_updated_with_exact_hashes(): void
    {
        $manager = $this->manager();
        $spec = $this->spec();
        $preview = $manager->preview($spec);

        self::assertSame('create', $preview['operation']);
        self::assertTrue($preview['writable']);
        self::assertNull($preview['expected_sha']);
        self::assertStringContainsString('+final class StatusesResource', $preview['diff']);
        self::assertFileDoesNotExist($preview['path']);

        $created = $manager->create($spec, $preview['proposal_hash']);
        self::assertSame($preview['path'], $created['path']);
        self::assertFileExists($created['path']);

        $source = (string) file_get_contents($created['path']);
        self::assertStringContainsString('return LazyFilterOptionRecord::class;', $source);
        self::assertStringContainsString("Select::make('status', 'Status')->options(", $source);
        self::assertStringContainsString("SelectFilter::make('status', 'Status')->options(", $source);
        self::assertStringContainsString("TextInput::make('status_label', 'Status Label')", $source);
        self::assertStringContainsString('// @flashboard-ai-fingerprint:', $source);
        self::assertStringNotContainsString('DeleteAction::make(),', $source);

        $spec['fields'][1]['label'] = 'Review Status';
        $updatePreview = $manager->preview($spec);
        self::assertSame('update', $updatePreview['operation']);
        self::assertTrue($updatePreview['writable']);
        self::assertStringContainsString('-                Select::make', $updatePreview['diff']);

        $manager->update($spec, $updatePreview['expected_sha'], $updatePreview['proposal_hash']);
        self::assertStringContainsString('Review Status', (string) file_get_contents($created['path']));
    }

    public function test_manual_edits_and_stale_preview_are_never_overwritten(): void
    {
        $manager = $this->manager();
        $spec = $this->spec();
        $preview = $manager->preview($spec);
        $created = $manager->create($spec, $preview['proposal_hash']);

        $spec['fields'][1]['label'] = 'Updated';
        $updatePreview = $manager->preview($spec);
        file_put_contents($created['path'], "\n// local developer edit\n", FILE_APPEND);

        self::assertFalse($manager->preview($spec)['writable']);

        $this->expectException(RuntimeException::class);
        $manager->update($spec, $updatePreview['expected_sha'], $updatePreview['proposal_hash']);
    }

    public function test_create_refuses_an_existing_file_and_unpreviewed_proposals(): void
    {
        $manager = $this->manager();
        $spec = $this->spec();
        $preview = $manager->preview($spec);

        try {
            $manager->create($spec, str_repeat('0', 64));
            self::fail('Unpreviewed hash should be rejected.');
        } catch (InvalidArgumentException) {
            self::assertFileDoesNotExist($preview['path']);
        }

        $manager->create($spec, $preview['proposal_hash']);

        $this->expectException(RuntimeException::class);
        $manager->create($spec, $preview['proposal_hash']);
    }

    public function test_compiler_rejects_unknown_sensitive_and_unfillable_fields(): void
    {
        $manager = $this->manager();
        $spec = $this->spec();

        foreach (['missing_field', 'password'] as $invalid) {
            $spec['fields'][1]['name'] = $invalid;

            try {
                $manager->preview($spec);
                self::fail("Field [$invalid] should not compile.");
            } catch (InvalidArgumentException) {
                // Validation belongs to the compiler, not the agent.
            }
        }

        $spec['fields'][1]['name'] = 'status';
        $spec['fields'][1]['form'] = false;
        $spec['fields'][2]['name'] = 'id';
        $spec['fields'][2]['form'] = true;

        $this->expectException(InvalidArgumentException::class);
        $manager->preview($spec);
    }

    public function test_generator_refuses_unmanaged_existing_files(): void
    {
        $manager = $this->manager();
        $spec = $this->spec();
        $preview = $manager->preview($spec);

        (new Filesystem())->ensureDirectoryExists($this->outputDirectory);
        file_put_contents($preview['path'], '<?php // handwritten resource');

        self::assertFalse($manager->preview($spec)['writable']);

        $this->expectException(RuntimeException::class);
        $manager->update($spec, hash('sha256', '<?php // handwritten resource'), $preview['proposal_hash']);
    }

    /**
     * @return array<string, mixed>
     */
    private function spec(): array
    {
        return [
            'resource' => 'Statuses',
            'model' => LazyFilterOptionRecord::class,
            'fields' => [
                ['name' => 'id', 'form' => false, 'sortable' => true],
                [
                    'name' => 'status',
                    'type' => 'select',
                    'options' => ['draft' => 'Draft', 'published' => 'Published'],
                    'required' => true,
                    'filter' => true,
                    'searchable' => true,
                ],
                ['name' => 'status_label'],
            ],
        ];
    }

    private function catalog(): ModelCatalog
    {
        return new ModelCatalog(
            __DIR__ . '/../Fixtures/Models',
            'Pepperfm\\Flashboard\\Tests\\Fixtures\\Models\\',
        );
    }

    private function manager(): ResourceManager
    {
        return new ResourceManager($this->catalog(), new Filesystem(), $this->outputDirectory);
    }
}
