<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Tests\Fixtures\Resources;

use Illuminate\Database\Eloquent\Builder;
use Pepperfm\Flashboard\Contracts\Forms\FormContract;
use Pepperfm\Flashboard\Contracts\Resources\Resource;
use Pepperfm\Flashboard\Core\Forms\Fields\BelongsTo;
use Pepperfm\Flashboard\Core\Forms\Fields\TextInput;
use Pepperfm\Flashboard\Tests\Fixtures\Models\BelongsToProduct;

final class SelectionProductResource extends Resource
{
    public static function model(): string
    {
        return BelongsToProduct::class;
    }

    public static function form(FormContract $form): FormContract
    {
        return $form->schema([
            TextInput::make('name')->required(),
            BelongsTo::make('category_id')->resource(SelectionCategoryResource::class)
                ->searchable(['slug'])->modifyQueryUsing(static fn(Builder $query): Builder => $query->where('slug', '!=', 'filtered')),
        ])->rules([
            'name' => ['required', 'string'],
            'category_id' => ['nullable', 'integer'],
        ]);
    }
}
