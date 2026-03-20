<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Tests\Fixtures\Flashboard;

use Pepperfm\Flashboard\Contracts\Pages\PageType;
use Pepperfm\Flashboard\Core\Pages\CustomPage;

final class ReviewQueuePage extends CustomPage
{
    public static function title(): string
    {
        return 'Review Queue';
    }

    public static function type(): PageType
    {
        return PageType::Custom;
    }

    public static function uri(): string
    {
        return 'queues/review';
    }
}
