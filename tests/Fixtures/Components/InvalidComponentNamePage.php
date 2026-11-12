<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Tests\Fixtures\Components;

use Pepperfm\Flashboard\Contracts\Pages\PageType;
use Pepperfm\Flashboard\Core\Pages\CustomPage;

final class InvalidComponentNamePage extends CustomPage
{
    public static function title(): string
    {
        return 'Invalid Component Name';
    }

    public static function type(): PageType
    {
        return PageType::Custom;
    }

    public static function uri(): string
    {
        return 'invalid-component-name';
    }

    public static function component(): string
    {
        return 'invalid component name';
    }
}
