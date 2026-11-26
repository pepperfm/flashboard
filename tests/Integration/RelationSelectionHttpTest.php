<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Tests\Integration;

use Illuminate\Auth\GenericUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase;
use Pepperfm\Flashboard\Core\Registry\ResourceRegistry;
use Pepperfm\Flashboard\Integration\Laravel\FlashboardServiceProvider;
use Pepperfm\Flashboard\Integration\Laravel\Routing\PanelRouteRegistrar;
use Pepperfm\Flashboard\Tests\Fixtures\Models\BelongsToCategory;
use Pepperfm\Flashboard\Tests\Fixtures\Models\BelongsToProduct;
use Pepperfm\Flashboard\Tests\Fixtures\Models\BelongsToManyTag;
use Pepperfm\Flashboard\Tests\Fixtures\Models\BelongsToManyProduct;
use Pepperfm\Flashboard\Tests\Fixtures\RelationSelectionPolicy;
use Pepperfm\Flashboard\Tests\Fixtures\Resources\SelectionCategoryResource;
use Pepperfm\Flashboard\Tests\Fixtures\Resources\SelectionProductResource;
use Pepperfm\Flashboard\Tests\Fixtures\Resources\SelectionTagResource;
use Pepperfm\Flashboard\Tests\Fixtures\Resources\SelectionTaggedProductResource;

final class RelationSelectionHttpTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [FlashboardServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('session.driver', 'array');
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['belongs_to_categories', 'belongs_to_many_tags'] as $tableName) {
            Schema::create($tableName, static function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
            });
        }

        Schema::create('belongs_to_products', static function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('category_id')->nullable();
        });
        Schema::create('belongs_to_many_products', static function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });
        Schema::create('belongs_to_many_product_tag', static function (Blueprint $table): void {
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('tag_id');
        });

        Gate::define('accessFlashboard', static fn(GenericUser $user): bool => true);
        Gate::policy(BelongsToCategory::class, RelationSelectionPolicy::class);
        Gate::policy(BelongsToManyTag::class, RelationSelectionPolicy::class);
        $this->actingAs(new GenericUser(['id' => 1]));

        $registry = $this->app->make(ResourceRegistry::class);
        foreach ([
            SelectionCategoryResource::class,
            SelectionProductResource::class,
            SelectionTagResource::class,
            SelectionTaggedProductResource::class,
        ] as $resource) {
            $registry->register($resource);
        }

        $this->app->make(PanelRouteRegistrar::class)->register();
    }

    public function test_belongs_to_rejects_query_modifier_and_policy_denials_on_create_and_update(): void
    {
        $allowed = BelongsToCategory::query()->create(['name' => 'Allowed', 'slug' => 'allowed']);
        $product = BelongsToProduct::query()->create(['name' => 'Original', 'category_id' => $allowed->getKey()]);
        $base = '/admin/resources/selection_product';

        foreach (['outside', 'filtered', 'policy-denied'] as $slug) {
            $denied = BelongsToCategory::query()->create(['name' => $slug, 'slug' => $slug]);
            $payload = ['name' => 'Changed', 'category_id' => $denied->getKey()];

            $this->getJson($base.'/_relation-options/category_id?search='.$slug)
                ->assertOk()->assertJsonCount(0, 'items');
            $this->postJson($base, $payload)->assertUnprocessable()->assertJsonValidationErrors('category_id');
            $this->patchJson($base.'/'.$product->getKey(), $payload)
                ->assertUnprocessable()->assertJsonValidationErrors('category_id');

            self::assertSame('Original', $product->refresh()->getAttribute('name'));
            self::assertSame($allowed->getKey(), $product->getAttribute('category_id'));
        }

        self::assertSame(1, BelongsToProduct::query()->count());
        $this->postJson($base, ['name' => 'Allowed product', 'category_id' => $allowed->getKey()])->assertRedirect();
        self::assertSame(2, BelongsToProduct::query()->count());
    }

    public function test_belongs_to_many_denied_record_keeps_scalar_and_pivot_state_unchanged(): void
    {
        $allowed = BelongsToManyTag::query()->create(['name' => 'Allowed', 'slug' => 'allowed']);
        $product = BelongsToManyProduct::query()->create(['name' => 'Original']);
        $product->tags()->attach($allowed->getKey());
        $base = '/admin/resources/selection_tagged_product';

        foreach (['outside', 'filtered', 'policy-denied'] as $slug) {
            $denied = BelongsToManyTag::query()->create(['name' => $slug, 'slug' => $slug]);
            $payload = ['name' => 'Changed', 'tags' => [$allowed->getKey(), $denied->getKey()]];

            $this->getJson($base.'/_relation-options/tags?search='.$slug)
                ->assertOk()->assertJsonCount(0, 'items');
            $this->postJson($base, $payload)->assertUnprocessable()->assertJsonValidationErrors('tags');
            $this->patchJson($base.'/'.$product->getKey(), $payload)
                ->assertUnprocessable()->assertJsonValidationErrors('tags');

            self::assertSame('Original', $product->refresh()->getAttribute('name'));
            self::assertSame([$allowed->getKey()], $product->tags()->pluck('belongs_to_many_tags.id')->all());
        }

        self::assertSame(1, BelongsToManyProduct::query()->count());
        $this->patchJson($base.'/'.$product->getKey(), ['name' => 'Changed', 'tags' => []])->assertRedirect();
        self::assertSame(0, $product->tags()->count());
    }

    public function test_denied_related_resource_rejects_direct_form_submission(): void
    {
        $allowed = BelongsToCategory::query()->create(['name' => 'Allowed', 'slug' => 'allowed']);
        $this->actingAs(new GenericUser(['id' => 2]));

        $base = '/admin/resources/selection_product';
        $this->getJson($base.'/_relation-options/category_id')->assertForbidden();
        $this->postJson($base, ['name' => 'Denied', 'category_id' => $allowed->getKey()])
            ->assertUnprocessable()->assertJsonValidationErrors('category_id');
        self::assertSame(0, BelongsToProduct::query()->count());
    }
}
