<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Tests\Fixtures\Resources;

use Pepperfm\Flashboard\Contracts\Forms\FormContract;
use Pepperfm\Flashboard\Contracts\Resources\Resource;
use Pepperfm\Flashboard\Core\Forms\Fields\NumberInput;
use Pepperfm\Flashboard\Core\Forms\Fields\TextInput;
use Pepperfm\Flashboard\Tests\Fixtures\Models\RelationManagerOrderItem;

final class RelationManagerOrderItemResource extends Resource
{
    public static function model(): string
    {
        return RelationManagerOrderItem::class;
    }

    public static function form(FormContract $form): FormContract
    {
        return $form->schema([
            TextInput::make('name', 'Name'),
            TextInput::make('sku', 'SKU'),
            NumberInput::make('order_id', 'Order'),
        ]);
    }

    public static function creationRules(): array
    {
        return [
            'name' => ['nullable', 'string'],
            'sku' => ['nullable', 'string'],
        ];
    }
}
