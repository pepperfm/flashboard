<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Tests\Feature;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Factory;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Builder;
use Pepperfm\Flashboard\Contracts\Forms\FormContract;
use Pepperfm\Flashboard\Contracts\Resources\Resource;
use Pepperfm\Flashboard\Core\Authorization\Visibility\ScreenAccessResolver;
use Pepperfm\Flashboard\Core\Extensions\ExtensionRegistry;
use Pepperfm\Flashboard\Core\Forms\Fields\BelongsTo;
use Pepperfm\Flashboard\Core\Forms\Fields\Checkbox;
use Pepperfm\Flashboard\Core\Forms\Fields\DateInput;
use Pepperfm\Flashboard\Core\Forms\Fields\FileUpload;
use Pepperfm\Flashboard\Core\Forms\Fields\PasswordInput;
use Pepperfm\Flashboard\Core\Forms\Fields\RichText;
use Pepperfm\Flashboard\Core\Forms\Fields\TextInput;
use Pepperfm\Flashboard\Core\Forms\Fields\Toggle;
use Pepperfm\Flashboard\Core\Forms\Layout\Section;
use Pepperfm\Flashboard\Core\Forms\Layout\Tab;
use Pepperfm\Flashboard\Core\Forms\Layout\Tabs;
use Pepperfm\Flashboard\Core\Registry\ResourceRegistry;
use Pepperfm\Flashboard\Core\Resources\ResourceSurfaceResolver;
use Pepperfm\Flashboard\Core\Runtime\Assemblers\FormPayloadAssembler;
use Pepperfm\Flashboard\Integration\Laravel\Auth\PanelAuthenticator;
use Pepperfm\Flashboard\Integration\Laravel\Auth\PolicyBridge;
use Pepperfm\Flashboard\Integration\Laravel\DataSources\ResourceFormDataSource;
use Pepperfm\Flashboard\Tests\Fixtures\Models\BelongsToCategory;
use Pepperfm\Flashboard\Tests\Fixtures\Models\BelongsToProduct;
use Pepperfm\Flashboard\Tests\Fixtures\Resources\BelongsToCategoryResource;
use Pepperfm\Flashboard\Tests\Fixtures\Resources\BelongsToProductResource;
use Pepperfm\Flashboard\Tests\TestCase;

final class ResourceFormDataSourceTest extends TestCase
{
    public function test_hidden_fields_are_pruned_from_the_schema_tree_and_flattened_fields(): void
    {
        $this->fakeUrlGenerator();
        $this->fakeGateForFieldVisibility();

        $payload = $this->makeDataSource()->resolve($this->visibilityAwareResourceClass());

        self::assertSame(['email', 'is_active'], array_column($payload['fields'], 'key'));
        self::assertSame(['email'], array_column($payload['schema'][0]['schema'], 'key'));
        self::assertSame(['is_active'], array_column($payload['schema'][1]['tabs'][0]['schema'], 'key'));
    }

    public function test_boolean_fields_default_to_false_without_overriding_explicit_defaults_or_record_state(): void
    {
        $this->fakeUrlGenerator();
        $this->fakeGateForFieldVisibility();

        $resourceClass = $this->booleanDefaultsResourceClass();
        $createPayload = $this->makeDataSource()->resolve($resourceClass);

        self::assertFalse($createPayload['state']['is_featured']);
        self::assertTrue($createPayload['state']['is_active']);
        self::assertNull($createPayload['state']['name']);

        $record = new class() extends \Illuminate\Database\Eloquent\Model
        {
            protected $guarded = [];
        };
        $record->forceFill([
            'is_featured' => true,
            'is_active' => false,
            'name' => 'Published product',
        ]);
        $record->exists = true;

        $editPayload = $this->makeDataSource()->resolve($resourceClass, $record);

        self::assertTrue($editPayload['state']['is_featured']);
        self::assertFalse($editPayload['state']['is_active']);
        self::assertSame('Published product', $editPayload['state']['name']);
    }

    public function test_text_field_record_state_is_normalized_to_browser_form_strings(): void
    {
        $this->fakeUrlGenerator();
        $this->fakeGateForFieldVisibility();

        $resourceClass = $this->stringStateResourceClass();
        $createPayload = $this->makeDataSource()->resolve($resourceClass);

        self::assertSame(['name', 'is_active'], array_column($createPayload['fields'], 'key'));
        self::assertArrayNotHasKey('id', $createPayload['rules']);
        self::assertArrayNotHasKey('id', $createPayload['state']);
        self::assertNull($createPayload['state']['name']);
        self::assertFalse($createPayload['state']['is_active']);

        $record = new class() extends \Illuminate\Database\Eloquent\Model
        {
            protected $guarded = [];
        };
        $record->forceFill([
            'id' => 2,
            'is_active' => true,
            'name' => 123,
        ]);
        $record->exists = true;

        $payload = $this->makeDataSource()->resolve($resourceClass, $record);

        self::assertSame(['id', 'name', 'is_active'], array_column($payload['fields'], 'key'));
        self::assertSame('2', $payload['state']['id']);
        self::assertSame('123', $payload['state']['name']);
        self::assertTrue($payload['state']['is_active']);
    }

    public function test_advanced_edit_state_hides_passwords_files_and_normalizes_dates_and_rich_text(): void
    {
        $this->fakeUrlGenerator();
        $this->fakeGateForFieldVisibility();

        $record = new class() extends \Illuminate\Database\Eloquent\Model
        {
            protected $guarded = [];
        };
        $record->forceFill([
            'id' => 7,
            'published_on' => new \DateTimeImmutable('2026-05-28 13:45:00'),
            'password' => 'hashed-secret',
            'receipt' => 'receipts/order.pdf',
            'attachments' => [
                [
                    'name' => 'Invoice',
                    'path' => 'docs/invoice.pdf',
                    'url' => 'https://example.test/invoice.pdf',
                ],
                'docs/terms.pdf',
            ],
            'body' => 123,
            'content_json' => '{"type":"doc","content":[]}',
        ]);
        $record->exists = true;

        $payload = $this->makeDataSource()->resolve($this->advancedStateResourceClass(), $record);
        $fields = array_column($payload['fields'], null, 'key');

        self::assertSame('2026-05-28', $payload['state']['published_on']);
        self::assertNull($payload['state']['password']);
        self::assertNull($payload['state']['receipt']);
        self::assertNull($payload['state']['attachments']);
        self::assertSame('123', $payload['state']['body']);
        self::assertSame(['type' => 'doc', 'content' => []], $payload['state']['content_json']);

        self::assertSame(
            [
                [
                    'name' => 'order.pdf',
                    'path' => 'receipts/order.pdf',
                    'url' => null,
                ],
            ],
            $fields['receipt']['existing_files'],
        );
        self::assertSame(
            [
                [
                    'name' => 'Invoice',
                    'path' => 'docs/invoice.pdf',
                    'url' => 'https://example.test/invoice.pdf',
                ],
                [
                    'name' => 'terms.pdf',
                    'path' => 'docs/terms.pdf',
                    'url' => null,
                ],
            ],
            $fields['attachments']['existing_files'],
        );
    }

    public function test_belongs_to_fields_are_enriched_with_relation_metadata_and_selected_option(): void
    {
        $this->fakeUrlGenerator();
        $this->fakeGateForFieldVisibility();
        $this->createBelongsToTables();

        $category = BelongsToCategory::query()->create([
            'name' => 'Hardware',
            'slug' => 'hardware',
        ]);
        $record = BelongsToProduct::query()->create([
            'name' => 'Keyboard',
            'category_id' => $category->getKey(),
        ]);
        $payload = $this->makeDataSource($this->belongsToRegistry())
            ->resolve(BelongsToProductResource::class, $record);
        $fields = array_column($payload['fields'], null, 'key');
        $field = $fields['category_id'];

        self::assertSame($category->getKey(), $payload['state']['category_id']);
        self::assertSame('belongs_to', $field['type']);
        self::assertSame('relation_select', $field['renderer']);
        self::assertSame('category', $field['relationship']);
        self::assertSame(BelongsToCategory::class, $field['related_model']);
        self::assertSame(BelongsToCategoryResource::class, $field['related_resource']);
        self::assertSame('belongs_to_categories', $field['related_table']);
        self::assertSame('/flashboard.resources.belongs_to_product.relations.options', $field['options_url']);
        self::assertSame(['detail' => true], $field['related_routes']);
        self::assertSame([
            'label' => 'Hardware',
            'value' => $category->getKey(),
            'url' => '/flashboard.resources.belongs_to_category.detail',
        ], $field['selected_option']);
    }

    public function test_belongs_to_selected_option_respects_field_query_modifier(): void
    {
        $this->fakeUrlGenerator();
        $this->fakeGateForFieldVisibility();
        $this->createBelongsToTables();

        $category = BelongsToCategory::query()->create([
            'name' => 'Hidden',
            'slug' => 'hidden',
        ]);
        $record = BelongsToProduct::query()->create([
            'name' => 'Desk',
            'category_id' => $category->getKey(),
        ]);
        $payload = $this->makeDataSource($this->belongsToRegistry())
            ->resolve($this->queryModifiedBelongsToResourceClass(), $record);
        $fields = array_column($payload['fields'], null, 'key');

        self::assertNull($fields['category_id']['selected_option']);
    }

    public function test_belongs_to_metadata_is_enriched_recursively_in_sections_and_tabs(): void
    {
        $this->fakeUrlGenerator();
        $this->fakeGateForFieldVisibility();

        $payload = $this->makeDataSource($this->belongsToRegistry())
            ->resolve($this->nestedBelongsToResourceClass());

        self::assertSame('relation_select', $payload['schema'][0]['schema'][0]['renderer']);
        self::assertSame('belongs_to_categories', $payload['schema'][0]['schema'][0]['related_table']);
        self::assertSame('relation_select', $payload['schema'][1]['tabs'][0]['schema'][0]['renderer']);
        self::assertSame('belongs_to_categories', $payload['schema'][1]['tabs'][0]['schema'][0]['related_table']);
    }

    public function test_belongs_to_related_routes_and_selected_option_are_hidden_when_related_resource_is_inaccessible(): void
    {
        $this->fakeUrlGenerator();
        $this->fakeGateForFieldVisibility();
        $this->createBelongsToTables();

        $category = BelongsToCategory::query()->create([
            'name' => 'Hidden',
            'slug' => 'hidden',
        ]);
        $record = BelongsToProduct::query()->create([
            'name' => 'Desk',
            'category_id' => $category->getKey(),
        ]);
        $payload = $this->makeDataSource($this->belongsToRegistry())
            ->resolve($this->inaccessibleRelatedResourceClass(), $record);
        $fields = array_column($payload['fields'], null, 'key');

        self::assertArrayNotHasKey('related_routes', $fields['category_id']);
        self::assertNull($fields['category_id']['selected_option']);
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

    private function fakeGateForFieldVisibility(): void
    {
        $this->app->instance(Gate::class, new class() implements Gate
        {
            public function has($ability): bool
            {
                return true;
            }

            public function define($ability, $callback): static
            {
                return $this;
            }

            public function resource($name, $class, ?array $abilities = null): static
            {
                return $this;
            }

            public function policy($class, $policy): static
            {
                return $this;
            }

            public function before(callable $callback): static
            {
                return $this;
            }

            public function after(callable $callback): static
            {
                return $this;
            }

            public function allows($ability, $arguments = []): bool
            {
                return $ability !== 'view-hidden';
            }

            public function denies($ability, $arguments = []): bool
            {
                return !$this->allows($ability, $arguments);
            }

            public function check($abilities, $arguments = []): bool
            {
                foreach ((array) $abilities as $ability) {
                    if (!$this->allows($ability, $arguments)) {
                        return false;
                    }
                }

                return true;
            }

            public function any($abilities, $arguments = []): bool
            {
                foreach ((array) $abilities as $ability) {
                    if ($this->allows($ability, $arguments)) {
                        return true;
                    }
                }

                return false;
            }

            public function authorize($ability, $arguments = []): \Illuminate\Auth\Access\Response
            {
                return $this->inspect($ability, $arguments);
            }

            public function inspect($ability, $arguments = []): \Illuminate\Auth\Access\Response
            {
                return new \Illuminate\Auth\Access\Response($this->allows($ability, $arguments));
            }

            public function raw($ability, $arguments = []): bool
            {
                return $this->allows($ability, $arguments);
            }

            public function getPolicyFor($class): mixed
            {
                return null;
            }

            public function forUser($user): static
            {
                return $this;
            }

            public function abilities(): array
            {
                return [];
            }
        });
    }

    private function makeDataSource(?ResourceRegistry $resourceRegistry = null): ResourceFormDataSource
    {
        return new ResourceFormDataSource(
            new FormPayloadAssembler(),
            new ScreenAccessResolver(new PolicyBridge()),
            new PanelAuthenticator(new class() implements Factory
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
            }),
            new ExtensionRegistry(),
            new ResourceSurfaceResolver(new ScreenAccessResolver(new PolicyBridge())),
            $resourceRegistry ?? new ResourceRegistry(),
        );
    }

    private function createBelongsToTables(): void
    {
        $database = new Capsule();
        $database->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $database->setAsGlobal();
        $database->bootEloquent();
        $database->schema()->create('belongs_to_categories', static function (\Illuminate\Database\Schema\Blueprint $table): void {
            $table->increments('id');
            $table->string('name');
            $table->string('slug')->nullable();
        });
        $database->schema()->create('belongs_to_products', static function (\Illuminate\Database\Schema\Blueprint $table): void {
            $table->increments('id');
            $table->string('name');
            $table->unsignedInteger('category_id')->nullable();
        });
    }

    private function belongsToRegistry(): ResourceRegistry
    {
        $registry = new ResourceRegistry();
        $registry->register(BelongsToCategoryResource::class);
        $registry->register(BelongsToProductResource::class);

        return $registry;
    }

    /**
     * @return class-string<Resource>
     */
    private function visibilityAwareResourceClass(): string
    {
        return get_class(new class() extends Resource
        {
            public static function model(): string
            {
                return \Illuminate\Database\Eloquent\Model::class;
            }

            public static function policy(): string
            {
                return \stdClass::class;
            }

            public static function fieldAbilityMap(): array
            {
                return [
                    'secret' => 'view-hidden',
                    'secret_toggle' => 'view-hidden',
                ];
            }

            public static function form(FormContract $form): FormContract
            {
                return $form->schema([
                    Section::make('main')->label('Main')->schema([
                        TextInput::make('email')->label('Email'),
                        TextInput::make('secret')->label('Secret'),
                    ]),
                    Tabs::make('settings')->tabs([
                        Tab::make('access')->label('Access')->schema([
                            TextInput::make('is_active')->label('Is active'),
                            TextInput::make('secret_toggle')->label('Hidden Toggle'),
                        ]),
                    ]),
                ]);
            }
        });
    }

    /**
     * @return class-string<Resource>
     */
    private function booleanDefaultsResourceClass(): string
    {
        return get_class(new class() extends Resource
        {
            public static function model(): string
            {
                return ResourceFormDataSourceGeneratedKeyModel::class;
            }

            public static function form(FormContract $form): FormContract
            {
                return $form
                    ->schema([
                        TextInput::make('name')->label('Name'),
                        Checkbox::make('is_featured')->label('Featured'),
                        Toggle::make('is_active')->label('Is active'),
                    ])
                    ->defaults([
                        'is_active' => true,
                    ]);
            }
        });
    }

    /**
     * @return class-string<Resource>
     */
    private function stringStateResourceClass(): string
    {
        return get_class(new class() extends Resource
        {
            public static function model(): string
            {
                return ResourceFormDataSourceGeneratedKeyModel::class;
            }

            public static function form(FormContract $form): FormContract
            {
                return $form->schema([
                    TextInput::make('id')->label('ID'),
                    TextInput::make('name')->label('Name'),
                    Toggle::make('is_active')->label('Is active'),
                ]);
            }
        });
    }

    /**
     * @return class-string<Resource>
     */
    private function advancedStateResourceClass(): string
    {
        return get_class(new class() extends Resource
        {
            public static function model(): string
            {
                return ResourceFormDataSourceGeneratedKeyModel::class;
            }

            public static function form(FormContract $form): FormContract
            {
                return $form->schema([
                    DateInput::make('published_on')->label('Published on'),
                    PasswordInput::make('password')->label('Password'),
                    FileUpload::make('receipt')->label('Receipt'),
                    FileUpload::make('attachments')->label('Attachments')->multiple(),
                    RichText::make('body')->label('Body'),
                    RichText::make('content_json')->label('Content JSON')->json(),
                ]);
            }
        });
    }

    /**
     * @return class-string<Resource>
     */
    private function nestedBelongsToResourceClass(): string
    {
        return get_class(new class() extends Resource
        {
            public static function model(): string
            {
                return BelongsToProduct::class;
            }

            public static function form(FormContract $form): FormContract
            {
                return $form->schema([
                    Section::make('relationships')->label('Relationships')->schema([
                        BelongsTo::make('category_id', 'Category')->resource(BelongsToCategoryResource::class),
                    ]),
                    Tabs::make('more')->tabs([
                        Tab::make('secondary')->label('Secondary')->schema([
                            BelongsTo::make('backup_category_id', 'Backup category', 'category')
                                ->resource(BelongsToCategoryResource::class),
                        ]),
                    ]),
                ]);
            }
        });
    }

    /**
     * @return class-string<Resource>
     */
    private function inaccessibleRelatedResourceClass(): string
    {
        return get_class(new class() extends Resource
        {
            public static function model(): string
            {
                return BelongsToProduct::class;
            }

            public static function form(FormContract $form): FormContract
            {
                return $form->schema([
                    BelongsTo::make('category_id', 'Category')
                        ->resource(InaccessibleBelongsToCategoryResource::class)
                        ->titleAttribute('name'),
                ]);
            }
        });
    }

    /**
     * @return class-string<Resource>
     */
    private function queryModifiedBelongsToResourceClass(): string
    {
        return get_class(new class() extends Resource
        {
            public static function model(): string
            {
                return BelongsToProduct::class;
            }

            public static function form(FormContract $form): FormContract
            {
                return $form->schema([
                    BelongsTo::make('category_id', 'Category')
                        ->resource(BelongsToCategoryResource::class)
                        ->titleAttribute('name')
                        ->modifyQueryUsing(static fn (Builder $query): Builder => $query->where('slug', '!=', 'hidden')),
                ]);
            }
        });
    }
}

final class ResourceFormDataSourceGeneratedKeyModel extends \Illuminate\Database\Eloquent\Model
{
    protected $guarded = [];
}

final class InaccessibleBelongsToCategoryResource extends Resource
{
    public static function model(): string
    {
        return BelongsToCategory::class;
    }

    public static function canAccess(?\Illuminate\Contracts\Auth\Authenticatable $user = null): bool
    {
        return false;
    }

    public static function detail(\Pepperfm\Flashboard\Contracts\Detail\DetailContract $detail): \Pepperfm\Flashboard\Contracts\Detail\DetailContract
    {
        return $detail->entries([
            \Pepperfm\Flashboard\Core\Detail\Entries\TextEntry::make('name', 'Name'),
        ]);
    }
}
