<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Contracts\Schema;

interface KeyedSchemaNodeContract extends SchemaNodeContract
{
    public function key(): string;
}
