<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Integration\Laravel\Http\Controllers\Auth;

use Pepperfm\Flashboard\Integration\Laravel\Auth\PanelAuthenticator;

final readonly class LoginController
{
    public function __construct(
        private PanelAuthenticator $authenticator,
    ) {
    }

    public function __invoke(\Illuminate\Http\Request $request): \Illuminate\Http\RedirectResponse
    {
        $username = (string) config('flashboard.auth.username', 'email');
        $password = (string) config('flashboard.auth.password', 'password');

        $credentials = $request->validate([
            $username => ['required', 'string'],
            $password => ['required', 'string'],
            (string) config('flashboard.auth.remember_key', 'remember') => ['sometimes', 'boolean'],
        ]);

        if (! $this->authenticator->attempt($credentials)) {
            return redirect()->back()
                ->withInput($request->except($password))
                ->withErrors([
                    $username => 'The provided credentials could not be verified.',
                ]);
        }

        $request->session()->regenerate();

        return redirect()->route((string) config('flashboard.route_name_prefix', 'flashboard.').'home');
    }
}
