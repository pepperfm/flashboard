<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Core\Runtime\Workspaces;

use Illuminate\Container\Attributes\Singleton;
use Pepperfm\Flashboard\Contracts\Pages\CustomPageContract;

#[Singleton]
final class WorkspacePayloadAssembler
{
    /**
     * @param class-string<CustomPageContract> $pageClass
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function assemble(string $pageClass, array $context = []): array
    {
        return $pageClass::workspace($context);
    }
}
