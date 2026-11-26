<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Integration\Laravel\Diagnostics;

use Laravel\Mcp\Facades\Mcp;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Routing\Router;
use Pepperfm\Flashboard\Integration\Laravel\Auth\PanelAuthenticator;
use Pepperfm\Flashboard\Integration\Laravel\Http\Controllers\Auth\LoginController;
use Pepperfm\Flashboard\Integration\Laravel\Http\Controllers\Auth\LogoutController;
use Pepperfm\Flashboard\Integration\Laravel\Http\Controllers\Auth\ShowLoginController;
use Pepperfm\Flashboard\Integration\Laravel\Http\Controllers\PanelScreenController;
use Pepperfm\Flashboard\Contracts\Panel\PanelDefinitionContract;

final readonly class PanelConfigurationChecks
{
    public function __construct(
        private Application $app,
        private Router $router,
        private Gate $gate,
        private PanelAuthenticator $authenticator,
        private PanelDefinitionContract $panel
    ) {
    }

    /**
     * @return list<Diagnostic>
     */
    public function inspect(): array
    {
        return [
            $this->framework(),
            $this->routes(),
            $this->middleware(),
            $this->guard(),
            $this->accessGate(),
            $this->mcp(),
        ];
    }

    private function framework(): Diagnostic
    {
        $supported = str_starts_with($this->app->version(), '13.');

        return $supported
            ? new Diagnostic('stack', DiagnosticSeverity::Ok, 'Laravel 13 is supported. PHP ' . PHP_VERSION . ' (enforced by Composer).')
            : new Diagnostic('stack', DiagnosticSeverity::Error, 'Use PHP 8.5+ and Laravel 13.');
    }

    private function routes(): Diagnostic
    {
        $routes = $this->router->getRoutes();
        $prefix = $this->panel->routeNamePrefix();

        $expected = [
            'auth.login' => ShowLoginController::class,
            'auth.attempt' => LoginController::class,
            'auth.logout' => LogoutController::class,
            'home' => PanelScreenController::class,
        ];

        foreach ($expected as $name => $controller) {
            $route = $routes->getByName($prefix.$name);
            if ($route === null || $route->getControllerClass() !== $controller) {
                return new Diagnostic('panel_routes', DiagnosticSeverity::Error, 'A required panel route is missing or points to a different controller. Check route registration.');
            }
        }

        return new Diagnostic('panel_routes', DiagnosticSeverity::Ok, 'Required panel routes are registered.');
    }

    private function middleware(): Diagnostic
    {
        // Laravel synchronizes middleware aliases and groups when the HTTP kernel is resolved.
        try {
            $this->app->make(Kernel::class);
        } catch (\Throwable) {
            return new Diagnostic(
                code: 'panel_middleware',
                severity: DiagnosticSeverity::Error,
                message: 'The HTTP kernel could not be resolved. Check application middleware configuration.'
            );
        }
        $configured = [...$this->panel->webMiddleware(), ...$this->panel->authMiddleware()];
        $aliases = $this->router->getMiddleware();
        $groups = $this->router->getMiddlewareGroups();

        foreach ($configured as $middleware) {
            $name = explode(':', $middleware, 2)[0];

            if (!isset($aliases[$name]) && !isset($groups[$name]) && !class_exists($name)) {
                return new Diagnostic('panel_middleware', DiagnosticSeverity::Error, 'A configured panel middleware alias or class is missing.');
            }
        }

        return new Diagnostic('panel_middleware', DiagnosticSeverity::Ok, 'Configured panel middleware names resolve; custom behavior is not verified.');
    }

    private function guard(): Diagnostic
    {
        try {
            $this->authenticator->guard();
        } catch (\Throwable) {
            return new Diagnostic('panel_guard', DiagnosticSeverity::Error, 'The configured panel guard must resolve to a stateful guard.');
        }

        return new Diagnostic('panel_guard', DiagnosticSeverity::Ok, 'The panel guard resolves to a stateful guard.');
    }

    private function accessGate(): Diagnostic
    {
        $ability = config('flashboard.auth.access_ability', 'accessFlashboard');

        if (!is_string($ability) || trim($ability) === '') {
            return new Diagnostic('panel_gate', DiagnosticSeverity::Error, 'Configure a non-empty panel access ability.');
        }

        return $this->gate->has($ability)
            ? new Diagnostic('panel_gate', DiagnosticSeverity::Ok, 'The panel access ability is registered; user access is not evaluated.')
            : new Diagnostic('panel_gate', DiagnosticSeverity::Warning, 'Panel access ability is not explicitly registered. Verify a Gate definition or a deliberate Gate before callback.');
    }

    private function mcp(): Diagnostic
    {
        if (!config('flashboard.ai.mcp_enabled', false)) {
            return new Diagnostic('mcp', DiagnosticSeverity::Ok, 'Local MCP is disabled (opt-in).');
        }

        return class_exists(Mcp::class)
            ? new Diagnostic('mcp', DiagnosticSeverity::Ok, 'Local MCP is enabled; verify the stdio connection separately.')
            : new Diagnostic('mcp', DiagnosticSeverity::Error, 'Local MCP is enabled but the Laravel MCP package is unavailable.');
    }
}
