<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Examples\Resources;

use App\Models\Order;
use Pepperfm\Flashboard\Contracts\Detail\DetailContract;
use Pepperfm\Flashboard\Contracts\Forms\FormContract;
use Pepperfm\Flashboard\Contracts\Resources\Resource;
use Pepperfm\Flashboard\Contracts\Tables\TableContract;
use Pepperfm\Flashboard\Core\Actions\Builders\Action;
use Pepperfm\Flashboard\Core\Detail\Entries\TextEntry;
use Pepperfm\Flashboard\Core\Forms\Fields\Select;
use Pepperfm\Flashboard\Core\Forms\Fields\TextInput;
use Pepperfm\Flashboard\Core\Forms\Layout\Section;
use Pepperfm\Flashboard\Core\Relations\RelationDefinition;
use Pepperfm\Flashboard\Core\Tables\Columns\BadgeColumn;
use Pepperfm\Flashboard\Core\Tables\Columns\TextColumn;
use Pepperfm\Flashboard\Core\Tables\Filters\SelectFilter;

final class OrdersResource extends Resource
{
    public static function model(): string
    {
        return Order::class;
    }

    public static function table(TableContract $table): TableContract
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('ID')->sortable(),
                BadgeColumn::make('status')->label('Status')->sortable()->searchable(),
                TextColumn::make('created_at')->label('Created'),
            ])
            ->filters([
                SelectFilter::make('status')->label('Status'),
            ]);
    }

    public static function form(FormContract $form): FormContract
    {
        return $form
            ->sections([
                Section::make('main')->label('Main')->schema([
                    Select::make('status')->label('Status')->required(),
                    TextInput::make('notes')->label('Notes'),
                ]),
            ])
            ->rules([
                'status' => ['required', 'string'],
            ]);
    }

    public static function infolist(DetailContract $detail): DetailContract
    {
        return $detail->entries([
            TextEntry::make('id')->label('ID'),
            TextEntry::make('status')->label('Status'),
            TextEntry::make('notes')->label('Notes'),
        ]);
    }

    public static function actions(): array
    {
        return [
            Action::make('archive')
                ->label('Archive')
                ->requiresConfirmation()
                ->successMessage('Order archived.'),
        ];
    }

    public static function relations(): array
    {
        return [
            RelationDefinition::make('items')
                ->label('Items')
                ->titleAttribute('name'),
        ];
    }
}
