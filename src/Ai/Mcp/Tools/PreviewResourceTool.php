<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Ai\Mcp\Tools;

use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('preview_resource')]
#[Description('Preview a generated resource and a source diff without changing any files.')]
#[IsReadOnly]
final class PreviewResourceTool extends HarnessTool
{
    public function schema(\Illuminate\Contracts\JsonSchema\JsonSchema $schema): array
    {
        return $this->specificationSchema($schema);
    }

    public function handle(\Laravel\Mcp\Request $request): \Laravel\Mcp\Response
    {
        return $this->respond(static fn (): array => app(\Pepperfm\Flashboard\Ai\ResourceManager::class)->preview($request->all()));
    }
}
