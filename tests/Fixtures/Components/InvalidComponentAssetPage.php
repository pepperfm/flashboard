<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Tests\Fixtures\Components;

use Pepperfm\Flashboard\Contracts\Pages\PageType;
use Pepperfm\Flashboard\Core\Pages\CustomPage;

final class InvalidComponentAssetPage extends CustomPage
{
    public static function title(): string
    {
        return 'Invalid Asset Page';
    }

    public static function type(): PageType
    {
        return PageType::Custom;
    }

    public static function uri(): string
    {
        return 'invalid-asset-page';
    }

    public static function component(): string
    {
        return 'invalid-asset-page';
    }

    public static function componentAssets(): array
    {
        return [
            'scripts' => [
                [
                    'src' => '/missing-url.js',
                ],
            ],
            'styles' => [],
        ];
    }
}
