<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Tests\Fixtures\Components;

use Pepperfm\Flashboard\Contracts\Pages\PageType;
use Pepperfm\Flashboard\Core\Pages\CustomPage;

final class ComponentReviewQueuePage extends CustomPage
{
    public static function title(): string
    {
        return 'Component Review Queue';
    }

    public static function type(): PageType
    {
        return PageType::Custom;
    }

    public static function uri(): string
    {
        return 'queues/component-review/{record}';
    }

    public static function component(): string
    {
        return 'review-queue';
    }

    public static function componentProps(array $context = []): array
    {
        return [
            'initial_filters' => $context['query'] ?? [],
            'record' => $context['route_parameters']['record'] ?? null,
            'screen' => $context['page_key'] ?? null,
        ];
    }

    public static function componentAssets(): array
    {
        return [
            'scripts' => [
                '/build/flashboard-pages.js',
                [
                    'url' => '/build/flashboard-classic-page.js',
                    'module' => false,
                ],
            ],
            'styles' => [
                '/build/flashboard-pages.css',
            ],
        ];
    }
}
