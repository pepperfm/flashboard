<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Tests\Feature;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Http\UploadedFile;
use Pepperfm\Flashboard\Contracts\Extensions\RuntimeHookContract;
use Pepperfm\Flashboard\Contracts\Forms\FormContract;
use Pepperfm\Flashboard\Contracts\Resources\Resource;
use Pepperfm\Flashboard\Core\Forms\Fields\BelongsTo;
use Pepperfm\Flashboard\Core\Forms\Fields\FileUpload;
use Pepperfm\Flashboard\Core\Forms\Fields\PasswordInput;
use Pepperfm\Flashboard\Core\Forms\Fields\TextInput;
use Pepperfm\Flashboard\Core\Hooks\RuntimeHookDispatcher;
use Pepperfm\Flashboard\Integration\Laravel\Persistence\ResourceFormPersister;
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
        ResourceFormPersisterCapturingHook::$calls = [];
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
}

final class ResourceFormPersisterRecord extends \Illuminate\Database\Eloquent\Model
{
    public $timestamps = false;

    protected $guarded = [];
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
