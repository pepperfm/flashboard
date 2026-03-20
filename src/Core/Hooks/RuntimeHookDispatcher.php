<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Core\Hooks;

use Pepperfm\Flashboard\Contracts\Extensions\RuntimeHookContract;
use Pepperfm\Flashboard\Contracts\Resources\Resource;

final class RuntimeHookDispatcher
{
    /**
     * @param class-string<Resource> $resourceClass
     * @param array<string, mixed> $context
     */
    public function dispatch(string $resourceClass, string $hook, array $context = []): void
    {
        foreach ($resourceClass::runtimeHooks() as $runtimeHook) {
            if ($runtimeHook instanceof RuntimeHookContract) {
                $runtimeHook->handle($hook, $context);
            }
        }
    }
}
