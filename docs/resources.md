# Resources

Resources are the declarative core of Flashboard.

## Minimal Resource

```php
<?php

declare(strict_types=1);

namespace App\Flashboard;

use App\Models\Order;
use Pepperfm\Flashboard\Flashboard;
use Pepperfm\Flashboard\Contracts\Resources\Resource;
use Pepperfm\Flashboard\Core\Tables\Builders\Table;

final class OrdersResource extends Resource
{
    public static function model(): string
    {
        return Order::class;
    }

    public static function table(\Pepperfm\Flashboard\Contracts\Tables\TableContract $table): \Pepperfm\Flashboard\Contracts\Tables\TableContract
    {
        return $table->columns([
            ['key' => 'id', 'label' => 'ID', 'sortable' => true],
            ['key' => 'status', 'label' => 'Status', 'searchable' => true],
        ]);
    }
}

Flashboard::configure()
    ->discover();
```

Any `*Resource` class placed in `app/Flashboard` will be picked up automatically by `discover()`.
Use `->resource(OrdersResource::class)` only when you want explicit registration.

## Discovery Variants

```php
Flashboard::configure()
    ->discoverResources()
    ->except(
        App\Flashboard\Support\DraftResource::class,
        'Support/IgnoredResource.php',
    );
```

Use:

- `discover()` to scan both resources and pages
- `discoverResources()` to scan only resources
- `discoverPages()` to scan only pages
- `except()` to exclude helper or draft classes from auto-registration
- `withoutDiscovery()` to opt out completely and register resources explicitly

## Available Resource Surfaces

- `table()` for list/index behavior
- `form()` for create/edit behavior
- `detail()` for read-only detail screens
- `actions()` for page or record actions
- `relations()` for nested relation payloads
- `pages()` for resource-owned page declarations

## Common Overrides

- `routeBasePath()`
- `navigationLabel()`
- `navigationGroup()`
- `formRules()`
- `mutateFormDataBeforeSave()`
- `afterSave()`
- `policy()`

## Escape Hatches

- `queryExtensions()`
- `payloadExtensions()`
- `actionExtensions()`
- `runtimeHooks()`

Use these when the 80 percent declarative path is not enough.

## Generate With Prompts

```bash
php artisan flashboard:make-resource
```

The command will ask for the resource class, model class, primary fields, and whether to scaffold form/detail/action sections.

When generated into `app/Flashboard`, the resource is auto-discovered by default.
