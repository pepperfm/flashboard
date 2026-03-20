<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Contracts\Pages;

abstract class Page implements PageDefinitionContract
{
    public static function key(): string
    {
        return str(class_basename(static::class))->kebab()->toString();
    }

    public static function navigationLabel(): ?string
    {
        return static::title();
    }

    public static function navigationGroup(): ?string
    {
        return null;
    }

    /**
     * @return list<string>
     */
    public static function middleware(): array
    {
        return [];
    }

    public static function isNavigable(): bool
    {
        return true;
    }

    public static function canAccess(?\Illuminate\Contracts\Auth\Authenticatable $user = null): bool
    {
        return true;
    }
}
