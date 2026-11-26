<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Tests\Fixtures\Resources;

use Illuminate\Database\Eloquent\Builder;
use Pepperfm\Flashboard\Contracts\Resources\Resource;
use Pepperfm\Flashboard\Tests\Fixtures\RelationSelectionPolicy;
use Pepperfm\Flashboard\Tests\Fixtures\Models\BelongsToManyTag;

final class SelectionTagResource extends Resource
{
    public static function model(): string
    {
        return BelongsToManyTag::class;
    }

    public static function policy(): string
    {
        return RelationSelectionPolicy::class;
    }

    public static function fieldAbilityMap(): array
    {
        return ['name' => 'view-name'];
    }

    public static function query(): Builder
    {
        return parent::query()->where('slug', '!=', 'outside');
    }
}
