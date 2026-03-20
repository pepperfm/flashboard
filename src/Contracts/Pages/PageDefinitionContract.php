<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Contracts\Pages;

interface PageDefinitionContract
{
    public static function key(): string;

    public static function title(): string;

    public static function type(): PageType;

    public static function uri(): string;

    public static function navigationLabel(): ?string;

    public static function navigationGroup(): ?string;

    /**
     * @return list<string>
     */
    public static function middleware(): array;

    public static function isNavigable(): bool;

    public static function canAccess(?\Illuminate\Contracts\Auth\Authenticatable $user = null): bool;
}
