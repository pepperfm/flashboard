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
            \Pepperfm\Flashboard\Core\Tables\Filters\SelectFilter::make('status')->label('Status'),
        ])
        ->pagination(25);
}
```

Typed columns and filters are the preferred public API. Legacy array definitions continue to work while the package migrates the rest of the DSL to the concept-first object style.

## Query Behavior

`ResourceListDataSource` currently supports:

- search across columns marked `searchable`
- sort across columns marked `sortable`
- simple filter key/value pairs from the request
- Eloquent pagination with query-string preservation

## Extension Points

- `queryExtensions()` for custom query mutation
- `payloadExtensions()` for post-assembly table payload changes

## Debugging

Use JSON responses on resource routes to inspect:

- normalized table schema
- dataset rows
- pagination metadata
- filtered action visibility
