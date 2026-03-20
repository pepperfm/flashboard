<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Contracts\Extensions;

interface QueryExtensionContract
{
    public function extend(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder;
}
