<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Integration\Laravel\Http\Controllers;

final readonly class PanelHomeController
{
    public function __invoke(\Illuminate\Http\Request $request): \Symfony\Component\HttpFoundation\Response
    {
        return app(PanelScreenController::class)($request);
    }
}
