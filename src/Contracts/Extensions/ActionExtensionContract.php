<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Contracts\Extensions;

interface ActionExtensionContract
{
    /**
     * @param array<string, mixed> $action
     * @return array<string, mixed>
     */
    public function extend(array $action): array;
}
