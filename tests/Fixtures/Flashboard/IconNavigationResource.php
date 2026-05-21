<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Tests\Fixtures\Flashboard;

use Pepperfm\Flashboard\Contracts\Resources\Resource;

final class IconNavigationResource extends Resource
{
    public static function model(): string
    {
        return \Illuminate\Database\Eloquent\Model::class;
    }

    public static function navigationIcon(): string
    {
        return 'lucide:annoyed';
    }
}
