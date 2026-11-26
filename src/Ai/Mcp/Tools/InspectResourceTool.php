<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Ai\Mcp\Tools;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Pepperfm\Flashboard\Ai\Inspection\ResourceInspector;

#[Name('inspect_resource')]
#[Description('Inspect structural declarations of a registered Resource without reading records or evaluating actions.')]
#[IsReadOnly]
final class InspectResourceTool extends HarnessTool
{
    public function schema(JsonSchema $schema): array
    {
        return ['resource' => $schema->string()->description('Registered resource class or route key.')->required()];
    }

    public function handle(Request $request): Response
    {
        return $this->respondInspection(static fn(): array => app(ResourceInspector::class)->inspect((string) $request->get('resource', '')));
    }
}
