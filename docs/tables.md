# Tables

Tables drive list/index screens and are backed by Eloquent queries.

## Example

```php
public static function table(\Pepperfm\Flashboard\Contracts\Tables\TableContract $table): \Pepperfm\Flashboard\Contracts\Tables\TableContract
{
    return $table
        ->columns([
            \Pepperfm\Flashboard\Core\Tables\Columns\TextColumn::make('id', 'ID')->sortable(),
            \Pepperfm\Flashboard\Core\Tables\Columns\BadgeColumn::make('status', 'Status')->searchable()->sortable(),
            \Pepperfm\Flashboard\Core\Tables\Columns\DateColumn::make('created_at', 'Created')->format('d.m.Y')->sortable(),
        ])
        ->filters([
            \Pepperfm\Flashboard\Core\Tables\Filters\SelectFilter::make('status', 'Status')
                ->lazy(),
            \Pepperfm\Flashboard\Core\Tables\Filters\InputFilter::make('email', 'Email')
                ->contains(),
            \Pepperfm\Flashboard\Core\Tables\Filters\DateFilter::make('created_at', 'Created'),
            \Pepperfm\Flashboard\Core\Tables\Filters\SelectFilter::make('sku', 'SKU')
                ->queryColumn('id')
                ->searchable()
                ->options($products->pluck('sku', 'id')->all()),
        ])
        ->pagination(25);
}
```

Typed columns and filters are the preferred public API. Pass a visible label as the optional second `make()` argument, for example `TextColumn::make('email', 'Email')`; `->label()` remains available for later overrides. Legacy array definitions continue to work while the package migrates the rest of the DSL to the concept-first object style.

## Date Columns

Call `DateColumn::make()` when a column represents a date-like value:

```php
\Pepperfm\Flashboard\Core\Tables\Columns\DateColumn::make('created_at', 'Created')
    ->sortable();
```

Date columns render the payload value as-is by default. Use `format()` when the backend should format a date value before it reaches the table:

```php
\Pepperfm\Flashboard\Core\Tables\Columns\DateColumn::make('created_at', 'Created')
    ->format('d.m.Y');
```

The format string uses PHP date format tokens. Empty values still render as the normal empty placeholder, and unrecognized string values fall back to their original value.

## Column Search And Sorting

Call `searchable()` on a table column when it should participate in the global table search:

```php
\Pepperfm\Flashboard\Core\Tables\Columns\TextColumn::make('email', 'Email')
    ->searchable();
```

When at least one visible column is searchable, Flashboard renders one search input above the resource table. The input writes scalar URL state:

```text
search=john
```

The backend applies global search only across columns marked `searchable`. Search changes reset `page` and preserve active filters and sorting.

Call `sortable()` on a table column when its header should become a server-side sort control:

```php
\Pepperfm\Flashboard\Core\Tables\Columns\TextColumn::make('id', 'ID')
    ->sortable();
```

Sortable headers cycle through ascending, descending, and unsorted URL state:

```text
sort=id&direction=asc
sort=id&direction=desc
```

Sort changes reset `page` and preserve active filters and search. The backend ignores unsupported sort keys, so query strings cannot sort by columns that were not marked `sortable`.

Column-level `searchable()` is separate from select-filter `searchable()`. A searchable column contributes to the global table search; a searchable select filter changes how that one filter's options are picked.

## Row Actions

Table row actions are configured from the resource, while `table()` stays focused on columns, filters, scopes, and pagination:

```php
use Pepperfm\Flashboard\Core\Tables\Actions\DeleteAction;
use Pepperfm\Flashboard\Core\Tables\Actions\EditAction;

public static function table(\Pepperfm\Flashboard\Contracts\Tables\TableContract $table): \Pepperfm\Flashboard\Contracts\Tables\TableContract
{
    return $table->columns([
        \Pepperfm\Flashboard\Core\Tables\Columns\TextColumn::make('id', 'ID'),
    ]);
}

public static function actions(): array
{
    return [
        EditAction::make(),
        DeleteAction::make(),
    ];
}
```

When `actions()` is omitted, Flashboard does not render row actions for the resource index table. Delete uses a protected `DELETE` resource route with confirmation in the UI.

The backend filters row actions per record through the resource policy. `view`, `edit`, and `delete` use Laravel `view`, `update`, and `delete` abilities respectively. Delete calls `$record->delete()`, so host application soft delete behavior is respected.

## Query Behavior

`ResourceListDataSource` currently supports:

- global search UI and backend search across columns marked `searchable`
- clickable sortable headers and backend sort across columns marked `sortable`
- permission-aware row actions for view, edit, and explicitly configured delete
- table filter controls rendered above resource index tables, with searchable select filters and a reset action when filters are active
- simple filter key/value pairs from the request, such as `filters[status]=active`
- input filters from the request, such as `filters[email]=john`, with exact matching by default and opt-in `contains()` matching
- date filters from the request, such as `filters[created_at]=2026-05-22`, applied with exact day matching through `whereDate()`
- multi-value select filters from the request, such as `filters[status][]=draft&filters[status][]=published`, applied with `whereIn()`
- select option keys are submitted as filter values, and `queryColumn()` can target a different database column
- lazy select filters load options from a protected backend endpoint with server-side search and scroll pagination
- Eloquent pagination with query-string preservation

## Input Filters

Call `InputFilter::make()` when a filter should accept operator-entered text for one declared query column:

```php
\Pepperfm\Flashboard\Core\Tables\Filters\InputFilter::make('email', 'Email');
```

Input filters use scalar URL state:

```text
filters[email]=john@example.com
```

By default, input filters apply exact matching with `where($column, $value)`. Use `contains()` when the input should match any part of the column value:

```php
\Pepperfm\Flashboard\Core\Tables\Filters\InputFilter::make('email', 'Email')
    ->contains();
```

`contains()` applies a column-scoped `LIKE` query. Use `queryColumn()` when the public filter key should differ from the database column:

```php
\Pepperfm\Flashboard\Core\Tables\Filters\InputFilter::make('customer', 'Customer email')
    ->queryColumn('customers.email')
    ->contains();
```

Use global table search for broad matching across multiple searchable columns. Use `InputFilter` when the operator should filter one specific column and keep that filter in the URL.

## Date Filters

Call `DateFilter::make()` when a filter should select one exact calendar day:

```php
\Pepperfm\Flashboard\Core\Tables\Filters\DateFilter::make('created_at', 'Created');
```

Date filters render as a Nuxt UI date picker with an input, popover, and calendar. They use scalar URL state:

```text
filters[created_at]=2026-05-22
```

The backend accepts only strict `YYYY-MM-DD` values and applies them with `whereDate($column, '=', $date)`, so both date and datetime columns can be matched by day. Empty, array, non-string, and invalid dates such as `2026-02-30` are ignored and do not appear in `active_filters`.

Use `queryColumn()` when the public filter key should differ from the database column:

```php
\Pepperfm\Flashboard\Core\Tables\Filters\DateFilter::make('reviewed_date', 'Reviewed date')
    ->queryColumn('reviewed_at');
```

`DateFilter` is intentionally single-date only. Date ranges should use a separate API, such as a future `DateRangeFilter`, so the URL shape and query behavior stay explicit.

## Searchable Select Filters

Call `searchable()` on a select filter to render a searchable picker instead of a plain select:

```php
\Pepperfm\Flashboard\Core\Tables\Filters\SelectFilter::make('sku', 'SKU')
    ->searchable()
    ->options($products->pluck('sku', 'id')->all());
```

## Multiple Select Filters

Call `multiple()` when a filter should accept more than one value. Flashboard submits repeated Laravel array query parameters and applies the filter with `whereIn()`:

```php
\Pepperfm\Flashboard\Core\Tables\Filters\SelectFilter::make('status', 'Status')
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
\Pepperfm\Flashboard\Core\Tables\Filters\SelectFilter::make('sku', 'SKU')
    ->lazy()
    ->multiple()
    ->optionValue('id')
    ->optionLabel('sku');
```

## Lazy Select Filters

Call `lazy()` when a select filter has many possible values or should avoid sending all options in the initial table payload:

```php
\Pepperfm\Flashboard\Core\Tables\Filters\SelectFilter::make('status', 'Status')
    ->lazy();
```

Without a callback, Flashboard queries distinct non-null values from the resource query using the filter key as the column. The option label and submitted value are the same scalar value, so this is best for direct columns such as `status`, `type`, `country`, or `role`.

When the options still come from the resource query but the visible label and submitted value are different columns, define them fluently:

```php
SelectFilter::make('sku', 'SKU')
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

SelectFilter::make('product_id', 'Product')
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
