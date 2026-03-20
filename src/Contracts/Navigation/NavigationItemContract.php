<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Contracts\Navigation;

interface NavigationItemContract
{
    public static function make(string $key): static;

    public function label(string $label): static;

    public function icon(?string $icon): static;

    public function group(?string $group): static;

    public function badge(?string $badge): static;

    public function visible(bool $condition): static;

    /**
     * @param list<NavigationItemContract> $children
     */
    public function children(array $children): static;

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
