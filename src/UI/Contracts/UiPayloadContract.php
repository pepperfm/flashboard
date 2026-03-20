<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\UI\Contracts;

interface UiPayloadContract
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
