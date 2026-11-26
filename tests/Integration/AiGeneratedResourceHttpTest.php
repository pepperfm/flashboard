<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Tests\Integration;

use Illuminate\Auth\GenericUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Route;
use Orchestra\Testbench\TestCase;
use Pepperfm\Flashboard\Ai\ModelCatalog;
use Pepperfm\Flashboard\Ai\ResourceManager;
use Pepperfm\Flashboard\Contracts\Resources\Resource;
use Pepperfm\Flashboard\Contracts\Detail\DetailContract;
use Pepperfm\Flashboard\Contracts\Tables\TableContract;
use Pepperfm\Flashboard\Core\Detail\Entries\TextEntry;
use Pepperfm\Flashboard\Core\Tables\Columns\TextColumn;
use Pepperfm\Flashboard\Core\Forms\Builders\Form;
use Pepperfm\Flashboard\Core\Registry\ResourceRegistry;
use Pepperfm\Flashboard\Core\Tables\Builders\Table;
use Pepperfm\Flashboard\Integration\Laravel\FlashboardServiceProvider;
use Pepperfm\Flashboard\Integration\Laravel\Routing\PanelRouteRegistrar;
use Pepperfm\Flashboard\Tests\Fixtures\Models\AiProbeRecord;

final class AiGeneratedResourceHttpTest extends TestCase
{
    private string $directory;

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

        $this->directory = sys_get_temp_dir().'/flashboard-ai-http-'.bin2hex(random_bytes(8));
        Schema::create('ai_probe_records', static function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->integer('state')->default(0);
            $table->date('occurred_on');
            $table->timestamps();
        });

        Gate::define('accessFlashboard', static fn(GenericUser $user): bool => true);
        $this->actingAs(new GenericUser(['id' => 1]));
    }

    protected function tearDown(): void
    {
        if (isset($this->directory)) {
            new Filesystem()->deleteDirectory($this->directory);
        }

        parent::tearDown();
    }

    public function test_generated_read_only_resource_denies_standard_mutations(): void
    {
        $resource = $this->generate('AiReadOnlyProbe', true);
        $record = AiProbeRecord::query()->create([
            'name' => 'Original', 'state' => 0, 'occurred_on' => '2026-11-26',
        ]);
        $base = '/admin/resources/ai_read_only_probe';

        self::assertTrue($resource::isReadOnly());
        self::assertSame([], $resource::form(Form::make())->fieldSchema());
        self::assertSame([], $resource::actions());
        $this->getJson($base)->assertOk()
            ->assertJsonPath('payload.metrics.items.0.value', 1);
        $this->getJson($base.'/'.$record->getKey())->assertOk();
        self::assertFalse(Route::has('flashboard.resources.ai_read_only_probe.store'));
        self::assertFalse(Route::has('flashboard.resources.ai_read_only_probe.update'));
        self::assertFalse(Route::has('flashboard.resources.ai_read_only_probe.destroy'));
        $this->postJson($base, [])->assertStatus(405);
        $this->patchJson($base.'/'.$record->getKey(), ['name' => 'Changed'])->assertStatus(405);
        $this->deleteJson($base.'/'.$record->getKey())->assertStatus(405);
        self::assertSame('Original', $record->refresh()->getAttribute('name'));
    }

    public function test_generated_enum_options_validate_backing_values_and_filters(): void
    {
        $catalog = new ModelCatalog(null, 'Pepperfm\\Flashboard\\Tests\\Fixtures\\Models\\');
        $state = collect($catalog->inspect(AiProbeRecord::class)['fields'])->firstWhere('name', 'state');
        self::assertSame('int', $state['enum']['backing']);
        self::assertSame([['value' => 0, 'label' => 'Draft'], ['value' => 1, 'label' => 'Published']], $state['enum']['cases']);

        $resource = $this->generate('AiWritableProbe', false);
        $field = collect($resource::form(Form::make())->fieldSchema())->firstWhere('key', 'state');
        $filter = collect($resource::table(Table::make())->toArray()['filters'])->firstWhere('key', 'state');
        $items = [
            ['value' => 0, 'label' => 'Draft'],
            ['value' => 1, 'label' => 'Published'],
        ];
        self::assertSame($items, $field['options']);
        self::assertSame($items, $filter['options']);
        $base = '/admin/resources/ai_writable_probe';
        $payload = ['name' => 'Generated', 'state' => 1, 'occurred_on' => '2026-11-26'];

        $this->postJson($base, [...$payload, 'state' => 99])->assertUnprocessable()
            ->assertJsonValidationErrors('state');
        $this->postJson($base, $payload)->assertRedirect();
        $this->assertDatabaseHas('ai_probe_records', ['name' => 'Generated', 'state' => 1]);
        $this->getJson($base.'?filters[state]=1')->assertOk()
            ->assertJsonPath('payload.table.dataset.pagination.total', 1);
        $this->getJson($base.'?filters[occurred_on]=2026-11-25')->assertOk()
            ->assertJsonPath('payload.table.dataset.pagination.total', 0);
        $this->getJson($base)->assertOk()
            ->assertJsonPath('payload.metrics.items.2.points.0.label', 'Published');

    }

    public function test_policy_denied_mutation_links_are_absent_from_read_surfaces(): void
    {
        $resource = (new class extends Resource {
            public static function model(): string
            {
                return AiProbeRecord::class;
            }

            public static function key(): string
            {
                return 'policy_read_only_probe';
            }

            public static function policy(): string
            {
                return ReadSurfacePolicy::class;
            }

            public static function table(TableContract $table): TableContract
            {
                return $table->columns([
                    TextColumn::make('name'),
                ]);
            }

            public static function detail(DetailContract $detail): DetailContract
            {
                return $detail->entries([
                    TextEntry::make('name'),
                ]);
            }
        })::class;

        Gate::policy(AiProbeRecord::class, ReadSurfacePolicy::class);
        $this->app->make(ResourceRegistry::class)->register($resource);
        $this->app->make(PanelRouteRegistrar::class)->register();

        $record = AiProbeRecord::query()->create([
            'name' => 'Original', 'state' => 0, 'occurred_on' => '2026-11-26',
        ]);
        $base = '/admin/resources/policy_read_only_probe';
        $detail = $base.'/'.$record->getKey();

        $this->getJson($base)->assertOk()
            ->assertJsonPath('payload.resource.routes.create', null)
            ->assertJsonPath('payload.table.dataset.routes.create', null)
            ->assertJsonPath('payload.table.dataset.rows.0.links.edit', null);
        $this->getJson($detail)->assertOk()
            ->assertJsonPath('payload.resource.routes.edit', null)
            ->assertJsonPath('payload.detail.routes.edit', null);
        $this->getJson($base.'/create')->assertForbidden();
        $this->getJson($detail.'/edit')->assertForbidden();
    }

    /** @return class-string<Resource> */
    private function generate(string $name, bool $readOnly): string
    {
        $catalog = new ModelCatalog(null, 'Pepperfm\\Flashboard\\Tests\\Fixtures\\Models\\');
        $manager = new ResourceManager($catalog, new Filesystem(), $this->directory);
        $spec = [
            'resource' => $name,
            'model' => AiProbeRecord::class,
            'read_only' => $readOnly,
            'fields' => [
                ['name' => 'name', 'required' => true],
                ['name' => 'state', 'type' => 'enum', 'filter' => true, 'required' => true],
                ['name' => 'occurred_on', 'type' => 'date', 'filter_type' => 'date', 'required' => true],
            ],
            'metrics' => [
                ['kind' => 'count', 'key' => 'total'],
                ['kind' => 'trend', 'key' => 'trend', 'column' => 'occurred_on'],
                ['kind' => 'breakdown', 'key' => 'states', 'column' => 'state', 'chart' => 'donut'],
            ],
        ];

        $preview = $manager->preview($spec);
        $result = $manager->create($spec, $preview['proposal_hash']);
        require $result['path'];
        $resource = $result['resource'];
        self::assertTrue(is_subclass_of($resource, Resource::class));
        $this->app->make(ResourceRegistry::class)->register($resource);
        $this->app->make(PanelRouteRegistrar::class)->register();

        return $resource;
    }
}

final class ReadSurfacePolicy
{
    public function viewAny(GenericUser $user): bool
    {
        return true;
    }

    public function view(GenericUser $user, AiProbeRecord $record): bool
    {
        return true;
    }

    public function create(GenericUser $user): bool
    {
        return false;
    }

    public function update(GenericUser $user, AiProbeRecord $record): bool
    {
        return false;
    }
}
