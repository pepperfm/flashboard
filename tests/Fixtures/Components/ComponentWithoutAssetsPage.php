<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Tests\Fixtures\Components;

use Pepperfm\Flashboard\Contracts\Pages\PageType;
use Pepperfm\Flashboard\Core\Pages\CustomPage;

final class ComponentWithoutAssetsPage extends CustomPage
{
    public static function component(): string
    {
        return 'assetless-review';
    }

    public static function title(): string
    {
        return 'Assetless Review';
    }

    public static function type(): PageType
    {
        return PageType::Custom;
    }

    public static function uri(): string
    {
        return 'queues/assetless-review';
    }
}
