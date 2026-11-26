<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Ai\Mcp\Tools;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Pepperfm\Flashboard\Ai\Inspection\PanelInspector;

#[Name('inspect_panel')]
#[Description('Inspect registered panel resources and read-only doctor diagnostics.')]
#[IsReadOnly]
final class InspectPanelTool extends HarnessTool
{
    public function handle(Request $request): Response
    {
        return $this->respondInspection(static fn(): array => app(PanelInspector::class)->inspect());
    }
}
