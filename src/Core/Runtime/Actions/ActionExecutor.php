<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Core\Runtime\Actions;

use Illuminate\Container\Attributes\Singleton;
use Pepperfm\Flashboard\Contracts\Actions\ActionContract;

#[Singleton]
final class ActionExecutor
{
    /**
     * @param array<string, mixed> $arguments
     */
    public function execute(ActionContract $action, array $arguments = []): ActionExecutionResult
    {
        return $action->execute($arguments);
    }
}
