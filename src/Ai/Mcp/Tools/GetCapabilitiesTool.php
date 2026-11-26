<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Ai\Mcp\Tools;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Pepperfm\Flashboard\Ai\Inspection\CapabilityCatalog;

#[Name('get_capabilities')]
#[Description('Describe the installed Flashboard version, schema, supported generator features and write safety.')]
#[IsReadOnly]
final class GetCapabilitiesTool extends HarnessTool
{
    public function handle(Request $request): Response
    {
        return $this->respondInspection(static fn(): array => app(CapabilityCatalog::class)->describe());
    }
}
