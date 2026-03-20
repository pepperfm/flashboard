<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Integration\Laravel\Http\Middleware;

use Pepperfm\Flashboard\Integration\Laravel\Auth\PanelAuthenticator;

final readonly class AuthenticatePanelUser
{
    public function __construct(
        private PanelAuthenticator $authenticator,
    ) {
    }

    public function handle(\Illuminate\Http\Request $request, \Closure $next): mixed
    {
        if ($this->authenticator->check()) {
            return $next($request);
        }
        if ($request->expectsJson()) {
            abort(401);
        }

        return redirect()->route((string) config('flashboard.route_name_prefix', 'flashboard.') . 'auth.login');
    }
}
