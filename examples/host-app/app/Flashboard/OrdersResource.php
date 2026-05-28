<?php

declare(strict_types=1);

namespace App\Flashboard;

use App\Models\Order;
use Pepperfm\Flashboard\Contracts\Detail\DetailContract;
use Pepperfm\Flashboard\Contracts\Forms\FormContract;
use Pepperfm\Flashboard\Contracts\Resources\Resource;
use Pepperfm\Flashboard\Contracts\Tables\TableContract;
use Pepperfm\Flashboard\Core\Actions\Builders\Action;
use Pepperfm\Flashboard\Core\Detail\Entries\TextEntry;
use Pepperfm\Flashboard\Core\Forms\Fields\DateInput;
use Pepperfm\Flashboard\Core\Forms\Fields\FileUpload;
use Pepperfm\Flashboard\Core\Forms\Fields\RichText;
use Pepperfm\Flashboard\Core\Forms\Fields\Select;
use Pepperfm\Flashboard\Core\Forms\Layout\Section;
use Pepperfm\Flashboard\Core\Forms\Layout\Tab;
use Pepperfm\Flashboard\Core\Forms\Layout\Tabs;
use Pepperfm\Flashboard\Core\Relations\RelationDefinition;
use Pepperfm\Flashboard\Core\Tables\Actions\DeleteAction;
use Pepperfm\Flashboard\Core\Tables\Actions\EditAction;
use Pepperfm\Flashboard\Core\Tables\Columns\BadgeColumn;
use Pepperfm\Flashboard\Core\Tables\Columns\TextColumn;

final class OrdersResource extends Resource
{
    public static function model(): string
    {
        return Order::class;
    }

    public static function table(TableContract $table): TableContract
    {
        return $table->columns([
            TextColumn::make('id')->label('ID')->sortable(),
            BadgeColumn::make('status')->label('Status')->searchable()->sortable(),
        ]);
    }

    public static function form(FormContract $form): FormContract
    {
        return $form
            ->schema([
                Section::make('main')->label('Main')->schema([
                    Select::make('status')->label('Status')->required(),
                    DateInput::make('ordered_on')->label('Ordered on'),
                    RichText::make('notes')->label('Notes')->fullWidth(),
                    FileUpload::make('receipt')
                        ->label('Receipt')
                        ->accept('application/pdf,image/*')
                        ->directory('order-receipts'),
                ]),
                Tabs::make('settings')->tabs([
                    Tab::make('visibility')->label('Visibility')->schema([
                        Select::make('status')->label('Status'),
                    ]),
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
        ]);
    }

    public static function actions(): array
    {
        return [
            EditAction::make(),
            DeleteAction::make(),
            Action::make('archive')->label('Archive'),
        ];
    }

    public static function relations(): array
    {
        return [
            RelationDefinition::make('items')->label('Items'),
        ];
    }
}
