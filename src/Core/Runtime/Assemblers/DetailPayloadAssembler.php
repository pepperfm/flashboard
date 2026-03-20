<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Core\Runtime\Assemblers;

use Pepperfm\Flashboard\Contracts\Resources\Resource;
use Pepperfm\Flashboard\Core\Detail\Builders\Detail;
use Pepperfm\Flashboard\Core\Runtime\Payloads\DetailPayload;

final class DetailPayloadAssembler
{
    /**
     * @param class-string<Resource> $resourceClass
     */
    public function assemble(string $resourceClass): DetailPayload
    {
        return new DetailPayload(
            $resourceClass::detail(Detail::make())->toArray(),
        );
    }
}
