<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Tests\Fixtures\Resources;

use Pepperfm\Flashboard\Contracts\Resources\Resource;
use Pepperfm\Flashboard\Contracts\Tables\TableContract;
use Pepperfm\Flashboard\Core\Tables\Columns\TextColumn;
use Pepperfm\Flashboard\Tests\Fixtures\Models\LazyFilterOptionRecord;

final class VisibilityRestrictedListResource extends Resource
{
    public static function model(): string
    {
        return LazyFilterOptionRecord::class;
    }

    public static function policy(): string
    {
        return \stdClass::class;
    }

    public static function fieldAbilityMap(): array
    {
        return [
            'status_label' => 'view-hidden',
        ];
    }

    public static function table(TableContract $table): TableContract
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('ID')->sortable(),
                TextColumn::make('status')->label('Status')->searchable()->sortable(),
                TextColumn::make('status_label')->label('Status label')->searchable()->sortable(),
            ]);
    }
}
