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
use Pepperfm\Flashboard\Core\Relations\Timeline;
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
            $table->timestamp('created_at')->nullable();
            $table->timestamp('private_date')->nullable();
            $table->text('description')->nullable();
            $table->text('private_description')->nullable();
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

    public function test_sync_with_incomplete_or_stale_snapshot_leaves_membership_unchanged(): void
    {
        $parent = RelationHttpParent::query()->create(['id' => 'sync-probe']);
        $first = RelationHttpChild::query()->create(['parent_id' => $parent->getKey(), 'name' => 'First']);
        $second = RelationHttpChild::query()->create(['parent_id' => $parent->getKey(), 'name' => 'Second']);
        $base = '/admin/resources/relation_http_parent/'.$parent->getKey().'/_relations/items/sync';

        $this->patchJson($base, [
            'related' => [$first->getKey()],
            'expected_related' => [$first->getKey()],
        ])->assertUnprocessable()->assertJsonValidationErrors('related');
        self::assertSame($parent->getKey(), $first->refresh()->parent_id);
        self::assertSame($parent->getKey(), $second->refresh()->parent_id);

        $this->patchJson($base, [
            'related' => [$first->getKey()],
            'expected_related' => [$second->getKey(), $first->getKey()],
        ])->assertRedirect();
        self::assertSame($parent->getKey(), $first->refresh()->parent_id);
        self::assertNull($second->refresh()->parent_id);
    }

    public function test_nested_create_form_uses_server_rules_without_serializing_them(): void
    {
        $parent = RelationHttpParent::query()->create(['id' => 'nested-validation']);
        $base = '/admin/resources/relation_http_child';
        $query = http_build_query([
            'parent_resource' => 'relation_http_parent',
            'parent_record' => $parent->getKey(),
            'relation' => 'items',
        ]);

        $this->get($base.'/create?'.$query)->assertOk()->assertDontSee('server-only-rule-configuration');
        $this->get($base.'/create?'.$query, ['X-Inertia' => 'true', 'Accept' => 'text/html'])
            ->assertOk()->assertJsonPath('props.payload.form.rules', []);
        $this->getJson($base.'/create?'.$query)->assertOk()->assertJsonPath('payload.form.rules', []);
        $this->postJson($base.'?'.$query, ['name' => 'reserved'])->assertUnprocessable()
            ->assertJsonValidationErrors('name');
        $this->postJson($base.'?'.$query, ['name' => 'class-denied'])->assertUnprocessable()
            ->assertJsonValidationErrors('name');
        $this->postJson($base.'?'.$query, ['name' => 'Nested child'])->assertRedirect();
        $this->assertDatabaseHas('relation_http_children', ['parent_id' => 'nested-validation', 'name' => 'Nested child']);
    }

    public function test_timeline_is_read_only_scoped_and_paginated_in_chronological_order(): void
    {
        $parent = RelationHttpParent::query()->create(['id' => 'timeline-probe']);
        $older = RelationHttpChild::query()->create([
            'parent_id' => $parent->getKey(), 'name' => 'Older', 'created_at' => '2026-11-25 12:00:00',
        ]);
        $first = RelationHttpChild::query()->create([
            'parent_id' => $parent->getKey(), 'name' => 'Same date A', 'created_at' => '2026-11-26 12:00:00',
        ]);
        $second = RelationHttpChild::query()->create([
            'parent_id' => $parent->getKey(), 'name' => 'Same date B', 'created_at' => '2026-11-26 12:00:00',
            'description' => '<script>timeline-probe</script>', 'private_description' => 'hidden-timeline-marker', 'private_date' => '2026-11-26 12:00:00',
        ]);
        RelationHttpChild::query()->create([
            'parent_id' => $parent->getKey(), 'name' => 'Excluded', 'created_at' => '2026-11-27 12:00:00',
        ]);
        RelationHttpChild::query()->create(['parent_id' => $parent->getKey(), 'name' => 'Undated']);
        $orphan = RelationHttpChild::query()->create(['name' => 'Orphan', 'created_at' => '2026-11-28 12:00:00']);
        $base = '/admin/resources/relation_http_parent/'.$parent->getKey().'/_relations/history';

        $this->getJson($base)->assertOk()
            ->assertJsonPath('presentation', 'timeline')
            ->assertJsonPath('read_only', true)
            ->assertJsonPath('actions', [])
            ->assertJsonPath('options_url', null)
            ->assertJsonPath('records.0.key', $second->getKey())
            ->assertJsonPath('records.0.description', '<script>timeline-probe</script>')
            ->assertJsonPath('records.0.date', '2026-11-26 12:00:00')
            ->assertJsonPath('records.0.links.edit', null)
            ->assertJsonPath('records.1.key', $first->getKey())
            ->assertJsonPath('pagination.next_page', 2)
            ->assertDontSee('hidden-timeline-marker')
            ->assertDontSee('Excluded');
        $this->getJson($base.'?page=2')->assertOk()
            ->assertJsonCount(1, 'records')->assertJsonPath('records.0.key', $older->getKey())
            ->assertJsonPath('pagination.has_more', false);
        $this->getJson(str_replace('/history', '/hidden_history', $base))->assertOk()
            ->assertJsonPath('records.0.description', null)->assertDontSee('hidden-timeline-marker');
        $this->getJson(str_replace('/history', '/hidden_date', $base))->assertOk()
            ->assertJsonCount(0, 'records');
        $this->getJson($base.'/options')->assertForbidden();
        $this->postJson($base.'/attach', ['related' => $orphan->getKey()])->assertForbidden();
        $this->deleteJson($base.'/detach', ['related' => [$second->getKey()]])->assertForbidden();
        $this->patchJson($base.'/replace', ['related' => $orphan->getKey()])->assertForbidden();
        $this->patchJson($base.'/sync', ['related' => []])->assertForbidden();
        self::assertSame($parent->getKey(), $second->refresh()->parent_id);
        self::assertNull($orphan->refresh()->parent_id);
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

    protected $hidden = ['private_description', 'private_date'];

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
            Timeline::make('history', 'History', 'items')
                ->resource(RelationHttpChildResource::class)
                ->descriptionAttribute('description')
                ->perPage(2)
                ->readOnly(false)
                ->attachable()
                ->syncable()
                ->modifyRecordsQueryUsing(static fn ($query) => $query->where('name', '!=', 'Excluded')),
            Timeline::make('hidden_history', 'Hidden description', 'items')
                ->resource(RelationHttpChildResource::class)
                ->descriptionAttribute('private_description'),
            Timeline::make('hidden_date', 'Hidden date', 'items')
                ->resource(RelationHttpChildResource::class)
                ->dateAttribute('private_date'),
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

    public static function form(\Pepperfm\Flashboard\Contracts\Forms\FormContract $form): \Pepperfm\Flashboard\Contracts\Forms\FormContract
    {
        return $form->schema([
            \Pepperfm\Flashboard\Core\Forms\Fields\TextInput::make('name')->required(),
        ])->rules([
            'name' => [
                static function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value === 'reserved') {
                        $fail('Reserved name.');
                    }
                },
                new \Pepperfm\Flashboard\Tests\Fixtures\Rules\ServerOnlyNameRule(),
            ],
        ]);
    }
}

final class RelationHttpProfileResource extends Resource
{
    public static function model(): string
    {
        return RelationHttpProfile::class;
    }
}
