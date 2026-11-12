<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Ai\Mcp\Tools;

use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('inspect_model')]
#[Description('Inspect columns and relation return types without reading records.')]
#[IsReadOnly]
final class InspectModelTool extends HarnessTool
{
    public function schema(\Illuminate\Contracts\JsonSchema\JsonSchema $schema): array
    {
        return ['model' => $schema->string()->description('Fully qualified App Models class.')->required()];
    }

    public function handle(\Laravel\Mcp\Request $request): \Laravel\Mcp\Response
    {
        return $this->respond(static fn (): array => app(\Pepperfm\Flashboard\Ai\ModelCatalog::class)->inspect((string) $request->get('model', '')));
    }
}
