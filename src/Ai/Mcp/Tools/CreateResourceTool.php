<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Ai\Mcp\Tools;

use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('create_resource')]
#[Description('Create a new generated Resource after exact source-hash preview.')]
final class CreateResourceTool extends HarnessTool
{
    public function schema(\Illuminate\Contracts\JsonSchema\JsonSchema $schema): array
    {
        return [
            ...$this->specificationSchema($schema),
            'proposal_hash' => $schema->string()->description('Exact proposal_hash returned by preview_resource.')->required(),
        ];
    }

    public function handle(\Laravel\Mcp\Request $request): \Laravel\Mcp\Response
    {
        return $this->respond(static fn (): array => app(\Pepperfm\Flashboard\Ai\ResourceManager::class)->create(
            $request->all(),
            (string) $request->get('proposal_hash', ''),
        ));
    }
}
