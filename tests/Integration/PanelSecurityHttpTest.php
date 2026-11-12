<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Tests\Integration;

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Orchestra\Testbench\TestCase;
use Pepperfm\Flashboard\Contracts\Resources\Resource;
use Pepperfm\Flashboard\Core\Actions\Builders\Action;
use Pepperfm\Flashboard\Integration\Laravel\FlashboardServiceProvider;
use Pepperfm\Flashboard\Integration\Laravel\Http\Controllers\ActionController;

final class PanelSecurityHttpTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [FlashboardServiceProvider::class];
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
