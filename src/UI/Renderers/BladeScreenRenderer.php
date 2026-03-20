<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\UI\Renderers;

use Pepperfm\Flashboard\Contracts\Panel\PanelDefinitionContract;
use Pepperfm\Flashboard\UI\Contracts\ScreenRendererContract;
use Pepperfm\Flashboard\UI\Panel\PanelShell;

final class BladeScreenRenderer implements ScreenRendererContract
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
        return response()->view($shell->view(), [
            'layout' => $layout,
            'panel' => $panel,
            'payload' => $payload,
            'shell' => $shell,
            'user' => $user,
            'version' => $version,
        ]);
    }
}
