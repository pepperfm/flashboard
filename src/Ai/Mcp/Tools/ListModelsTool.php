<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Ai\Mcp\Tools;

use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_models')]
#[Description('List Eloquent models in the host App Models directory without reading any records.')]
#[IsReadOnly]
final class ListModelsTool extends HarnessTool
{
    public function handle(\Laravel\Mcp\Request $request): \Laravel\Mcp\Response
    {
        return $this->respond(
            static fn (): array => ['models' => app(\Pepperfm\Flashboard\Ai\ModelCatalog::class)->all()]
        );
    }
}
