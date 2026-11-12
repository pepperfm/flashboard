<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Contracts\Pages;

interface ComponentPageContract extends PageDefinitionContract
{
    public static function component(): ?string;

    /**
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public static function componentProps(array $context = []): array;

    /**
     * @return array{scripts?: list<mixed>, styles?: list<mixed>}
     */
    public static function componentAssets(): array;
}
