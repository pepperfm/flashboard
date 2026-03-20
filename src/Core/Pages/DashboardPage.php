<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Core\Pages;

use Pepperfm\Flashboard\Contracts\Pages\Page;
use Pepperfm\Flashboard\Contracts\Pages\PageType;

final class DashboardPage extends Page
{
    public static function key(): string
    {
        return 'dashboard';
    }

    public static function title(): string
    {
        return 'Dashboard';
    }

    public static function type(): PageType
    {
        return PageType::Dashboard;
    }

    public static function uri(): string
    {
        return '/';
    }
}
