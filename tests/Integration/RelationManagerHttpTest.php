<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Tests\Integration;

use Illuminate\Auth\GenericUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany as HasManyRelation;
use Illuminate\Database\Eloquent\Relations\HasOne as HasOneRelation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase;
use Pepperfm\Flashboard\Contracts\Resources\Resource;
use Pepperfm\Flashboard\Core\Registry\ResourceRegistry;
use Pepperfm\Flashboard\Core\Relations\HasMany;
use Pepperfm\Flashboard\Core\Relations\HasOne;
use Pepperfm\Flashboard\Integration\Laravel\FlashboardServiceProvider;
use Pepperfm\Flashboard\Integration\Laravel\Routing\PanelRouteRegistrar;

final class RelationManagerHttpTest extends TestCase
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
            'prefix' => '',
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('relation_http_parents', static function (Blueprint $table): void {
            $table->string('id')->primary();
        });
        Schema::create('relation_http_children', static function (Blueprint $table): void {
            $table->id();
            $table->string('parent_id')->nullable();
            $table->string('name');
        });
        Schema::create('relation_http_profiles', static function (Blueprint $table): void {
            $table->id();
            $table->string('parent_id')->nullable();
            $table->string('name');
        });

        Gate::define('accessFlashboard', static fn (GenericUser $user): bool => true);
        $this->actingAs(new GenericUser(['id' => 1]));

        $registry = $this->app->make(ResourceRegistry::class);
        $registry->register(RelationHttpParentResource::class);
        $registry->register(RelationHttpChildResource::class);
        $registry->register(RelationHttpProfileResource::class);
        $this->app->make(PanelRouteRegistrar::class)->register();
    }

    public function test_nested_routes_resolve_relation_key_for_uuid_and_numeric_parent_keys(): void
    {
        foreach (['f7f1499b-e13d-4226-8689-4746aaea2461', '42'] as $parentKey) {
            RelationHttpParent::query()->create(['id' => $parentKey]);
            $attached = RelationHttpChild::query()->create(['parent_id' => $parentKey, 'name' => 'Attached']);
            $orphan = RelationHttpChild::query()->create(['name' => 'Orphan '.$parentKey]);
            $base = '/admin/resources/relation_http_parent/'.$parentKey.'/_relations';

            $this->getJson($base.'/items')->assertOk()
                ->assertJsonPath('key', 'items')
                ->assertJsonPath('records.0.title', 'Attached');
            $this->getJson($base.'/items/options?search='.urlencode($orphan->name))->assertOk()
                ->assertJsonPath('items.0.value', $orphan->getKey());
            $this->getJson($base.'/missing')->assertNotFound();

            $this->post($base.'/items/attach', ['related' => $orphan->getKey()])->assertRedirect();
            self::assertSame($parentKey, $orphan->refresh()->parent_id);
            $this->delete($base.'/items/detach', ['related' => [$attached->getKey()]])->assertRedirect();
            self::assertNull($attached->refresh()->parent_id);
            $this->patch($base.'/items/sync', ['related' => [$attached->getKey()]])->assertRedirect();
            self::assertSame($parentKey, $attached->refresh()->parent_id);
            self::assertNull($orphan->refresh()->parent_id);

            $profile = RelationHttpProfile::query()->create(['parent_id' => $parentKey, 'name' => 'Current']);
            $replacement = RelationHttpProfile::query()->create(['name' => 'Replacement']);
            $this->patch($base.'/profile/replace', ['related' => $replacement->getKey()])->assertRedirect();
            self::assertNull($profile->refresh()->parent_id);
            self::assertSame($parentKey, $replacement->refresh()->parent_id);
        }
    }
}

final class RelationHttpParent extends Model
{
    protected $table = 'relation_http_parents';

    protected $keyType = 'string';

    protected $guarded = [];

    public $incrementing = false;

    public $timestamps = false;

    public function items(): HasManyRelation
    {
        return $this->hasMany(RelationHttpChild::class, 'parent_id');
    }

    public function profile(): HasOneRelation
    {
        return $this->hasOne(RelationHttpProfile::class, 'parent_id');
    }
}

/**
 * @property string $name
 * @property string|null $parent_id
 */
final class RelationHttpChild extends Model
{
    protected $table = 'relation_http_children';

    protected $guarded = [];

    public $timestamps = false;
}

/** @property string|null $parent_id */
final class RelationHttpProfile extends Model
{
    protected $table = 'relation_http_profiles';

    protected $guarded = [];

    public $timestamps = false;
}

final class RelationHttpParentResource extends Resource
{
    public static function model(): string
    {
        return RelationHttpParent::class;
    }

    public static function relations(): array
    {
        return [
            HasMany::make('items')
                ->resource(RelationHttpChildResource::class)
                ->searchable(['name'])
                ->attachable()
                ->detachable()
                ->syncable(),
            HasOne::make('profile')
                ->resource(RelationHttpProfileResource::class)
                ->attachable()
                ->detachable()
                ->replaceable(),
        ];
    }
}

final class RelationHttpChildResource extends Resource
{
    public static function model(): string
    {
        return RelationHttpChild::class;
    }
}

final class RelationHttpProfileResource extends Resource
{
    public static function model(): string
    {
        return RelationHttpProfile::class;
    }
}
