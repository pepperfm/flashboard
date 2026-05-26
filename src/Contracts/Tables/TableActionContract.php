<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Contracts\Tables;

interface TableActionContract
{
    public function key(): string;

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
