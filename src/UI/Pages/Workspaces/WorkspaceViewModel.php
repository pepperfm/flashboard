<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\UI\Pages\Workspaces;

final readonly class WorkspaceViewModel
{
    /**
     * @param array<string, mixed> $workspace
     */
    public function __construct(
        private array $workspace,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->workspace;
    }
}
