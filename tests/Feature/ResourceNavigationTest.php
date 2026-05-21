<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Tests\Feature;

use Pepperfm\Flashboard\Core\Navigation\Builders\NavigationItem;
use Pepperfm\Flashboard\Tests\Fixtures\Flashboard\IconNavigationResource;
use Pepperfm\Flashboard\Tests\Fixtures\Flashboard\UsersResource;
use Pepperfm\Flashboard\Tests\TestCase;

final class ResourceNavigationTest extends TestCase
{
    public function test_resource_navigation_item_keeps_configured_icon_unmodified(): void
    {
        $item = IconNavigationResource::navigationItem(NavigationItem::make('icon-navigation'))->toArray();

        self::assertSame('lucide:annoyed', $item['icon']);
    }

    public function test_resource_navigation_item_keeps_null_icon_by_default(): void
    {
        $item = UsersResource::navigationItem(NavigationItem::make('users'))->toArray();

        self::assertNull($item['icon']);
    }
}
