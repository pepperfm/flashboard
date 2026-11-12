<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Ai\Mcp\Tools;

use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_resource_spec')]
#[Description('Read the manifest of an unchanged AI-generated Resource so it can be extended safely.')]
#[IsReadOnly]
final class GetResourceSpecTool extends HarnessTool
{
    public function schema(\Illuminate\Contracts\JsonSchema\JsonSchema $schema): array
    {
        return ['resource' => $schema->string()->description('AI-generated Resource class name, e.g. Orders.')->required()];
    }

    public function handle(\Laravel\Mcp\Request $request): \Laravel\Mcp\Response
    {
        return $this->respond(static fn (): array => [
            'specification' => app(\Pepperfm\Flashboard\Ai\ResourceChangeManager::class)
                ->specification((string) $request->get('resource', '')),
        ]);
    }
}
