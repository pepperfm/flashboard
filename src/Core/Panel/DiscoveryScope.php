<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Core\Panel;

enum DiscoveryScope: string
{
    case Both = 'both';
    case Resources = 'resources';
    case Pages = 'pages';

    public function discoversResources(): bool
    {
        return $this === self::Both || $this === self::Resources;
    }

    public function discoversPages(): bool
    {
        return $this === self::Both || $this === self::Pages;
    }
}
