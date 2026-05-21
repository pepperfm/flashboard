<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Core\Runtime\Assemblers;

use Illuminate\Container\Attributes\Singleton;
use Pepperfm\Flashboard\Contracts\Actions\ActionContract;
use Pepperfm\Flashboard\Core\Runtime\Payloads\ActionPayload;

#[Singleton]
final class ActionPayloadAssembler
{
    public function assemble(ActionContract|array $action): ActionPayload
    {
        return new ActionPayload(
            $action instanceof ActionContract ? $action->toArray() : $action,
        );
    }
}
