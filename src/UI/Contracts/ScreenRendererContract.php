<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\UI\Contracts;

use Pepperfm\Flashboard\Contracts\Panel\PanelDefinitionContract;
use Pepperfm\Flashboard\UI\Panel\PanelShell;

interface ScreenRendererContract
{
    /**
     * @param array<string, mixed> $layout
     * @param array<string, mixed> $payload
     */
    public function render(
        \Illuminate\Http\Request $request,
        PanelDefinitionContract $panel,
        PanelShell $shell,
        array $layout,
        array $payload,
        ?\Illuminate\Contracts\Auth\Authenticatable $user,
        string $version,
    ): \Symfony\Component\HttpFoundation\Response;
}
