<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Tests\Fixtures\Flashboard;

use Pepperfm\Flashboard\Contracts\Resources\Resource;
use Pepperfm\Flashboard\Contracts\Tables\TableContract;

final class UsersResource extends Resource
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
