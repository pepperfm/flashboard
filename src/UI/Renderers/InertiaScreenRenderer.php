<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\UI\Renderers;

use Pepperfm\Flashboard\Contracts\Panel\PanelDefinitionContract;
use Pepperfm\Flashboard\UI\Contracts\ScreenRendererContract;
use Pepperfm\Flashboard\UI\Panel\PanelShell;

final class InertiaScreenRenderer implements ScreenRendererContract
{
    public function render(
        \Illuminate\Http\Request $request,
        PanelDefinitionContract $panel,
        PanelShell $shell,
        array $layout,
        array $payload,
        ?\Illuminate\Contracts\Auth\Authenticatable $user,
        string $version,
    ): \Symfony\Component\HttpFoundation\Response {
        $inertia = inertia();
        $inertia->setRootView($shell->view());

        return $inertia->render($shell->component(), [
            'layout' => $layout,
            'panel' => [
                'name' => $panel->name(),
                'path' => $panel->path(),
                'route_name_prefix' => $panel->routeNamePrefix(),
            ],
            'payload' => $payload,
            'user' => $user?->getAuthIdentifier(),
            'version' => $version,
        ])->toResponse($request);
    }
}
