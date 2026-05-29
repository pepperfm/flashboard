<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Tests\Fixtures\Resources;

use Pepperfm\Flashboard\Contracts\Forms\FormContract;
use Pepperfm\Flashboard\Contracts\Resources\Resource;
use Pepperfm\Flashboard\Core\Forms\Fields\BelongsTo;
use Pepperfm\Flashboard\Core\Forms\Fields\TextInput;
use Pepperfm\Flashboard\Tests\Fixtures\Models\BelongsToProduct;

final class BelongsToProductResource extends Resource
{
    public static function model(): string
    {
        return BelongsToProduct::class;
    }

    public static function form(FormContract $form): FormContract
    {
        return $form->schema([
            TextInput::make('name', 'Name')->required(),
            BelongsTo::make('category_id', 'Category', 'category')
                ->resource(BelongsToCategoryResource::class)
                ->titleAttribute('name')
                ->searchable(['name', 'slug'])
                ->required(),
        ]);
    }
}
