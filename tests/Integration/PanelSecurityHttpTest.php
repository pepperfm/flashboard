<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Tests\Integration;

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Orchestra\Testbench\TestCase;
use Pepperfm\Flashboard\Tests\PostgresTestConnection;
use Pepperfm\Flashboard\Contracts\Actions\ActionScope;
use Pepperfm\Flashboard\Contracts\Resources\Resource;
use Pepperfm\Flashboard\Core\Actions\Builders\Action;
use Pepperfm\Flashboard\Core\Forms\Builders\Form;
use Pepperfm\Flashboard\Core\Forms\Fields\DateInput;
use Pepperfm\Flashboard\Core\Forms\Fields\NumberInput;
use Pepperfm\Flashboard\Core\Forms\Fields\Textarea;
use Pepperfm\Flashboard\Core\Registry\ResourceRegistry;
use Pepperfm\Flashboard\Core\Runtime\Actions\ActionExecutionResult;
use Pepperfm\Flashboard\Integration\Laravel\FlashboardServiceProvider;
use Pepperfm\Flashboard\Integration\Laravel\Http\Controllers\ActionController;
use Pepperfm\Flashboard\Integration\Laravel\Routing\PanelRouteRegistrar;

final class PanelSecurityHttpTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [FlashboardServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:' . base64_encode(random_bytes(32)));
        $app['config']->set('session.driver', 'array');
    }

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'flashboard.auth'])
            ->get('/__flashboard_access_probe', static fn () => response('ok'));

        Route::middleware(['web', 'flashboard.auth'])
            ->post('/__flashboard_action_probe/{action}', ActionController::class)
            ->defaults('flashboard.resource', DeniedActionResource::class);
    }

    public function test_panel_login_uses_package_root_view_even_when_host_has_inertia(): void
    {
        // Host applications may already have a different Inertia root view.
        \Inertia\Inertia::setRootView('host-inertia-view');

        $response = $this->get('/admin/login');

        $response->assertOk();
        $response->assertSee('vendor/flashboard/build/', false);
        $response->assertDontSee('resources/js/app.ts', false);
    }

    public function test_guests_cannot_access_panel_endpoints(): void
    {
        $this->get('/__flashboard_access_probe')->assertRedirect();
    }

    public function test_authenticated_users_are_denied_without_panel_gate(): void
    {
        $this->actingAs(new GenericUser(['id' => 1, 'email' => 'user@example.test']))
            ->get('/__flashboard_access_probe')
            ->assertForbidden();
    }

    public function test_gate_authorized_users_can_access_panel(): void
    {
        Gate::define('accessFlashboard', static fn (GenericUser $user): bool => $user->getAuthIdentifier() === 1);

        $this->actingAs(new GenericUser(['id' => 1, 'email' => 'admin@example.test']))
            ->get('/__flashboard_access_probe')
            ->assertOk();
    }

    public function test_collection_actions_cannot_bypass_resource_access(): void
    {
        Gate::define('accessFlashboard', static fn (GenericUser $user): bool => true);

        $this->actingAs(new GenericUser(['id' => 1, 'email' => 'admin@example.test']))
            ->post('/__flashboard_action_probe/ping')
            ->assertForbidden();
    }

    public function test_record_action_uses_named_route_and_validated_context(): void
    {
        $base = $this->actionFixture();
        $key = 'f7f1499b-e13d-4226-8689-4746aaea2461';
        $url = $base . '/42/actions/grant';
        $this->getJson($url . '/form')->assertOk()->assertJsonPath('defaults.days', 7)->assertJsonMissingPath('rules');
        $this->postJson($url, ['payload' => ['days' => 3, 'reason' => 'Test'], 'idempotency_key' => $key])
            ->assertOk()->assertJsonPath('data.record', '42')->assertJsonPath('data.days', 3)
            ->assertJsonPath('data.idempotency_key', $key)->assertJsonPath('data.user', 1);
        $this->postJson($url, ['payload' => ['days' => 3, 'reason' => 'Test', 'user' => ['id' => 2]], 'idempotency_key' => $key])
            ->assertUnprocessable();
        $this->postJson($url, ['payload' => ['days' => 0, 'reason' => 'Test'], 'idempotency_key' => $key])
            ->assertUnprocessable();
        $this->postJson($base . '/actions/grant', ['payload' => [], 'idempotency_key' => $key])->assertForbidden();
        $this->getJson($base . '/42/actions/hidden/form')->assertForbidden();
        $this->postJson($base . '/42/actions/hidden')->assertForbidden();
        $this->postJson($base . '/42/actions/forbidden')->assertForbidden();
        $this->postJson($base . '/missing/actions/grant')->assertNotFound();
    }

    public function test_record_action_business_failure_and_collection_scope(): void
    {
        $base = $this->actionFixture();
        $this->postJson($base . '/actions/legacy', ['value' => 'legacy'])
            ->assertOk()->assertJsonPath('data.value', 'legacy');
        $this->postJson($base . '/actions/ping')->assertOk();
        $this->postJson($base . '/42/actions/third_party', ['value' => 'legacy'])
            ->assertOk()->assertJsonPath('data.record', '42')->assertJsonPath('data.payload.value', 'legacy');
        $this->postJson($base . '/42/actions/ping')->assertForbidden();
        $this->postJson($base . '/42/actions/cancel')->assertConflict()->assertJsonPath('successful', false);
        $this->from($base)->post($base . '/42/actions/cancel')->assertRedirect($base)
            ->assertSessionHas('error', 'Cannot cancel.');
        $uuid = 'f7f1499b-e13d-4226-8689-4746aaea2461';
        $this->postJson($base . '/' . $uuid . '/actions/credits', [
            'payload' => ['units' => 3, 'expiry' => '2027-01-01', 'reason' => 'Test'],
            'idempotency_key' => $uuid,
        ])->assertOk()->assertJsonPath('data.units', 3);
        $this->postJson($base . '/' . $uuid . '/actions/cancel')->assertForbidden();
        $this->postJson($base . '/42/actions/disable', ['payload' => [], 'idempotency_key' => 'f7f1499b-e13d-4226-8689-4746aaea2461'])
            ->assertOk()->assertJsonPath('data.payload', []);
    }

    public function test_head_prepares_form_without_running_handler(): void
    {
        $base = $this->actionFixture();
        $url = $base . '/42/actions/grant';
        ActionProbeResource::$executions = 0;
        $input = ['payload' => ['days' => 3, 'reason' => 'Test'], 'idempotency_key' => 'f7f1499b-e13d-4226-8689-4746aaea2461'];

        $this->call('HEAD', $url . '/form', $input, [], [], ['HTTP_ACCEPT' => 'application/json'])->assertOk();
        $this->getJson($url . '/form')->assertOk();
        self::assertSame(0, ActionProbeResource::$executions);
        $this->postJson($url, $input)->assertOk();
        self::assertSame(1, ActionProbeResource::$executions);

        $response = $this->getJson($base . '/42/actions/credits/form')->assertOk();
        self::assertInstanceOf(\stdClass::class, json_decode($response->getContent())->defaults);
    }

    public function test_explicit_action_checks_fail_closed_even_with_legacy_scope(): void
    {
        $base = $this->actionFixture();
        $this->postJson($base . '/42/actions/mapped')->assertForbidden();
        $this->getJson($base . '/42/actions/denied_callback/form')->assertForbidden();
        $this->postJson($base . '/42/actions/denied_callback')->assertForbidden();
    }

    public function test_explicit_policy_must_be_registered_and_receive_the_record(): void
    {
        $this->actionFixture();
        $this->app->make(ResourceRegistry::class)->register(PolicyActionProbeResource::class);
        $this->app->make(PanelRouteRegistrar::class)->register();
        $base = '/admin/resources/policy_action_probe';

        $this->postJson($base . '/42/actions/forbidden')->assertForbidden();
        Gate::policy(ActionProbeRecord::class, ActionProbePolicy::class);
        $this->postJson($base . '/42/actions/forbidden')->assertOk();
        $this->postJson($base . '/f7f1499b-e13d-4226-8689-4746aaea2461/actions/forbidden')->assertForbidden();
    }

    public function test_invalid_native_postgresql_record_key_returns_not_found(): void
    {
        $this->app['config']->set('database.connections.flashboard_pg_test', PostgresTestConnection::configuration());
        $connection = $this->app['db']->connection('flashboard_pg_test');
        $connection->getSchemaBuilder()->create('flashboard_action_probe', static function (\Illuminate\Database\Schema\Blueprint $table): void {
            $table->uuid('id')->primary();
        });

        try {
            $uuid = 'f7f1499b-e13d-4226-8689-4746aaea2461';
            $connection->table('flashboard_action_probe')->insert(['id' => $uuid]);
            $this->actionFixture();
            $this->app->make(ResourceRegistry::class)->register(NativeUuidActionProbeResource::class);
            $this->app->make(PanelRouteRegistrar::class)->register();
            $base = '/admin/resources/native_uuid_action_probe';
            $this->postJson($base . '/not-a-uuid/actions/ping')->assertNotFound();
            $this->postJson($base . '/' . $uuid . '/actions/ping')->assertOk()->assertJsonPath('data.record', $uuid);
        } finally {
            $connection->getSchemaBuilder()->dropIfExists('flashboard_action_probe');
        }
    }

    private function actionFixture(): string
    {
        Gate::define('accessFlashboard', static fn (GenericUser $user): bool => true);
        $this->actingAs(new GenericUser(['id' => 1]));
        $this->app->make(ResourceRegistry::class)->register(ActionProbeResource::class);
        $this->app->make(PanelRouteRegistrar::class)->register();

        return '/admin/resources/action_probe';
    }

    public function test_login_attempts_are_throttled_at_the_route_boundary(): void
    {
        $route = Route::getRoutes()->getByName('flashboard.auth.attempt');

        self::assertNotNull($route);
        self::assertContains('throttle:6,1', $route->gatherMiddleware());
    }
}

final class DeniedActionResource extends Resource
{
    public static function model(): string
    {
        return \Illuminate\Database\Eloquent\Model::class;
    }

    public static function canAccess(?\Illuminate\Contracts\Auth\Authenticatable $user = null): bool
    {
        return false;
    }

    public static function actions(): array
    {
        return [Action::make('ping')->handleUsing(static fn (): array => ['executed' => true])];
    }
}

final class ActionProbeRecord extends \Illuminate\Database\Eloquent\Model
{
    protected $keyType = 'string';
    public $incrementing = false;
}

class ActionProbeResource extends Resource
{
    public static int $executions = 0;

    public static function actionAbilityMap(): array
    {
        return ['mapped' => 'grant'];
    }

    public static function model(): string
    {
        return ActionProbeRecord::class;
    }

    public static function resolveRecord(mixed $recordKey): ?\Illuminate\Database\Eloquent\Model
    {
        if (!in_array($recordKey, ['42', 'f7f1499b-e13d-4226-8689-4746aaea2461'], true)) {
            return null;
        }

        return new ActionProbeRecord()->forceFill(['id' => $recordKey]);
    }

    public static function actions(): array
    {
        $form = static fn (): Form => Form::make()->schema([
            NumberInput::make('days')->required(),
            Textarea::make('reason')->required(),
        ])->rules(['days' => ['integer', 'min:1', 'max:365']])->defaults(['days' => 7]);

        return [
            Action::make('grant')->scope(ActionScope::Record)->form($form)
                ->handleUsing(static function (array $context): array {
                    ++self::$executions;
                    return [
                    'record' => $context['model']->getKey(),
                    'days' => $context['payload']['days'],
                    'idempotency_key' => $context['idempotency_key'],
                    'user' => $context['user']->getAuthIdentifier(),
                ]; }),
            Action::make('credits')->scope(ActionScope::Record)->form(Form::make()->schema([
                NumberInput::make('units')->required(),
                DateInput::make('expiry')->required(),
                Textarea::make('reason')->required(),
            ])->rules(['units' => ['integer', 'min:1']]))
                ->handleUsing(static fn (array $context): array => $context['payload']),
            Action::make('hidden')->scope(ActionScope::Record)->form($form)->visible(false),
            Action::make('mapped'),
            Action::make('denied_callback')->scope(ActionScope::Record)->form($form)->authorizeUsing(static fn (): bool => false),
            Action::make('forbidden')->scope(ActionScope::Record)->ability('grant'),
            Action::make('legacy')->handleUsing(static fn (array $context): array => $context['payload']),
            LegacyProbeAction::make('third_party'),
            Action::make('ping')->scope(ActionScope::Collection),
            Action::make('cancel')->scope(ActionScope::Record)->requiresConfirmation()
                ->visibleWhen(static fn (array $context): bool => $context['model']?->getKey() === '42')
                ->handleUsing(static fn (): ActionExecutionResult => new ActionExecutionResult(false, 'Cannot cancel.', null)),
            Action::make('disable')->scope(ActionScope::Record)->requiresConfirmation()
                ->handleUsing(static fn (array $context): array => ['payload' => $context['payload']]),
        ];
    }
}


final class LegacyProbeAction implements \Pepperfm\Flashboard\Contracts\Actions\ActionContract
{
    private function __construct(private string $actionKey) {}

    public static function make(string $key): static { return new self($key); }
    public function key(): string { return $this->actionKey; }
    public function label(string $label): static { return $this; }
    public function icon(?string $icon): static { return $this; }
    public function color(?string $color): static { return $this; }
    public function requiresConfirmation(bool $condition = true): static { return $this; }
    public function modal(?string $modal): static { return $this; }
    public function visible(bool $condition): static { return $this; }
    public function handleUsing(?callable $handler): static { return $this; }
    public function successMessage(?string $message): static { return $this; }
    public function redirectTo(?string $url): static { return $this; }
    public function execute(array $arguments = []): ActionExecutionResult
    {
        return new ActionExecutionResult(true, null, null, $arguments);
    }
    public function toArray(): array
    {
        return ['key' => $this->actionKey, 'label' => 'Legacy third party', 'visible' => true];
    }
}

final class PolicyActionProbeResource extends ActionProbeResource
{
    public static function policy(): string
    {
        return ActionProbePolicy::class;
    }
}

final class ActionProbePolicy
{
    public function viewAny(GenericUser $user): bool { return true; }
    public function view(GenericUser $user, ActionProbeRecord $record): bool { return true; }
    public function grant(GenericUser $user, ActionProbeRecord $record): bool { return $record->getKey() === '42'; }
}

final class NativeUuidActionProbeRecord extends \Illuminate\Database\Eloquent\Model
{
    protected $connection = 'flashboard_pg_test';
    protected $table = 'flashboard_action_probe';
    protected $keyType = 'string';
    public $incrementing = false;
}

final class NativeUuidActionProbeResource extends Resource
{
    public static function model(): string
    {
        return NativeUuidActionProbeRecord::class;
    }

    public static function actions(): array
    {
        return [
            Action::make('ping')->scope(ActionScope::Record)
                ->handleUsing(static fn (array $context): array => ['record' => $context['record']]),
        ];
    }
}
