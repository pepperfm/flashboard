<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Integration\Laravel\Http\Controllers\Auth;

use Pepperfm\Flashboard\Integration\Laravel\Auth\PanelAuthenticator;

final readonly class LogoutController
{
    public function __construct(
        private PanelAuthenticator $authenticator,
    ) {
    }

    public function __invoke(\Illuminate\Http\Request $request): \Illuminate\Http\RedirectResponse
    {
        $this->authenticator->logout();

        return redirect()->route((string) config('flashboard.route_name_prefix', 'flashboard.') . 'auth.login');
    }
}
