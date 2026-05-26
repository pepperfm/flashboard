<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Integration\Laravel\Routing;

use Illuminate\Container\Attributes\Singleton;
use Illuminate\Support\Facades\Route;
use Pepperfm\Flashboard\Core\Registry\PageRegistry;
use Pepperfm\Flashboard\Core\Registry\ResourceRegistry;
use Pepperfm\Flashboard\Core\Resources\ResourceSurfaceResolver;
use Pepperfm\Flashboard\Integration\Laravel\Http\Controllers\ActionController;
use Pepperfm\Flashboard\Integration\Laravel\Http\Controllers\Auth\LoginController;
use Pepperfm\Flashboard\Integration\Laravel\Http\Controllers\Auth\LogoutController;
use Pepperfm\Flashboard\Integration\Laravel\Http\Controllers\Auth\ShowLoginController;
use Pepperfm\Flashboard\Integration\Laravel\Http\Controllers\PanelScreenController;
use Pepperfm\Flashboard\Integration\Laravel\Http\Controllers\ResourceFilterOptionsController;
use Pepperfm\Flashboard\Integration\Laravel\Http\Controllers\ResourceFormController;

#[Singleton]
final readonly class PanelRouteRegistrar
{
    public function __construct(
        private PageRegistry $pageRegistry,
        private ResourceRegistry $resourceRegistry,
        private ResourceSurfaceResolver $resourceSurfaceResolver,
    ) {
    }

    public function register(): void
    {
        $path = trim((string) config('flashboard.path', 'admin'), '/');
        $routeNamePrefix = (string) config('flashboard.route_name_prefix', 'flashboard.');
        $webMiddleware = (array) config('flashboard.middleware.web', ['web']);
        $authMiddleware = (array) config('flashboard.middleware.auth', ['flashboard.auth']);

        Route::middleware($webMiddleware)
            ->prefix($path)
            ->as($routeNamePrefix)
            ->group(function () use ($authMiddleware): void {
                $this->registerAuthRoutes();

                Route::middleware($authMiddleware)
                    ->group(function (): void {
                        $this->registerPageRoutes();
                        $this->registerResourceRoutes();
                    });
            });
    }

    private function registerAuthRoutes(): void
    {
        Route::get((string) config('flashboard.auth.login_path', 'login'), ShowLoginController::class)
            ->name('auth.login');

        Route::post((string) config('flashboard.auth.login_path', 'login'), LoginController::class)
            ->name('auth.attempt');

        Route::post((string) config('flashboard.auth.logout_path', 'logout'), LogoutController::class)
            ->name('auth.logout');
    }

    private function registerPageRoutes(): void
    {
        foreach ($this->pageRegistry->all() as $pageClass) {
            $uri = trim($pageClass::uri(), '/');
            $routeUri = $uri === '' ? '/' : $uri;
            $name = $pageClass::key() === 'dashboard' ? 'home' : 'pages.' . $pageClass::key();

            Route::get($routeUri, PanelScreenController::class)
                ->middleware($pageClass::middleware())
                ->defaults('flashboard.page', $pageClass)
                ->name($name);
        }
    }

    private function registerResourceRoutes(): void
    {
        foreach ($this->resourceRegistry->all() as $resourceClass) {
            $resourcePath = trim($resourceClass::routeBasePath(), '/');
            $base = 'resources/' . $resourcePath;

            Route::get($base, PanelScreenController::class)
                ->middleware($resourceClass::middleware())
                ->defaults('flashboard.resource', $resourceClass)
                ->defaults('flashboard.resource_page', 'index')
                ->name('resources.' . $resourceClass::key() . '.index');

            Route::get("$base/create", PanelScreenController::class)
                ->middleware($resourceClass::middleware())
                ->defaults('flashboard.resource', $resourceClass)
                ->defaults('flashboard.resource_page', 'create')
                ->name('resources.' . $resourceClass::key() . '.create');

            Route::post($base, [ResourceFormController::class, 'store'])
                ->middleware($resourceClass::middleware())
                ->defaults('flashboard.resource', $resourceClass)
                ->name('resources.' . $resourceClass::key() . '.store');

            Route::get("$base/_options/{filter}", ResourceFilterOptionsController::class)
                ->middleware($resourceClass::middleware())
                ->defaults('flashboard.resource', $resourceClass)
                ->name('resources.' . $resourceClass::key() . '.filters.options');

            Route::get("$base/_filter-options/{filter}", ResourceFilterOptionsController::class)
                ->middleware($resourceClass::middleware())
                ->defaults('flashboard.resource', $resourceClass)
                ->name('resources.' . $resourceClass::key() . '.filters.legacy-options');

            if ($this->resourceSurfaceResolver->hasDetailSurfaceForResource($resourceClass)) {
                Route::get("$base/{record}", PanelScreenController::class)
                    ->middleware($resourceClass::middleware())
                    ->defaults('flashboard.resource', $resourceClass)
                    ->defaults('flashboard.resource_page', 'detail')
                    ->name('resources.' . $resourceClass::key() . '.detail');
            }

            Route::get("$base/{record}/edit", PanelScreenController::class)
                ->middleware($resourceClass::middleware())
                ->defaults('flashboard.resource', $resourceClass)
                ->defaults('flashboard.resource_page', 'edit')
                ->name('resources.' . $resourceClass::key() . '.edit');

            Route::match(['PUT', 'PATCH'], "$base/{record}", [ResourceFormController::class, 'update'])
                ->middleware($resourceClass::middleware())
                ->defaults('flashboard.resource', $resourceClass)
                ->name('resources.' . $resourceClass::key() . '.update');

            Route::delete("$base/{record}", [ResourceFormController::class, 'destroy'])
                ->middleware($resourceClass::middleware())
                ->defaults('flashboard.resource', $resourceClass)
                ->name('resources.' . $resourceClass::key() . '.destroy');

            Route::post("$base/actions/{action}", ActionController::class)
                ->middleware($resourceClass::middleware())
                ->defaults('flashboard.resource', $resourceClass)
                ->name('resources.' . $resourceClass::key() . '.actions.index');

            Route::post("$base/{record}/actions/{action}", ActionController::class)
                ->middleware($resourceClass::middleware())
                ->defaults('flashboard.resource', $resourceClass)
                ->name('resources.' . $resourceClass::key() . '.actions.record');
        }
    }
}
