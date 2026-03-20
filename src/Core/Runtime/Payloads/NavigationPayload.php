<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Core\Runtime\Payloads;

final readonly class NavigationPayload
{
    /**
     * @param list<array<string, mixed>> $items
     */
    public function __construct(
        private array $items,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function toArray(): array
    {
        return $this->items;
    }
}
