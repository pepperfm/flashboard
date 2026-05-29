# Upgrading

## Before Upgrading

- read `CHANGELOG.md`
- compare `docs/contracts.md`
- confirm whether `schema_version` changed

## Upgrade Checklist

1. Review whether your host app should move from explicit registration to `Flashboard::configure()->discover()`
2. Keep explicit `resource()` / `page()` registration only for overrides or non-standard directories
3. Review whether legacy resource arrays should move to the typed resource DSL (`TextColumn`, `TextInput`, `BelongsTo`, `BelongsToMany`, `DateInput`, `FileUpload`, `RichText`, `PasswordInput`, `TextEntry`, `Section`, `Tab`)
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
        TextColumn::make('id', 'ID')->sortable(),
        BadgeColumn::make('status', 'Status')->searchable(),
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
- keyed typed schema nodes accept an optional label as the second `make()` argument; prefer `TextInput::make('name', 'Name')` over `TextInput::make('name')->label('Name')` for static labels
- legacy arrays remain valid as compatibility input
- runtime payloads are normalized from both styles into the same schema contract
- prefer purpose-built form fields over renderer overrides for relations, dates, uploads, rich text, and passwords
- replace one-record FK selects with `BelongsTo::make('related_id', 'Related')` when the model has an Eloquent `belongsTo` relation; pass the relationship as the third `make()` argument or call `relationship()` when the FK name does not match the relation method
- replace form-level many-to-many arrays with `BelongsToMany::make('tags', 'Tags')` when the model has an Eloquent `belongsToMany` relation; pass the relationship as the third `make()` argument or call `relationship()` when the field key does not match the relation method
- replace inverse read-only relation badges with `HasOne::make('profile', 'Profile')` or `HasMany::make('items', 'Items')` when operators should manage related records from the parent resource; enable mutation modes explicitly with `attachable()`, `detachable()`, `replaceable()`, or `syncable()`
- move one-off eager loading or option scoping for relation fields/managers to `modifyQueryUsing(fn (Builder $query): Builder => ...)`, `modifyRecordsQueryUsing(...)`, or `modifyAttachOptionsQueryUsing(...)`; every callback must return the modified Eloquent `Builder`
- keep legacy `RelationDefinition::make(...)` only for read-only compatibility payloads; new relation-manager UIs expect `type=has_one` or `type=has_many` plus protected nested route URLs
- review nullable FK behavior before enabling `detachable()`, `replaceable()`, or `syncable()` because inverse managers clear or move related records but never delete them
- JSON/form consumers should handle `relation_select` and `relation_multi_select` renderers and their lazy option payload keys before relying on raw `select` behavior for related records
- `relation_multi_select` edit payloads expose `selected_options`, submit arrays of scalar related keys, and sync pivot membership only; pivot attributes and related-record CRUD still belong in custom flows or inverse managers
- if an edit form previously hydrated password or file columns as text, migrate those fields to `PasswordInput` or `FileUpload`; edit state now intentionally keeps those values empty and exposes only safe file metadata
- `detail()` remains supported and `infolist()` is the concept-aligned alias for new resource classes
- `php artisan flashboard:make-resource` now generates typed table/form/infolist definitions and resource-level edit/delete row actions by default

## Breaking-Change Classes

- contract rename
- route naming change
- payload shape change
- policy mapping behavior change
- builder API signature change
