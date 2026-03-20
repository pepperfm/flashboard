<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Integration\Laravel\Auth;

use Illuminate\Contracts\Auth\Factory as AuthFactoryContract;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Support\Arr;

final readonly class PanelAuthenticator
{
    public function __construct(
        private AuthFactoryContract $authFactory,
    ) {
    }

    public function guard(): StatefulGuard
    {
        $guard = config('flashboard.guard');

        $resolvedGuard = $this->authFactory->guard(is_string($guard) && $guard !== '' ? $guard : null);
        if (!$resolvedGuard instanceof StatefulGuard) {
            throw new \LogicException('Flashboard requires a stateful Laravel auth guard.');
        }

        return $resolvedGuard;
    }

    public function user(): ?\Illuminate\Contracts\Auth\Authenticatable
    {
        return $this->guard()->user();
    }

    public function check(): bool
    {
        return $this->guard()->check();
    }

    public function attempt(array $payload): bool
    {
        $username = (string) config('flashboard.auth.username', 'email');
        $password = (string) config('flashboard.auth.password', 'password');
        $rememberKey = (string) config('flashboard.auth.remember_key', 'remember');
        $remember = (bool) Arr::get($payload, $rememberKey, false);

        return $this->guard()->attempt([
            $username => Arr::get($payload, $username),
            $password => Arr::get($payload, $password),
        ], $remember);
    }

    public function logout(): void
    {
        $this->guard()->logout();
        session()->invalidate();
        session()->regenerateToken();
    }
}
