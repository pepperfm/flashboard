<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Core\Runtime\Assemblers;

use Pepperfm\Flashboard\Contracts\Resources\Resource;
use Pepperfm\Flashboard\Core\Forms\Builders\Form;
use Pepperfm\Flashboard\Core\Runtime\Payloads\FormPayload;

final class FormPayloadAssembler
{
    /**
     * @param class-string<Resource> $resourceClass
     */
    public function assemble(string $resourceClass): FormPayload
    {
        return new FormPayload(
            $resourceClass::form(Form::make())->toArray(),
        );
    }
}
