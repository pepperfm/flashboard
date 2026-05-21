# Tables

Tables drive list/index screens and are backed by Eloquent queries.

## Example

```php
public static function table(\Pepperfm\Flashboard\Contracts\Tables\TableContract $table): \Pepperfm\Flashboard\Contracts\Tables\TableContract
{
    return $table
        ->columns([
            \Pepperfm\Flashboard\Core\Tables\Columns\TextColumn::make('id')->label('ID')->sortable(),
            \Pepperfm\Flashboard\Core\Tables\Columns\BadgeColumn::make('status')->label('Status')->searchable(),
        ])
        ->filters([
            \Pepperfm\Flashboard\Core\Tables\Filters\SelectFilter::make('status')
                ->label('Status')
                ->lazy(),
            \Pepperfm\Flashboard\Core\Tables\Filters\SelectFilter::make('sku')
                ->label('SKU')
                ->queryColumn('id')
                ->searchable()
                ->options($products->pluck('sku', 'id')->all()),
        ])
        ->pagination(25);
}
```

Typed columns and filters are the preferred public API. Legacy array definitions continue to work while the package migrates the rest of the DSL to the concept-first object style.

## Query Behavior

`ResourceListDataSource` currently supports:

- search across columns marked `searchable`
- sort across columns marked `sortable`
- table filter controls rendered above resource index tables, with searchable select filters and a reset action when filters are active
- simple filter key/value pairs from the request, such as `filters[status]=active`
- multi-value select filters from the request, such as `filters[status][]=draft&filters[status][]=published`, applied with `whereIn()`
- select option keys are submitted as filter values, and `queryColumn()` can target a different database column
- lazy select filters load options from a protected backend endpoint with server-side search and scroll pagination
- Eloquent pagination with query-string preservation

## Searchable Select Filters

Call `searchable()` on a select filter to render a searchable picker instead of a plain select:

```php
\Pepperfm\Flashboard\Core\Tables\Filters\SelectFilter::make('sku')
    ->label('SKU')
    ->searchable()
    ->options($products->pluck('sku', 'id')->all());
```

## Multiple Select Filters

Call `multiple()` when a filter should accept more than one value. Flashboard submits repeated Laravel array query parameters and applies the filter with `whereIn()`:

```php
\Pepperfm\Flashboard\Core\Tables\Filters\SelectFilter::make('status')
    ->label('Status')
    ->multiple()
    ->options([
        'draft' => 'Draft',
        'published' => 'Published',
        'archived' => 'Archived',
    ]);
```

The URL state uses Laravel's array format:

```text
filters[status][]=draft&filters[status][]=published
```

Routers may serialize the same state with numeric indexes, for example `filters[status][0]=draft`; Laravel parses both forms as an array.

For defensive request bounds, Flashboard applies the first 200 unique non-empty values per multiple filter request.

`multiple()` can be combined with `lazy()`. Lazy multiple filters still render through Nuxt UI `USelectMenu`, using backend search, scroll pagination, and selected-value hydration:

```php
\Pepperfm\Flashboard\Core\Tables\Filters\SelectFilter::make('sku')
    ->label('SKU')
    ->lazy()
    ->multiple()
    ->optionValue('id')
    ->optionLabel('sku');
```

## Lazy Select Filters

Call `lazy()` when a select filter has many possible values or should avoid sending all options in the initial table payload:

```php
\Pepperfm\Flashboard\Core\Tables\Filters\SelectFilter::make('status')
    ->label('Status')
    ->lazy();
```

Without a callback, Flashboard queries distinct non-null values from the resource query using the filter key as the column. The option label and submitted value are the same scalar value, so this is best for direct columns such as `status`, `type`, `country`, or `role`.

When the options still come from the resource query but the visible label and submitted value are different columns, define them fluently:

```php
SelectFilter::make('sku')
    ->label('SKU')
    ->lazy()
    ->optionValue('id')
    ->optionLabel('sku');
```

`optionValue()` also sets the filter query column, so the selected value above filters records by `id` while showing `sku` in the UI.

For related records, external sources, joins, or fully custom labels, pass a resolver:

```php
use Pepperfm\Flashboard\Contracts\Tables\Filters\SelectFilterOptionsQuery;
use Pepperfm\Flashboard\Contracts\Tables\Filters\SelectFilterOptionsResult;
use Pepperfm\Flashboard\Core\Tables\Filters\SelectFilter;

SelectFilter::make('product_id')
    ->label('Product')
    ->queryColumn('product_id')
    ->lazy(static function (SelectFilterOptionsQuery $query): SelectFilterOptionsResult {
        $products = Product::query()
            ->when(
                $query->search !== '',
                fn ($builder) => $builder->where('name', 'like', '%' . $query->search . '%'),
            )
            ->orderBy('name')
            ->offset($query->offset())
            ->limit($query->perPage + 1)
            ->get(['id', 'name']);

        return SelectFilterOptionsResult::make(
            $products
                ->take($query->perPage)
                ->map(fn (Product $product): array => [
                    'label' => $product->name,
                    'value' => $product->id,
                ])
                ->all(),
            $products->count() > $query->perPage,
            $products->count() > $query->perPage ? $query->page + 1 : null,
        );
    });
```

The options endpoint accepts `search`, `page`, `per_page`, `selected`, and `selected[]` query parameters and returns:

```json
{
  "items": [
    { "label": "Draft", "value": "draft" }
  ],
  "meta": {
    "has_more": false,
    "next_page": null
  }
}
```

`selected` and `selected[]` are used to hydrate visible labels when a table opens with active URL filters. Custom resolvers can read `$query->selected` for the first selected scalar value and `$query->selectedValues` for the full list. Backend option requests use normal Laravel logging and error handling; avoid logging raw search terms or selected values in custom resolvers.

## Extension Points

- `queryExtensions()` for custom query mutation
- `payloadExtensions()` for post-assembly table payload changes

## Debugging

Use JSON responses on resource routes to inspect:

- normalized table schema
- dataset rows
- pagination metadata
- filtered action visibility
