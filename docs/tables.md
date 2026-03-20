# Tables

Tables drive list/index screens and are backed by Eloquent queries.

## Example

```php
public static function table(\Pepperfm\Flashboard\Contracts\Tables\TableContract $table): \Pepperfm\Flashboard\Contracts\Tables\TableContract
{
    return $table
        ->columns([
            ['key' => 'id', 'label' => 'ID', 'sortable' => true],
            ['key' => 'status', 'label' => 'Status', 'searchable' => true],
        ])
        ->filters([
            ['key' => 'status', 'label' => 'Status'],
        ])
        ->pagination(25);
}
```

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
