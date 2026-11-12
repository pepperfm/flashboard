<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Ai\Mcp\Tools;

use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_resources')]
#[Description('List registered Flashboard Resources and backing models.')]
#[IsReadOnly]
final class ListResourcesTool extends HarnessTool
{
    public function handle(\Laravel\Mcp\Request $request): \Laravel\Mcp\Response
    {
        return $this->respond(static function (): array {
            $resources = app(\Pepperfm\Flashboard\Core\Registry\ResourceRegistry::class)->all();

            return ['resources' => array_map(static fn (string $resource): array => [
                'class' => $resource,
                'key' => $resource::key(),
                'model' => $resource::model(),
            ], $resources)];
        });
    }
}
