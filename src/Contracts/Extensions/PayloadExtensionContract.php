<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Contracts\Extensions;

interface PayloadExtensionContract
{
    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function extend(string $resourceClass, string $screenPage, array $payload): array;
}
