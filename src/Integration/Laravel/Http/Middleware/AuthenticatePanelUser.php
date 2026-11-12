<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Integration\Laravel\Http\Middleware;

use Illuminate\Contracts\Auth\Access\Gate;
use Pepperfm\Flashboard\Integration\Laravel\Auth\PanelAuthenticator;

final readonly class AuthenticatePanelUser
{
    public function __construct(
        private PanelAuthenticator $authenticator,
        private Gate $gate,
    ) {
    }

    public function handle(\Illuminate\Http\Request $request, \Closure $next): mixed
    {
        if (!$this->authenticator->check()) {
            if ($request->expectsJson()) {
                abort(401);
            }

            return redirect()->route((string) config('flashboard.route_name_prefix', 'flashboard.') . 'auth.login');
        }

        $user = $this->authenticator->user();
        $ability = config('flashboard.auth.access_ability', 'accessFlashboard');

        // A panel is privileged even when individual resources have no policy.
        // A missing/empty ability or an undefined Gate must fail closed.
        abort_unless(
            $user !== null
                && is_string($ability)
                && trim($ability) !== ''
                && $this->gate->forUser($user)->allows($ability),
            403,
        );

        return $next($request);
    }
}
