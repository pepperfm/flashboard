<?php

declare(strict_types=1);

namespace App\Flashboard;

use App\Models\Order;
use Pepperfm\Flashboard\Contracts\Resources\Resource;
use Pepperfm\Flashboard\Core\Actions\Builders\Action;
use Pepperfm\Flashboard\Core\Detail\Builders\Detail;
use Pepperfm\Flashboard\Core\Forms\Builders\Form;
use Pepperfm\Flashboard\Core\Relations\RelationDefinition;
use Pepperfm\Flashboard\Core\Tables\Builders\Table;

final class DemoOrdersResource extends Resource
{
    public static function model(): string
    {
        return Order::class;
    }

    public static function table(\Pepperfm\Flashboard\Contracts\Tables\TableContract $table): \Pepperfm\Flashboard\Contracts\Tables\TableContract
    {
        return $table->columns([
            ['key' => 'id', 'label' => 'ID', 'sortable' => true],
            ['key' => 'status', 'label' => 'Status', 'searchable' => true, 'sortable' => true],
        ]);
    }

    public static function form(\Pepperfm\Flashboard\Contracts\Forms\FormContract $form): \Pepperfm\Flashboard\Contracts\Forms\FormContract
    {
        return $form
            ->fields([
                ['key' => 'status', 'label' => 'Status'],
                ['key' => 'notes', 'label' => 'Notes'],
            ])
            ->rules([
                'status' => ['required', 'string'],
            ]);
    }

    public static function detail(\Pepperfm\Flashboard\Contracts\Detail\DetailContract $detail): \Pepperfm\Flashboard\Contracts\Detail\DetailContract
    {
        return $detail->entries([
            ['key' => 'id', 'label' => 'ID'],
            ['key' => 'status', 'label' => 'Status'],
        ]);
    }

    public static function actions(): array
    {
        return [
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
