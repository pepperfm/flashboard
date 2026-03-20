<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Contracts\Pages;

interface CustomPageContract extends PageDefinitionContract
{
    public static function workspaceKey(): string;

    public static function workspaceDescription(): ?string;

    /**
     * @return list<array<string, mixed>>
     */
    public static function workspaceActions(): array;

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public static function workspace(array $context = []): array;
}
