<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Core\Runtime\Metadata;

use Pepperfm\Flashboard\Contracts\Panel\PanelDefinitionContract;
use Pepperfm\Flashboard\Core\Runtime\Screens\ResolvedScreen;

final class RuntimeMetadataFactory
{
    public function make(PanelDefinitionContract $panel, ResolvedScreen $screen): RuntimeMetadata
    {
        return new RuntimeMetadata(
            panelName: $panel->name(),
            panelPath: $panel->path(),
            routeName: $screen->routeName(),
            screenKey: $screen->key(),
            screenKind: $screen->kind(),
            pageClass: $screen->pageClass(),
            resourceClass: $screen->resourceClass(),
        );
    }
}
