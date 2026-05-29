<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Tests\Feature;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Factory;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Builder;
use Pepperfm\Flashboard\Contracts\Extensions\QueryExtensionContract;
use Pepperfm\Flashboard\Contracts\Forms\FormContract;
use Pepperfm\Flashboard\Contracts\Resources\Resource;
use Pepperfm\Flashboard\Core\Authorization\Visibility\ScreenAccessResolver;
use Pepperfm\Flashboard\Core\Extensions\ExtensionRegistry;
use Pepperfm\Flashboard\Core\Forms\Fields\BelongsTo;
use Pepperfm\Flashboard\Core\Forms\Fields\TextInput;
use Pepperfm\Flashboard\Core\Registry\ResourceRegistry;
use Pepperfm\Flashboard\Core\Resources\ResourceSurfaceResolver;
use Pepperfm\Flashboard\Integration\Laravel\Auth\PanelAuthenticator;
use Pepperfm\Flashboard\Integration\Laravel\Auth\PolicyBridge;
use Pepperfm\Flashboard\Integration\Laravel\DataSources\ResourceRelationOptionsDataSource;
use Pepperfm\Flashboard\Integration\Laravel\Relations\RelationQueryModifier;
use Pepperfm\Flashboard\Tests\Fixtures\Models\BelongsToCategory;
use Pepperfm\Flashboard\Tests\Fixtures\Models\BelongsToProduct;
use Pepperfm\Flashboard\Tests\Fixtures\Resources\BelongsToCategoryResource;
use Pepperfm\Flashboard\Tests\Fixtures\Resources\BelongsToProductResource;
use Pepperfm\Flashboard\Tests\TestCase;

final class ResourceRelationOptionsDataSourceTest extends TestCase
{
    private Capsule $database;

    private BelongsToCategory $hardware;

    private BelongsToCategory $software;

    private BelongsToCategory $hidden;

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
        $this->database->schema()->create('belongs_to_categories', static function (\Illuminate\Database\Schema\Blueprint $table): void {
            $table->increments('id');
            $table->string('name');
            $table->string('slug')->nullable();
            $table->boolean('visible')->default(true);
        });
        $this->database->schema()->create('belongs_to_products', static function (\Illuminate\Database\Schema\Blueprint $table): void {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->unsignedInteger('category_id')->nullable();
        });

        $this->hardware = BelongsToCategory::query()->create([
            'name' => 'Hardware',
            'slug' => 'hardware',
            'visible' => true,
        ]);
        $this->software = BelongsToCategory::query()->create([
            'name' => 'Software',
            'slug' => 'software',
            'visible' => true,
        ]);
        $this->hidden = BelongsToCategory::query()->create([
            'name' => 'Hidden',
            'slug' => 'hidden',
            'visible' => false,
        ]);
    }

    public function test_relation_options_are_searchable_paginated_and_hydrate_selected_values(): void
    {
        $payload = $this->dataSource($this->defaultRegistry())->resolve(
            BelongsToProductResource::class,
            'category_id',
            \Illuminate\Http\Request::create('/', 'GET', [
                'page' => 1,
                'per_page' => 1,
                'selected' => $this->software->getKey(),
            ]),
        );

        self::assertSame([
            [
                'label' => 'Software',
                'value' => $this->software->getKey(),
                'url' => '/flashboard.resources.belongs_to_category.detail',
            ],
            [
                'label' => 'Hardware',
                'value' => $this->hardware->getKey(),
                'url' => '/flashboard.resources.belongs_to_category.detail',
            ],
        ], $payload['items']);
        self::assertTrue($payload['meta']['has_more']);
        self::assertSame(2, $payload['meta']['next_page']);

        $searchedPayload = $this->dataSource($this->defaultRegistry())->resolve(
            BelongsToProductResource::class,
            'category_id',
            \Illuminate\Http\Request::create('/', 'GET', [
                'search' => 'soft',
            ]),
        );

        self::assertSame([
            [
                'label' => 'Software',
                'value' => $this->software->getKey(),
                'url' => '/flashboard.resources.belongs_to_category.detail',
            ],
        ], $searchedPayload['items']);
        self::assertFalse($searchedPayload['meta']['has_more']);
        self::assertNull($searchedPayload['meta']['next_page']);
    }

    public function test_relation_options_reject_non_belongs_to_fields(): void
    {
        $this->expectException(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);

        $this->dataSource($this->defaultRegistry())->resolve(
            NonRelationFieldResource::class,
            'name',
            \Illuminate\Http\Request::create('/'),
        );
    }

    public function test_relation_options_reject_hidden_owning_fields(): void
    {
        $this->fakeGateDenying('view-category');

        $this->expectException(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);

        $this->dataSource($this->defaultRegistry())->resolve(
            HiddenBelongsToFieldProductResource::class,
            'category_id',
            \Illuminate\Http\Request::create('/'),
        );
    }

    public function test_relation_options_reject_inaccessible_related_resources(): void
    {
        $this->expectException(\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException::class);

        $this->dataSource($this->defaultRegistry())->resolve(
            InaccessibleRelationOptionsProductResource::class,
            'category_id',
            \Illuminate\Http\Request::create('/'),
        );
    }

    public function test_relation_options_respect_related_resource_query_extensions_for_selected_values(): void
    {
        $payload = $this->dataSource($this->defaultRegistry())->resolve(
            ScopedRelationOptionsProductResource::class,
            'category_id',
            \Illuminate\Http\Request::create('/', 'GET', [
                'selected' => $this->hidden->getKey(),
            ]),
        );

        $labels = array_column($payload['items'], 'label');

        self::assertNotContains('Hidden', $labels);
        self::assertContains('Hardware', $labels);
        self::assertContains('Software', $labels);
    }

    public function test_relation_options_respect_field_query_modifier_for_options_and_selected_values(): void
    {
        $registry = $this->defaultRegistry();
        $registry->register(ModifiedRelationOptionsProductResource::class);

        $payload = $this->dataSource($registry)->resolve(
            ModifiedRelationOptionsProductResource::class,
            'category_id',
            \Illuminate\Http\Request::create('/', 'GET', [
                'selected' => $this->hidden->getKey(),
            ]),
        );
        $labels = array_column($payload['items'], 'label');

        self::assertNotContains('Hidden', $labels);
        self::assertContains('Hardware', $labels);
        self::assertContains('Software', $labels);
    }

    public function test_relation_options_use_last_duplicate_field_query_modifier(): void
    {
        $registry = $this->defaultRegistry();
        $registry->register(DuplicateModifierRelationOptionsProductResource::class);

        $payload = $this->dataSource($registry)->resolve(
            DuplicateModifierRelationOptionsProductResource::class,
            'category_id',
            \Illuminate\Http\Request::create('/'),
        );
        $labels = array_column($payload['items'], 'label');

        self::assertNotContains('Hidden', $labels);
        self::assertContains('Hardware', $labels);
        self::assertContains('Software', $labels);
    }

    public function test_relation_options_can_use_explicit_model_fallback_without_related_resource(): void
    {
        $registry = new ResourceRegistry();
        $registry->register(ModelFallbackRelationOptionsProductResource::class);

        $payload = $this->dataSource($registry)->resolve(
            ModelFallbackRelationOptionsProductResource::class,
            'category_id',
            \Illuminate\Http\Request::create('/', 'GET', [
                'search' => 'hard',
            ]),
        );

        self::assertSame([
            [
                'label' => 'Hardware',
                'value' => $this->hardware->getKey(),
            ],
        ], $payload['items']);
    }

    public function test_relation_query_modifier_requires_builder_return_value(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('must return an Eloquent query builder');

        RelationQueryModifier::apply(
            static fn (Builder $query) => null,
            BelongsToCategory::query(),
            'category_id',
        );
    }

    private function dataSource(ResourceRegistry $resourceRegistry): ResourceRelationOptionsDataSource
    {
        $screenAccessResolver = new ScreenAccessResolver(new PolicyBridge());

        return new ResourceRelationOptionsDataSource(
            $this->authenticator(),
            $screenAccessResolver,
            new ExtensionRegistry(),
            $resourceRegistry,
            new ResourceSurfaceResolver($screenAccessResolver),
        );
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

    private function defaultRegistry(): ResourceRegistry
    {
        $registry = new ResourceRegistry();
        $registry->register(BelongsToCategoryResource::class);
        $registry->register(BelongsToProductResource::class);
        $registry->register(ScopedRelationOptionsCategoryResource::class);

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

    private function fakeGateDenying(string $deniedAbility): void
    {
        $this->app->instance(Gate::class, new class($deniedAbility) implements Gate
        {
            public function __construct(private readonly string $deniedAbility)
            {
            }

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
                return $ability !== $this->deniedAbility;
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
}

final class NonRelationFieldResource extends Resource
{
    public static function model(): string
    {
        return BelongsToProduct::class;
    }

    public static function form(FormContract $form): FormContract
    {
        return $form->schema([
            TextInput::make('name', 'Name'),
        ]);
    }
}

final class HiddenBelongsToFieldProductResource extends Resource
{
    public static function model(): string
    {
        return BelongsToProduct::class;
    }

    public static function policy(): string
    {
        return \stdClass::class;
    }

    public static function fieldAbilityMap(): array
    {
        return [
            'category_id' => 'view-category',
        ];
    }

    public static function form(FormContract $form): FormContract
    {
        return $form->schema([
            BelongsTo::make('category_id', 'Category')->resource(BelongsToCategoryResource::class),
        ]);
    }
}

final class InaccessibleRelationOptionsCategoryResource extends Resource
{
    public static function model(): string
    {
        return BelongsToCategory::class;
    }

    public static function canAccess(?\Illuminate\Contracts\Auth\Authenticatable $user = null): bool
    {
        return false;
    }
}

final class InaccessibleRelationOptionsProductResource extends Resource
{
    public static function model(): string
    {
        return BelongsToProduct::class;
    }

    public static function form(FormContract $form): FormContract
    {
        return $form->schema([
            BelongsTo::make('category_id', 'Category')->resource(InaccessibleRelationOptionsCategoryResource::class),
        ]);
    }
}

final class ScopedRelationOptionsCategoryResource extends Resource
{
    public static function model(): string
    {
        return BelongsToCategory::class;
    }

    public static function queryExtensions(): array
    {
        return [new VisibleRelationOptionsCategoryQueryExtension()];
    }
}

final class ScopedRelationOptionsProductResource extends Resource
{
    public static function model(): string
    {
        return BelongsToProduct::class;
    }

    public static function form(FormContract $form): FormContract
    {
        return $form->schema([
            BelongsTo::make('category_id', 'Category')
                ->resource(ScopedRelationOptionsCategoryResource::class)
                ->titleAttribute('name'),
        ]);
    }
}

final class ModelFallbackRelationOptionsProductResource extends Resource
{
    public static function model(): string
    {
        return BelongsToProduct::class;
    }

    public static function form(FormContract $form): FormContract
    {
        return $form->schema([
            BelongsTo::make('category_id', 'Category')
                ->model(BelongsToCategory::class)
                ->titleAttribute('name')
                ->searchable('name'),
        ]);
    }
}

final class ModifiedRelationOptionsProductResource extends Resource
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
                ->modifyQueryUsing(static fn (Builder $query): Builder => $query->where('visible', true)),
        ]);
    }
}

final class DuplicateModifierRelationOptionsProductResource extends Resource
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
                ->modifyQueryUsing(static fn (Builder $query): Builder => $query->where('visible', false)),
            BelongsTo::make('category_id', 'Category')
                ->resource(BelongsToCategoryResource::class)
                ->titleAttribute('name')
                ->modifyQueryUsing(static fn (Builder $query): Builder => $query->where('visible', true)),
        ]);
    }
}

final class VisibleRelationOptionsCategoryQueryExtension implements QueryExtensionContract
{
    public function extend(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('visible', true);
    }
}
