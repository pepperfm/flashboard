<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Core\Runtime\Assemblers;

use Pepperfm\Flashboard\Contracts\Resources\Resource;
use Pepperfm\Flashboard\Core\Runtime\Payloads\TablePayload;
use Pepperfm\Flashboard\Core\Tables\Builders\Table;

final class TablePayloadAssembler
{
    /**
     * @param class-string<Resource> $resourceClass
     */
    public function assemble(string $resourceClass): TablePayload
    {
        return new TablePayload(
            $resourceClass::table(Table::make())->toArray(),
        );
    }
}
