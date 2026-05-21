<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Tests\Fixtures\Resources;

use Pepperfm\Flashboard\Contracts\Resources\Resource;
use Pepperfm\Flashboard\Contracts\Tables\Filters\SelectFilterOptionsQuery;
use Pepperfm\Flashboard\Contracts\Tables\Filters\SelectFilterOptionsResult;
use Pepperfm\Flashboard\Contracts\Tables\TableContract;
use Pepperfm\Flashboard\Core\Tables\Columns\TextColumn;
use Pepperfm\Flashboard\Core\Tables\Filters\SelectFilter;
use Pepperfm\Flashboard\Tests\Fixtures\Models\LazyFilterOptionRecord;

final class LazyFilterOptionsResource extends Resource
{
    public static function model(): string
    {
        return LazyFilterOptionRecord::class;
    }

    public static function table(TableContract $table): TableContract
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('ID'),
                TextColumn::make('status')->label('Status'),
            ])
            ->filters([
                SelectFilter::make('status')->label('Status')->lazy(perPage: 2)->options([
                    'draft' => 'Draft',
                    'published' => 'Published',
                ]),
                SelectFilter::make('statuses')
                    ->label('Statuses')
                    ->queryColumn('status')
                    ->lazy(perPage: 2)
                    ->multiple(),
                SelectFilter::make('status_id')
                    ->label('Status by ID')
                    ->lazy(perPage: 2)
                    ->optionValue('id')
                    ->optionLabel('status'),
                SelectFilter::make('sku')
                    ->label('SKU')
                    ->queryColumn('id')
                    ->lazy(
                        static fn (SelectFilterOptionsQuery $query): SelectFilterOptionsResult => SelectFilterOptionsResult::make([
                            [
                                'label' => sprintf(
                                    'SKU %s page %d selected %s',
                                    $query->search,
                                    $query->page,
                                    (string) $query->selected,
                                ),
                                'value' => 99,
                            ],
                        ], true, $query->page + 1),
                        perPage: 5,
                    ),
                SelectFilter::make('sku_multi')
                    ->label('SKU multi')
                    ->queryColumn('id')
                    ->lazy(
                        static fn (SelectFilterOptionsQuery $query): SelectFilterOptionsResult => SelectFilterOptionsResult::make([
                            [
                                'label' => sprintf(
                                    'SKU selected %s first %s',
                                    implode('|', array_map('strval', $query->selectedValues)),
                                    (string) $query->selected,
                                ),
                                'value' => 100,
                            ],
                        ]),
                    )
                    ->multiple(),
                SelectFilter::make('eager')->label('Eager')->options([
                    'yes' => 'Yes',
                ]),
            ]);
    }
}
