# Upgrading

## Before Upgrading

- read `CHANGELOG.md`
- compare `docs/contracts.md`
- confirm whether `schema_version` changed

## Upgrade Checklist

1. Review whether your host app should move from explicit registration to `Flashboard::configure()->discover()`
2. Keep explicit `resource()` / `page()` registration only for overrides or non-standard directories
3. Review whether legacy resource arrays should move to the typed resource DSL (`TextColumn`, `TextInput`, `TextEntry`, `Section`, `Tab`)
4. Re-run host-app validation from `examples/host-app/README.md`
5. Verify custom extensions against new contracts
6. Re-test protected panel routes and JSON payload consumers

## Discovery Coexistence

Older setups may still use `config/flashboard.php` discovery arrays:

```php
'discovery' => [
    'resources' => [
        App\Flashboard\UsersResource::class,
    ],
    'pages' => [
        App\Flashboard\ReviewQueuePage::class,
    ],
],
```

This remains supported as a fallback and compatibility layer.

The new primary DX is inline config:

```php
Flashboard::configure()
    ->path('panel')
    ->discover();
```

Coexistence rules:

- fallback config arrays are still read
- inline `Flashboard::configure()` values are merged as a compatibility layer
- provider configuration is merged last and wins over inline and fallback config
- explicit `resource()` / `page()` registrations are deduplicated with discovered classes
- `withoutDiscovery()` disables only auto-discovery, not explicit or fallback registrations

## Provider-First Direction

New host applications should prefer a generated panel provider:

```bash
php artisan flashboard:make-provider
```

Use inline `Flashboard::configure()` only when you need a transitional or compatibility path in an existing host app.

## Typed Resource DSL Migration

The preferred public API for resources is now the typed DSL:

```php
public static function table(TableContract $table): TableContract
{
    return $table->columns([
        TextColumn::make('id')->label('ID')->sortable(),
        BadgeColumn::make('status')->label('Status')->searchable(),
    ]);
}
```

Legacy arrays still work:

```php
public static function table(TableContract $table): TableContract
{
    return $table->columns([
        ['key' => 'id', 'label' => 'ID', 'sortable' => true],
        ['key' => 'status', 'label' => 'Status', 'searchable' => true],
    ]);
}
```

Migration rules:

- typed schema nodes are the recommended authoring style for new resources
- legacy arrays remain valid as compatibility input
- runtime payloads are normalized from both styles into the same schema contract
- `detail()` remains supported and `infolist()` is the concept-aligned alias for new resource classes
- `php artisan flashboard:make-resource` now generates typed table/form/infolist definitions and resource-level edit/delete row actions by default

## Breaking-Change Classes

- contract rename
- route naming change
- payload shape change
- policy mapping behavior change
- builder API signature change
