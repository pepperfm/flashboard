<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Tests\Fixtures\Components;

use Pepperfm\Flashboard\Contracts\Pages\PageType;
use Pepperfm\Flashboard\Core\Pages\CustomPage;

final class UnsafeComponentAssetPage extends CustomPage
{
    public static function title(): string
    {
        return 'Unsafe Asset Page';
    }

    public static function type(): PageType
    {
        return PageType::Custom;
    }

    public static function uri(): string
    {
        return 'unsafe-asset-page';
    }

    public static function component(): string
    {
        return 'unsafe-asset-page';
    }

    public static function componentAssets(): array
    {
        return [
            'scripts' => [
                [
                    'url' => 'data:text/javascript,alert(1)',
                ],
            ],
            'styles' => [],
        ];
    }
}
