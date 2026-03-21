<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Contracts\Schema;

interface SchemaNodeContract
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
