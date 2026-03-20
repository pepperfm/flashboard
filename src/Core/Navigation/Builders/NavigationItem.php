<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Core\Navigation\Builders;

use Pepperfm\Flashboard\Contracts\Navigation\NavigationItemContract;

final class NavigationItem implements NavigationItemContract
{
    private ?string $label = null;

    private ?string $icon = null;

    private ?string $group = null;

    private ?string $badge = null;

    private bool $visible = true;

    /**
     * @var list<NavigationItemContract>
     */
    private array $children = [];

    private function __construct(
        private readonly string $key,
    ) {
    }

    public static function make(string $key): static
    {
        return new static($key);
    }

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function icon(?string $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    public function group(?string $group): static
    {
        $this->group = $group;

        return $this;
    }

    public function badge(?string $badge): static
    {
        $this->badge = $badge;

        return $this;
    }

    public function visible(bool $condition): static
    {
        $this->visible = $condition;

        return $this;
    }

    public function children(array $children): static
    {
        $this->children = $children;

        return $this;
    }

    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label ?? str($this->key)->headline()->toString(),
            'icon' => $this->icon,
            'group' => $this->group,
            'badge' => $this->badge,
            'visible' => $this->visible,
            'children' => array_values(array_map(
                static fn(NavigationItemContract $item): array => $item->toArray(),
                $this->children,
            )),
        ];
    }
}
