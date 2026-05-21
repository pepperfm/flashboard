<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\UI\Renderers;

use Illuminate\Container\Attributes\Singleton;
use Pepperfm\Flashboard\Contracts\Panel\PanelDefinitionContract;
use Pepperfm\Flashboard\UI\Contracts\ScreenRendererContract;
use Pepperfm\Flashboard\UI\Panel\PanelShell;

#[Singleton]
final class JsonScreenRenderer implements ScreenRendererContract
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
        return response()->json([
            'layout' => $layout,
            'name' => $panel->name(),
            'path' => $panel->path(),
            'payload' => $payload,
            'route_name_prefix' => $panel->routeNamePrefix(),
            'status' => 'bootstrapped',
            'version' => $version,
        ]);
    }
}
