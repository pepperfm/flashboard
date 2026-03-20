<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Integration\Laravel\Http\Controllers\Auth;

final readonly class ShowLoginController
{
    public function __invoke(\Illuminate\Http\Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $inertia = inertia();
        $inertia->setRootView('flashboard::panel');

        return $inertia->render('Flashboard/Auth/Login', [
            'attemptUrl' => route(config('flashboard.route_name_prefix', 'flashboard.') . 'auth.attempt'),
            'error' => $request->session()->get('errors')?->first(),
            'panelName' => config('flashboard.name', 'Flashboard'),
            'usernameField' => config('flashboard.auth.username', 'email'),
            'value' => $request->old((string) config('flashboard.auth.username', 'email')),
        ])->toResponse($request);
    }
}
