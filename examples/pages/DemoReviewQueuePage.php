<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Examples\Pages;

use Pepperfm\Flashboard\Contracts\Pages\PageType;
use Pepperfm\Flashboard\Core\Pages\CustomPage;

final class DemoReviewQueuePage extends CustomPage
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

    public static function workspaceDescription(): ?string
    {
        return 'Example operator workspace for moderation and review flows.';
    }

    public static function workspaceActions(): array
    {
        return [
            ['key' => 'refresh', 'label' => 'Refresh queue'],
        ];
    }
}
