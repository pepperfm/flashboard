<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Core\Runtime\Assemblers;

use Illuminate\Container\Attributes\Singleton;
use Pepperfm\Flashboard\Contracts\Resources\Resource;
use Pepperfm\Flashboard\Core\Runtime\Payloads\TablePayload;
use Pepperfm\Flashboard\Core\Tables\Builders\Table;

#[Singleton]
final class TablePayloadAssembler
{
    /**
     * @param class-string<Resource> $resourceClass
     */
    public function assemble(string $resourceClass): TablePayload
    {
        return new TablePayload(
            $this->table($resourceClass)->toArray(),
        );
    }

    /**
     * @param class-string<Resource> $resourceClass
     */
    public function table(string $resourceClass): Table
    {
        $table = $resourceClass::table(Table::make());

        if (!$table instanceof Table) {
            throw new \UnexpectedValueException('Resource table definitions must return the Flashboard table builder instance.');
        }

        return $table;
    }
}
