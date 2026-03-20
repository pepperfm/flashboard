<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Contracts\Extensions;

interface RuntimeHookContract
{
    /**
     * @param array<string, mixed> $context
     */
    public function handle(string $hook, array $context = []): void;
}
