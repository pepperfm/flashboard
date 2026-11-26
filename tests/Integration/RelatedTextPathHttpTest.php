<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Tests\Integration;

use Illuminate\Auth\GenericUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase;
use Pepperfm\Flashboard\Contracts\Detail\DetailContract;
use Pepperfm\Flashboard\Contracts\Resources\Resource;
use Pepperfm\Flashboard\Contracts\Tables\TableContract;
use Pepperfm\Flashboard\Core\Detail\Entries\JsonEntry;
use Pepperfm\Flashboard\Core\Detail\Entries\TextEntry;
use Pepperfm\Flashboard\Core\Registry\ResourceRegistry;
use Pepperfm\Flashboard\Core\Tables\Columns\TextColumn;
use Pepperfm\Flashboard\Integration\Laravel\FlashboardServiceProvider;
use Pepperfm\Flashboard\Integration\Laravel\Routing\PanelRouteRegistrar;
use Pepperfm\Flashboard\Tests\PostgresTestConnection;

final class RelatedTextPathHttpTest extends TestCase
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
        $app['config']->set('database.connections.testing', ['driver' => 'sqlite', 'database' => ':memory:']);
    }

    public function test_sqlite_related_paths(): void
    {
        $this->assertPaths();
    }

    public function test_postgresql_related_paths(): void
    {
        $this->app['config']->set('database.connections.testing', PostgresTestConnection::configuration());
        DB::purge('testing');
        $this->assertPaths();
    }

    private function assertPaths(): void
    {
        Schema::create('dot_people', static function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('tenant');
            $table->string('name');
            $table->string('private_label');
            $table->boolean('enabled');
        });
        Schema::create('dot_profiles', static function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('person_id');
            $table->string('label');
        });
        Schema::create('dot_records', static function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('tenant');
            $table->integer('user_id')->nullable();
            $table->string('title');
            $table->text('metadata')->nullable();
            $table->text('hidden_metadata')->nullable();
        });

        try {
            DB::table('dot_people')->insert([
                ['id' => 1, 'tenant' => 1, 'name' => 'Zed 100%', 'private_label' => 'hidden-label', 'enabled' => true],
                ['id' => 2, 'tenant' => 1, 'name' => 'Alice', 'private_label' => 'hidden-label', 'enabled' => true],
                ['id' => 3, 'tenant' => 2, 'name' => 'Foreign person', 'private_label' => 'hidden-label', 'enabled' => true],
                ['id' => 4, 'tenant' => 1, 'name' => 'Disabled person', 'private_label' => 'hidden-label', 'enabled' => false],
            ]);
            DB::table('dot_profiles')->insert([
                ['person_id' => 1, 'label' => 'Profile Z'], ['person_id' => 2, 'label' => 'Profile A'],
            ]);
            foreach ([[1, 1, 1, 'sample'], [2, 1, 2, 'sample'], [3, 1, 3, 'other'], [4, 1, null, 'other'], [5, 2, 1, 'sample'], [6, 1, 4, 'other']] as [$id, $tenant, $user, $title]) {
                DB::table('dot_records')->insert([
                    'id' => $id, 'tenant' => $tenant, 'user_id' => $user, 'title' => $title,
                    'metadata' => '{"label":"<script>probe</script>","count":2}',
                    'hidden_metadata' => '{"label":"hidden-json-marker"}',
                ]);
            }

            Gate::define('accessFlashboard', static fn (GenericUser $user): bool => true);
            $this->actingAs(new GenericUser(['id' => 1]));
            $registry = $this->app->make(ResourceRegistry::class);
            $registry->registerMany([DotRecordResource::class, DotPersonResource::class]);
            $this->app->make(PanelRouteRegistrar::class)->register();
            $base = '/admin/resources/dot_record';

            $response = $this->getJson($base.'?search=sample&sort=user.name&direction=asc')->assertOk()
                ->assertJsonPath('payload.table.dataset.pagination.total', 2);
            $rows = $response->json('payload.table.dataset.rows');
            self::assertSame([2, 1], array_column($rows, 'id'));
            self::assertSame(['Alice', 'Zed 100%'], array_map(static fn (array $row): mixed => $row['attributes']['user.name'], $rows));
            self::assertSame(['Profile A', 'Profile Z'], array_map(static fn (array $row): mixed => $row['attributes']['user.profile.label'], $rows));

            $this->getJson($base.'?search=sample&sort=user.name&direction=desc')->assertOk()
                ->assertJsonPath('payload.table.dataset.rows.0.id', 1);
            foreach (['Alice' => 1, 'Profile Z' => 1, '%' => 1, 'Foreign person' => 0, 'Disabled person' => 0, 'hidden-label' => 0] as $search => $total) {
                $this->getJson($base.'?'.http_build_query(['search' => $search]))->assertOk()
                    ->assertJsonPath('payload.table.dataset.pagination.total', $total);
            }

            $response = $this->getJson($base)->assertOk()->assertJsonPath('payload.table.dataset.pagination.total', 5);
            $rows = array_column($response->json('payload.table.dataset.rows'), 'attributes', 'id');
            foreach ([3, 4, 6] as $id) {
                self::assertNull($rows[$id]['user.name']);
            }
            self::assertArrayNotHasKey('user.private_label', $rows[1]);
            $detail = $this->getJson($base.'/1')->assertOk()->assertDontSee('hidden-json-marker')->assertDontSee('hidden-label');
            $entries = array_column($detail->json('payload.detail.entries'), 'value', 'key');
            self::assertSame('Zed 100%', $entries['user.name']);
            self::assertSame('Profile Z', $entries['user.profile.label']);
            self::assertSame('ok', $entries['metadata']['status']);
            self::assertStringContainsString('<script>probe</script>', $entries['metadata']['text']);
            self::assertArrayNotHasKey('hidden_metadata', $entries);
            self::assertArrayNotHasKey('user.private_label', $entries);

            $this->get($base.'/1')->assertOk()->assertDontSee('<script>probe</script>', false);
            $this->get($base.'/1', ['X-Inertia' => 'true', 'Accept' => 'text/html'])->assertOk()
                ->assertHeader('X-Inertia', 'true')->assertDontSee('hidden-json-marker');
            $this->getJson($base.'/3')->assertOk()->assertDontSee('Foreign person');
            $this->getJson($base.'/5')->assertNotFound();
        } finally {
            Schema::dropIfExists('dot_records');
            Schema::dropIfExists('dot_profiles');
            Schema::dropIfExists('dot_people');
        }
    }
}

final class DotRecord extends Model
{
    protected $table = 'dot_records';

    public $timestamps = false;

    protected $hidden = ['hidden_metadata'];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public function user(): BelongsTo
    {
        $relation = $this->belongsTo(DotPerson::class, 'user_id');
        $relation->where('enabled', true);

        return $relation;
    }
}

final class DotPerson extends Model
{
    protected $table = 'dot_people';

    public $timestamps = false;

    protected $hidden = ['private_label'];

    public function profile(): HasOne
    {
        return $this->hasOne(DotProfile::class, 'person_id');
    }
}

final class DotProfile extends Model
{
    protected $table = 'dot_profiles';

    public $timestamps = false;
}

final class DotRecordResource extends Resource
{
    public static function model(): string
    {
        return DotRecord::class;
    }

    public static function key(): string
    {
        return 'dot_record';
    }

    public static function query(): Builder
    {
        return DotRecord::query()->where('tenant', 1);
    }

    public static function table(TableContract $table): TableContract
    {
        return $table->columns([
            TextColumn::make('title')->searchable(),
            TextColumn::make('user.name')->searchable()->sortable(),
            TextColumn::make('user.profile.label')->searchable()->sortable(),
            TextColumn::make('user.private_label')->searchable()->sortable(),
        ]);
    }

    public static function detail(DetailContract $detail): DetailContract
    {
        return $detail->entries([
            TextEntry::make('user.name'),
            TextEntry::make('user.profile.label'),
            TextEntry::make('user.private_label'),
            JsonEntry::make('metadata'),
            JsonEntry::make('hidden_metadata'),
        ]);
    }
}

final class DotPersonResource extends Resource
{
    public static function model(): string
    {
        return DotPerson::class;
    }

    public static function key(): string
    {
        return 'dot_person';
    }

    public static function query(): Builder
    {
        return DotPerson::query()->where('tenant', 1);
    }
}
