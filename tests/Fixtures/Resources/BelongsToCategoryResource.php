<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Tests\Fixtures\Resources;

use Pepperfm\Flashboard\Contracts\Detail\DetailContract;
use Pepperfm\Flashboard\Contracts\Resources\Resource;
use Pepperfm\Flashboard\Core\Detail\Entries\TextEntry;
use Pepperfm\Flashboard\Tests\Fixtures\Models\BelongsToCategory;

final class BelongsToCategoryResource extends Resource
{
    public static function model(): string
    {
        return BelongsToCategory::class;
    }

    public static function detail(DetailContract $detail): DetailContract
    {
        return $detail->entries([
            TextEntry::make('name', 'Name'),
        ]);
    }
}
