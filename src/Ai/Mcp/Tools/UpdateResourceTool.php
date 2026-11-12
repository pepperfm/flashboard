<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Ai\Mcp\Tools;

use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('update_resource')]
#[Description('Update an unmodified AI generated Resource only when preview hashes match.')]
final class UpdateResourceTool extends HarnessTool
{
    public function schema(\Illuminate\Contracts\JsonSchema\JsonSchema $schema): array
    {
        return [
            ...$this->specificationSchema($schema),
            'expected_sha' => $schema->string()->description('Exact expected_sha returned by preview_resource.')->required(),
            'proposal_hash' => $schema->string()->description('Exact proposal_hash returned by preview_resource.')->required(),
        ];
    }

    public function handle(\Laravel\Mcp\Request $request): \Laravel\Mcp\Response
    {
        return $this->respond(static fn (): array => app(\Pepperfm\Flashboard\Ai\ResourceManager::class)->update(
            $request->all(),
            (string) $request->get('expected_sha', ''),
            (string) $request->get('proposal_hash', ''),
        ));
    }
}
