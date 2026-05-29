<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Tests\Feature;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory as ValidationFactory;
use Illuminate\Validation\ValidationException;
use Pepperfm\Flashboard\Contracts\Extensions\RuntimeHookContract;
use Pepperfm\Flashboard\Contracts\Forms\FormContract;
use Pepperfm\Flashboard\Contracts\Resources\Resource;
use Pepperfm\Flashboard\Core\Forms\Fields\BelongsTo;
use Pepperfm\Flashboard\Core\Forms\Fields\BelongsToMany;
use Pepperfm\Flashboard\Core\Forms\Fields\FileUpload;
use Pepperfm\Flashboard\Core\Forms\Fields\PasswordInput;
use Pepperfm\Flashboard\Core\Forms\Fields\TextInput;
use Pepperfm\Flashboard\Core\Hooks\RuntimeHookDispatcher;
use Pepperfm\Flashboard\Integration\Laravel\Persistence\ResourceFormPersister;
use Pepperfm\Flashboard\Tests\Fixtures\Models\BelongsToCategory;
use Pepperfm\Flashboard\Tests\Fixtures\Models\BelongsToManyProduct;
use Pepperfm\Flashboard\Tests\Fixtures\Models\BelongsToManyTag;
use Pepperfm\Flashboard\Tests\Fixtures\Resources\BelongsToManyTagResource;
use Pepperfm\Flashboard\Tests\TestCase;

final class ResourceFormPersisterTest extends TestCase
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
        $this->database->schema()->create('resource_form_persister_records', static function (\Illuminate\Database\Schema\Blueprint $table): void {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->string('password')->nullable();
            $table->string('receipt')->nullable();
            $table->unsignedInteger('category_id')->nullable();
        });
        $this->app->instance('validator', new ValidationFactory(
            new Translator(new ArrayLoader(), 'en'),
            $this->app,
        ));
        ResourceFormPersisterCapturingHook::$calls = [];
        ResourceFormPersisterBelongsToManyProductResource::$afterSaveTagIds = [];
        ResourceFormPersisterBelongsToManyProductResource::$afterSaveDataKeys = [];
    }

    public function test_update_skips_empty_passwords_and_raw_unstored_file_uploads(): void
    {
        $record = ResourceFormPersisterRecord::query()->create([
            'name' => 'Original',
            'password' => 'existing-hash',
            'receipt' => 'receipts/original.pdf',
        ]);

        $updated = (new ResourceFormPersister(new RuntimeHookDispatcher()))
            ->update($this->resourceClass(), $record, [
                'name' => 'Updated',
                'password' => '',
                'receipt' => $this->uploadedFile(),
            ]);

        self::assertSame('Updated', $updated->getAttribute('name'));
        self::assertSame('existing-hash', $updated->getAttribute('password'));
        self::assertSame('receipts/original.pdf', $updated->getAttribute('receipt'));
    }

    public function test_create_strips_password_confirmation_payload_before_mass_assignment(): void
    {
        $created = (new ResourceFormPersister(new RuntimeHookDispatcher()))
            ->create($this->resourceClass(), [
                'name' => 'Created',
                'password' => 'secret',
                'password_confirmation' => 'secret',
            ]);

        self::assertSame('Created', $created->getAttribute('name'));
        self::assertSame('secret', $created->getAttribute('password'));
        self::assertArrayNotHasKey('password_confirmation', $created->getAttributes());
    }

    public function test_runtime_hooks_receive_sanitized_passwords_and_uploads(): void
    {
        $record = ResourceFormPersisterRecord::query()->create([
            'name' => 'Original',
            'password' => 'existing-hash',
            'receipt' => 'receipts/original.pdf',
        ]);

        (new ResourceFormPersister(new RuntimeHookDispatcher()))
            ->update($this->resourceClass(), $record, [
                'name' => 'Updated',
                'password' => 'new-secret',
                'password_confirmation' => 'new-secret',
                'receipt' => $this->uploadedFile(),
            ]);

        $beforeContext = $this->hookContext('resource.update.before');
        $afterContext = $this->hookContext('resource.update.after');

        self::assertSame('[redacted]', $beforeContext['payload']['password']);
        self::assertSame('[redacted]', $beforeContext['payload']['password_confirmation']);
        self::assertSame(true, $beforeContext['payload']['receipt']['uploaded']);
        self::assertSame('application/pdf', $beforeContext['payload']['receipt']['mime_type']);
        self::assertArrayHasKey('size', $beforeContext['payload']['receipt']);
        self::assertArrayNotHasKey('path', $beforeContext['payload']['receipt']);
        self::assertArrayNotHasKey('client_original_name', $beforeContext['payload']['receipt']);

        self::assertSame('[redacted]', $afterContext['payload']['password']);
        self::assertArrayNotHasKey('password_confirmation', $afterContext['payload']);
        self::assertArrayNotHasKey('receipt', $afterContext['payload']);

        self::assertSame('[redacted]', $beforeContext['record']['password']);
        self::assertSame(['stored' => true], $beforeContext['record']['receipt']);
        self::assertSame('[redacted]', $afterContext['record']['password']);
        self::assertSame(['stored' => true], $afterContext['record']['receipt']);
    }

    public function test_update_can_remove_existing_file_references(): void
    {
        $record = ResourceFormPersisterRecord::query()->create([
            'name' => 'Original',
            'password' => 'existing-hash',
            'receipt' => 'receipts/original.pdf',
        ]);

        $updated = (new ResourceFormPersister(new RuntimeHookDispatcher()))
            ->update($this->resourceClass(), $record, [
                'receipt__remove' => true,
            ]);

        self::assertNull($updated->getAttribute('receipt'));
    }

    public function test_belongs_to_values_are_persisted_as_scalar_foreign_keys_with_empty_values_normalized_to_null(): void
    {
        $record = ResourceFormPersisterRecord::query()->create([
            'name' => 'Original',
            'category_id' => 5,
        ]);

        $updated = (new ResourceFormPersister(new RuntimeHookDispatcher()))
            ->update($this->belongsToResourceClass(), $record, [
                'category_id' => '',
            ]);

        self::assertNull($updated->getAttribute('category_id'));

        $created = (new ResourceFormPersister(new RuntimeHookDispatcher()))
            ->create($this->belongsToResourceClass(), [
                'name' => 'Created',
                'category_id' => 7,
            ]);

        self::assertSame(7, $created->getAttribute('category_id'));
    }

    public function test_belongs_to_relationship_key_is_persisted_to_resolved_foreign_key(): void
    {
        $record = ResourceFormPersisterRecord::query()->create([
            'name' => 'Original',
            'category_id' => 5,
        ]);

        $updated = (new ResourceFormPersister(new RuntimeHookDispatcher()))
            ->update($this->relationshipKeyBelongsToResourceClass(), $record, [
                'category' => 9,
            ]);

        self::assertSame(9, $updated->getAttribute('category_id'));
        self::assertArrayNotHasKey('category', $updated->getAttributes());

        $created = (new ResourceFormPersister(new RuntimeHookDispatcher()))
            ->create($this->relationshipKeyBelongsToResourceClass(), [
                'name' => 'Created',
                'category' => 7,
            ]);

        self::assertSame(7, $created->getAttribute('category_id'));
        self::assertArrayNotHasKey('category', $created->getAttributes());
    }

    public function test_belongs_to_many_values_are_removed_from_scalar_attributes_and_synced(): void
    {
        $this->createBelongsToManyTables();
        $red = $this->createTag('Red', 'red');
        $blue = $this->createTag('Blue', 'blue');
        $green = $this->createTag('Green', 'green');

        $created = (new ResourceFormPersister(new RuntimeHookDispatcher()))
            ->create(ResourceFormPersisterBelongsToManyProductResource::class, [
                'name' => 'Product',
                'tags' => [$red->getKey(), (string) $blue->getKey(), $red->getKey()],
            ]);

        self::assertInstanceOf(BelongsToManyProduct::class, $created);
        self::assertSame('Product', $created->getAttribute('name'));
        self::assertArrayNotHasKey('tags', $created->getAttributes());
        self::assertSame([(int) $red->getKey(), (int) $blue->getKey()], $this->attachedTagIds($created));
        self::assertSame([(int) $red->getKey(), (int) $blue->getKey()], ResourceFormPersisterBelongsToManyProductResource::$afterSaveTagIds);
        self::assertNotContains('tags', ResourceFormPersisterBelongsToManyProductResource::$afterSaveDataKeys);

        $updated = (new ResourceFormPersister(new RuntimeHookDispatcher()))
            ->update(ResourceFormPersisterBelongsToManyProductResource::class, $created, [
                'name' => 'Updated product',
                'tags' => [$green->getKey()],
            ]);

        self::assertSame('Updated product', $updated->getAttribute('name'));
        self::assertSame([(int) $green->getKey()], $this->attachedTagIds($created));
    }

    public function test_belongs_to_many_omitted_field_keeps_membership_and_empty_array_detaches(): void
    {
        $this->createBelongsToManyTables();
        $red = $this->createTag('Red', 'red');
        $blue = $this->createTag('Blue', 'blue');
        $product = BelongsToManyProduct::query()->create(['name' => 'Product']);
        $product->tags()->sync([$red->getKey(), $blue->getKey()]);

        (new ResourceFormPersister(new RuntimeHookDispatcher()))
            ->update(ResourceFormPersisterBelongsToManyProductResource::class, $product, [
                'name' => 'Renamed',
            ]);

        self::assertSame('Renamed', $product->refresh()->getAttribute('name'));
        self::assertSame([(int) $red->getKey(), (int) $blue->getKey()], $this->attachedTagIds($product));

        (new ResourceFormPersister(new RuntimeHookDispatcher()))
            ->update(ResourceFormPersisterBelongsToManyProductResource::class, $product, [
                'tags' => [],
            ]);

        self::assertSame([], $this->attachedTagIds($product));
    }

    public function test_belongs_to_many_accepts_scalar_value_as_single_selection(): void
    {
        $this->createBelongsToManyTables();
        $red = $this->createTag('Red', 'red');

        $created = (new ResourceFormPersister(new RuntimeHookDispatcher()))
            ->create(ResourceFormPersisterBelongsToManyProductResource::class, [
                'name' => 'Product',
                'tags' => (string) $red->getKey(),
            ]);

        self::assertInstanceOf(BelongsToManyProduct::class, $created);
        self::assertSame([(int) $red->getKey()], $this->attachedTagIds($created));
    }

    public function test_belongs_to_many_rejects_invalid_submitted_items(): void
    {
        $this->createBelongsToManyTables();

        $this->expectException(ValidationException::class);

        (new ResourceFormPersister(new RuntimeHookDispatcher()))
            ->create(ResourceFormPersisterBelongsToManyProductResource::class, [
                'name' => 'Product',
                'tags' => [['id' => 1]],
            ]);
    }

    public function test_belongs_to_many_enforces_max_items_before_sync(): void
    {
        $this->createBelongsToManyTables();
        $red = $this->createTag('Red', 'red');
        $blue = $this->createTag('Blue', 'blue');

        $this->expectException(ValidationException::class);

        (new ResourceFormPersister(new RuntimeHookDispatcher()))
            ->create(ResourceFormPersisterLimitedBelongsToManyProductResource::class, [
                'name' => 'Product',
                'tags' => [$red->getKey(), $blue->getKey()],
            ]);
    }

    public function test_belongs_to_many_sync_respects_query_modifier(): void
    {
        $this->createBelongsToManyTables();
        $this->createTag('Visible', 'visible');
        $hidden = $this->createTag('Hidden', 'hidden');
        $product = BelongsToManyProduct::query()->create(['name' => 'Original']);

        $this->expectException(ValidationException::class);

        (new ResourceFormPersister(new RuntimeHookDispatcher()))
            ->update(ResourceFormPersisterFilteredBelongsToManyProductResource::class, $product, [
                'name' => 'Changed',
                'tags' => [$hidden->getKey()],
            ]);
    }

    public function test_belongs_to_many_persistence_rolls_back_when_after_save_fails(): void
    {
        $this->createBelongsToManyTables();
        $red = $this->createTag('Red', 'red');
        $blue = $this->createTag('Blue', 'blue');
        $product = BelongsToManyProduct::query()->create(['name' => 'Original']);
        $product->tags()->sync([$red->getKey()]);

        try {
            (new ResourceFormPersister(new RuntimeHookDispatcher()))
                ->update(ResourceFormPersisterFailingAfterSaveBelongsToManyProductResource::class, $product, [
                    'name' => 'Changed',
                    'tags' => [$blue->getKey()],
                ]);

            self::fail('Expected after-save failure to rollback the transaction.');
        } catch (\RuntimeException $exception) {
            self::assertSame('after-save failure', $exception->getMessage());
        }

        self::assertSame('Original', $product->refresh()->getAttribute('name'));
        self::assertSame([(int) $red->getKey()], $this->attachedTagIds($product));
    }

    /**
     * @return class-string<Resource>
     */
    private function resourceClass(): string
    {
        return get_class(new class() extends Resource
        {
            public static function model(): string
            {
                return ResourceFormPersisterRecord::class;
            }

            public static function form(FormContract $form): FormContract
            {
                return $form->schema([
                    TextInput::make('name')->label('Name'),
                    PasswordInput::make('password')->label('Password')->confirmed(),
                    FileUpload::make('receipt')->label('Receipt'),
                ]);
            }

            public static function runtimeHooks(): array
            {
                return [new ResourceFormPersisterCapturingHook()];
            }
        });
    }

    /**
     * @return class-string<Resource>
     */
    private function belongsToResourceClass(): string
    {
        return get_class(new class() extends Resource
        {
            public static function model(): string
            {
                return ResourceFormPersisterRecord::class;
            }

            public static function form(FormContract $form): FormContract
            {
                return $form->schema([
                    TextInput::make('name')->label('Name'),
                    BelongsTo::make('category_id', 'Category'),
                ]);
            }
        });
    }

    /**
     * @return class-string<Resource>
     */
    private function relationshipKeyBelongsToResourceClass(): string
    {
        return get_class(new class() extends Resource
        {
            public static function model(): string
            {
                return ResourceFormPersisterRecord::class;
            }

            public static function form(FormContract $form): FormContract
            {
                return $form->schema([
                    TextInput::make('name')->label('Name'),
                    BelongsTo::make('category', 'Category'),
                ]);
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function hookContext(string $hook): array
    {
        foreach (ResourceFormPersisterCapturingHook::$calls as $call) {
            if ($call['hook'] === $hook) {
                return $call['context'];
            }
        }

        self::fail("Hook [$hook] was not dispatched.");
    }

    private function uploadedFile(): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'flashboard-upload-');

        if ($path === false) {
            throw new \RuntimeException('Could not create a temporary upload fixture.');
        }

        file_put_contents($path, 'fixture');

        return new UploadedFile($path, 'receipt.pdf', 'application/pdf', null, true);
    }

    private function createBelongsToManyTables(): void
    {
        $this->database->schema()->create('belongs_to_many_products', static function (\Illuminate\Database\Schema\Blueprint $table): void {
            $table->increments('id');
            $table->string('name')->nullable();
        });
        $this->database->schema()->create('belongs_to_many_tags', static function (\Illuminate\Database\Schema\Blueprint $table): void {
            $table->increments('id');
            $table->string('name');
            $table->string('slug');
        });
        $this->database->schema()->create('belongs_to_many_product_tag', static function (\Illuminate\Database\Schema\Blueprint $table): void {
            $table->unsignedInteger('product_id');
            $table->unsignedInteger('tag_id');
        });
    }

    private function createTag(string $name, string $slug): BelongsToManyTag
    {
        $tag = BelongsToManyTag::query()->create([
            'name' => $name,
            'slug' => $slug,
        ]);

        self::assertInstanceOf(BelongsToManyTag::class, $tag);

        return $tag;
    }

    /**
     * @return list<int>
     */
    private function attachedTagIds(BelongsToManyProduct $product): array
    {
        return $product
            ->tags()
            ->orderBy('belongs_to_many_tags.id')
            ->pluck('belongs_to_many_tags.id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
    }
}

final class ResourceFormPersisterRecord extends \Illuminate\Database\Eloquent\Model
{
    public $timestamps = false;

    protected $guarded = [];

    public function category(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(BelongsToCategory::class, 'category_id');
    }
}

final class ResourceFormPersisterCapturingHook implements RuntimeHookContract
{
    /**
     * @var list<array{hook: string, context: array<string, mixed>}>
     */
    public static array $calls = [];

    public function handle(string $hook, array $context = []): void
    {
        self::$calls[] = [
            'hook' => $hook,
            'context' => $context,
        ];
    }
}

final class ResourceFormPersisterBelongsToManyProductResource extends Resource
{
    /**
     * @var list<int>
     */
    public static array $afterSaveTagIds = [];

    /**
     * @var list<string>
     */
    public static array $afterSaveDataKeys = [];

    public static function model(): string
    {
        return BelongsToManyProduct::class;
    }

    public static function form(FormContract $form): FormContract
    {
        return $form->schema([
            TextInput::make('name', 'Name'),
            BelongsToMany::make('tags', 'Tags')
                ->resource(BelongsToManyTagResource::class)
                ->titleAttribute('name')
                ->searchable(['name', 'slug']),
        ]);
    }

    public static function afterSave(\Illuminate\Database\Eloquent\Model $record, array $data): void
    {
        if (!$record instanceof BelongsToManyProduct) {
            return;
        }

        self::$afterSaveTagIds = $record
            ->tags()
            ->orderBy('belongs_to_many_tags.id')
            ->pluck('belongs_to_many_tags.id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
        self::$afterSaveDataKeys = array_keys($data);
    }
}

final class ResourceFormPersisterLimitedBelongsToManyProductResource extends Resource
{
    public static function model(): string
    {
        return BelongsToManyProduct::class;
    }

    public static function form(FormContract $form): FormContract
    {
        return $form->schema([
            TextInput::make('name', 'Name'),
            BelongsToMany::make('tags', 'Tags')
                ->resource(BelongsToManyTagResource::class)
                ->maxItems(1),
        ]);
    }
}

final class ResourceFormPersisterFilteredBelongsToManyProductResource extends Resource
{
    public static function model(): string
    {
        return BelongsToManyProduct::class;
    }

    public static function form(FormContract $form): FormContract
    {
        return $form->schema([
            TextInput::make('name', 'Name'),
            BelongsToMany::make('tags', 'Tags')
                ->resource(BelongsToManyTagResource::class)
                ->modifyQueryUsing(static fn (Builder $query): Builder => $query->where('slug', '!=', 'hidden')),
        ]);
    }
}

final class ResourceFormPersisterFailingAfterSaveBelongsToManyProductResource extends Resource
{
    public static function model(): string
    {
        return BelongsToManyProduct::class;
    }

    public static function form(FormContract $form): FormContract
    {
        return $form->schema([
            TextInput::make('name', 'Name'),
            BelongsToMany::make('tags', 'Tags')
                ->resource(BelongsToManyTagResource::class),
        ]);
    }

    public static function afterSave(\Illuminate\Database\Eloquent\Model $record, array $data): void
    {
        throw new \RuntimeException('after-save failure');
    }
}
