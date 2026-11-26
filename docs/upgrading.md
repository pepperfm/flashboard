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

## Resource permissions and host integration hardening

The resource runtime now applies `queryExtensions()` when resolving a single
record, including on edit/delete/action routes. Ensure custom Resource
`resolveRecord()` overrides apply the same scope. For multi-tenant applications,
**always** enforce tenant isolation in `Resource::query()` and/or
`queryExtensions()`; row-level policy checks alone are not sufficient.

The detail payload's `record` field now contains only the record primary key.
The relation-manager `attributes` field is retained for compatibility but is an
empty array; use the explicit `title`, `key`, and `links` fields instead.
Custom clients relying on previously serialized raw model attributes must
migrate to explicitly declared, authorized detail entries.

`fieldAbilityMap()` governs the form as well as server-side writes. Trying to
submit a denied field directly through POST/PUT/PATCH returns HTTP 403.
Form defaults and mutation output are reduced to visible form fields before
they are sent to the browser. Any server-side privileged changes should be
implemented in trusted Resource hooks, not hidden client-submitted fields.

The installer now publishes only built assets and refreshes them when rerun.
Flashboard's original package views are served directly from vendor. If a prior
installation contains `resources/views/vendor/flashboard/*`, those host
overrides continue taking precedence. Review or remove them manually after
checking any customizations. To explicitly create a view override, run:

```sh
php artisan vendor:publish --tag=flashboard-views
```

When a host application also runs Inertia, keep Flashboard on its own URL
prefix and use a full browser navigation when crossing frontend runtimes.
The host should exclude Flashboard routes from its own SSR handler when applicable.

## Custom page components

Custom pages now expose an additive `component` payload key.
Workspace-only pages continue to send `component: null` and render through the existing fallback card.

If you build JSON or frontend consumers outside the bundled Inertia renderer:

- ignore unknown page payload keys until you opt into component rendering
- resolve `component.name` through a trusted browser registry
- load `component.assets.styles` and `component.assets.scripts` before rendering
- treat `component.props` as server-provided input, not as a source for secrets or raw request data

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

## Resource action runtime upgrade

Existing ActionContract implementations and legacy array handlers continue to work. Previously visible=false actions and actions denied by record policies can no longer be executed directly. New explicit collection/record scopes do not accept cross-scope requests. For custom JSON clients, use the new form_url only when present and treat successful=false as a domain failure (HTTP 409). Legacy direct payloads keep their original handler shape.

After updating the Composer package, refresh the precompiled assets:

```sh
php artisan vendor:publish --tag=flashboard-assets --force
```

Check public/vendor/flashboard/build/manifest.json and the files referenced by its entries. If you have published custom Blade overrides, update the panel view to include the CSRF meta tag used by the action dialog:

```blade
<meta name="csrf-token" content="{{ csrf_token() }}">
```

Review policy maps, then smoke-test list/detail actions and validation failures. To roll back, restore the previous Composer version and republish that version's compiled assets. No schema migration is required.

### Relation fields: stricter write authorization

`BelongsTo` and `BelongsToMany` now reject selected records unavailable through the relation options endpoint. Register or explicitly declare the related resource, or opt into model fallback intentionally. Direct calls to `ResourceFormPersister::create()` and `update()` can pass the authenticated user as the optional final argument for related resource policy checks. Existing null/empty and omitted-field behavior is retained.

## Upgrade to 0.3.0

Refresh the bundled assets after upgrading: `php artisan vendor:publish --tag=flashboard-assets --force` and run `php artisan flashboard:doctor`. No package database migration is required. Review explicit read-only resource declarations and relation policies; denied create/edit links are now omitted and contextual creation through read-only relationships is forbidden. HasMany sync in the panel submits an expected membership snapshot; legacy clients without that snapshot retain full-replacement semantics. Numeric inputs now submit numbers or null, not empty strings. The opt-in local MCP server reports the installed Composer version; existing JSON text responses remain compatible. Re-preview any outstanding managed-file changes after upgrading.


## Runtime maintainability (next release)

Initial form relation options now apply the same record policy and title-field
visibility as the options endpoint. A hidden title falls back to the record key;
a denied record is omitted from selected options. A BelongsTo foreign key is
still an attribute of the owning record: restrict the owning field as well if
that identifier must not be exposed.

BelongsToMany form updates replace only memberships visible under the related
resource query, field query modifier and record policy. Existing memberships
outside those constraints are preserved, including when an empty array is
submitted. Submitted IDs are still validated; omit the field to leave all
memberships unchanged. Review this if host code previously relied on the
form to remove relations outside its visible scope. Privileged whole-membership
operations belong in explicit, authorized host actions.

The package lock file was updated for Symfony security advisories. Consumer
applications resolve their own Composer dependencies; run `composer audit`
and apply supported security updates in each host separately.
