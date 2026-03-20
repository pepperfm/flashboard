<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Core\Pages;

use Pepperfm\Flashboard\Contracts\Pages\CustomPageContract;
use Pepperfm\Flashboard\Contracts\Pages\Page;

abstract class CustomPage extends Page implements CustomPageContract
{
    public static function workspaceKey(): string
    {
        return static::key();
    }

    public static function workspaceDescription(): ?string
    {
        return null;
    }

    public static function workspaceActions(): array
    {
        return [];
    }

    public static function workspace(array $context = []): array
    {
        return [
            'key' => static::workspaceKey(),
            'title' => static::title(),
            'description' => static::workspaceDescription(),
            'actions' => static::workspaceActions(),
            'context' => $context,
        ];
    }
}
