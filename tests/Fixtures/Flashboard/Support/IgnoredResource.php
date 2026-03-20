<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Tests\Fixtures\Flashboard\Support;

use Pepperfm\Flashboard\Contracts\Resources\Resource;
use Pepperfm\Flashboard\Contracts\Tables\TableContract;

final class IgnoredResource extends Resource
{
    public static function model(): string
    {
        return \Illuminate\Database\Eloquent\Model::class;
    }

    public static function table(TableContract $table): TableContract
    {
        return $table;
    }
}
