<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Core\Runtime\Metadata;

use Pepperfm\Flashboard\Core\Runtime\Screens\ScreenKind;

final readonly class RuntimeMetadata
{
    public function __construct(
        private string $panelName,
        private string $panelPath,
        private string $routeName,
        private string $screenKey,
        private ScreenKind $screenKind,
        private ?string $pageClass,
        private ?string $resourceClass,
    ) {
    }

    public function toArray(): array
    {
        return [
            'panel_name' => $this->panelName,
            'panel_path' => $this->panelPath,
            'route_name' => $this->routeName,
            'screen_key' => $this->screenKey,
            'screen_kind' => $this->screenKind->value,
            'page_class' => $this->pageClass,
            'resource_class' => $this->resourceClass,
        ];
    }
}
