<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Tests\Fixtures\Resources;

use Pepperfm\Flashboard\Contracts\Resources\Resource;
use Pepperfm\Flashboard\Core\Relations\HasMany;
use Pepperfm\Flashboard\Core\Relations\HasOne;
use Pepperfm\Flashboard\Tests\Fixtures\Models\RelationManagerOrder;

final class RelationManagerOrderResource extends Resource
{
    public static function model(): string
    {
        return RelationManagerOrder::class;
    }

    public static function relations(): array
    {
        return [
            HasOne::make('profile', 'Profile')
                ->resource(RelationManagerProfileResource::class)
                ->attachable()
                ->detachable()
                ->replaceable(),
            HasMany::make('items', 'Items')
                ->resource(RelationManagerOrderItemResource::class)
                ->searchable(['name', 'sku'])
                ->perPage(5)
                ->attachable()
                ->detachable()
                ->syncable(),
        ];
    }
}
