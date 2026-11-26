# Resources

Resources are the declarative core of Flashboard.

## Minimal Resource

```php
<?php

declare(strict_types=1);

namespace App\Flashboard;

use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Pepperfm\Flashboard\Contracts\Resources\Resource;
use Pepperfm\Flashboard\Core\Forms\Fields\BelongsTo;
use Pepperfm\Flashboard\Core\Forms\Fields\BelongsToMany;
use Pepperfm\Flashboard\Core\Forms\Fields\Checkbox;
use Pepperfm\Flashboard\Core\Forms\Fields\DateInput;
use Pepperfm\Flashboard\Core\Forms\Fields\FileUpload;
use Pepperfm\Flashboard\Core\Forms\Fields\RichText;
use Pepperfm\Flashboard\Core\Forms\Fields\Select;
use Pepperfm\Flashboard\Core\Forms\Fields\Textarea;
use Pepperfm\Flashboard\Core\Forms\Fields\TextInput;
use Pepperfm\Flashboard\Core\Forms\Fields\Toggle;
use Pepperfm\Flashboard\Core\Relations\HasMany;
use Pepperfm\Flashboard\Core\Relations\HasOne;
use Pepperfm\Flashboard\Core\Tables\Actions\DeleteAction;
use Pepperfm\Flashboard\Core\Tables\Actions\EditAction;
use Pepperfm\Flashboard\Core\Tables\Columns\BadgeColumn;
use Pepperfm\Flashboard\Core\Tables\Columns\TextColumn;
use Pepperfm\Flashboard\Core\Tables\Filters\SelectFilter;
use Pepperfm\Flashboard\Integration\Laravel\FlashboardPanelProvider;

final class OrdersResource extends Resource
{
    public static function model(): string
    {
        return Order::class;
    }

    public static function table(\Pepperfm\Flashboard\Contracts\Tables\TableContract $table): \Pepperfm\Flashboard\Contracts\Tables\TableContract
    {
        return $table
            ->columns([
                TextColumn::make('id', 'ID')->sortable(),
                BadgeColumn::make('status', 'Status')->searchable()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status', 'Status')->lazy(),
            ]);
    }

    public static function form(\Pepperfm\Flashboard\Contracts\Forms\FormContract $form): \Pepperfm\Flashboard\Contracts\Forms\FormContract
    {
        return $form
            ->columns(2)
            ->schema([
                Select::make('status', 'Status'),
                BelongsTo::make('customer_id', 'Customer')
                    ->resource(CustomersResource::class)
                    ->titleAttribute('name')
                    ->searchable(['name', 'email'])
                    ->required(),
                BelongsToMany::make('tags', 'Tags')
                    ->resource(TagsResource::class)
                    ->titleAttribute('name')
                    ->searchable(['name', 'slug'])
                    ->maxItems(8),
                TextInput::make('name', 'Name')->required(),
                TextInput::make('email', 'Email')->email(),
                Textarea::make('notes', 'Notes')->columnSpan(2),
                DateInput::make('ordered_on', 'Ordered on'),
                RichText::make('internal_summary', 'Internal summary')->fullWidth(),
                FileUpload::make('receipt', 'Receipt')
                    ->accept('application/pdf,image/*')
                    ->directory('order-receipts'),
                Checkbox::make('is_featured', 'Featured'),
                Toggle::make('is_active', 'Is active'),
            ]);
    }

    public static function actions(): array
    {
        return [
            EditAction::make(),
            DeleteAction::make(),
        ];
    }

    public static function relations(): array
    {
        return [
            HasOne::make('profile', 'Profile')
                ->resource(OrderProfilesResource::class)
                ->attachable()
                ->detachable()
                ->replaceable(),
            HasMany::make('items', 'Items')
                ->resource(OrderItemsResource::class)
                ->searchable(['name', 'sku'])
                ->perPage(10)
                ->attachable()
                ->detachable()
                ->syncable(),
        ];
    }
}

final class AdminPanelProvider extends FlashboardPanelProvider
{
    public function register(): void
    {
        $this->panelConfig()
            ->discover();
    }
}
```

Any `*Resource` class placed in `app/Flashboard` will be picked up automatically by provider `discover()`.
Use `->resource(OrdersResource::class)` only when you want explicit registration in the provider.

In table definitions, column-level `searchable()` adds the column to the global resource table search, and column-level `sortable()` renders a clickable server-side sort header. This is separate from `SelectFilter::searchable()`, which only changes the option picker for that filter.

### Related table columns and N+1 queries

Table cells read attributes from loaded Eloquent models. If a column accesses a relationship (for example, `customer.name`), preload it in the resource's existing query method:

```php
public static function query(): \Illuminate\Database\Eloquent\Builder
{
    return \App\Models\Order::query()->with('customer');
}
```

Use `withCount()` for relation counts when appropriate. Flashboard does not automatically guess which relationships to eager-load; avoid N+1 by configuring the resource query and checking SQL query counts for the real table.

## Discovery Variants

```php
public function register(): void
{
    $this->panelConfig()
        ->discoverResources()
        ->except(
            App\Flashboard\Support\DraftResource::class,
            'Support/IgnoredResource.php',
        );
}
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
- `infolist()` as a concept-aligned alias for `detail()`
- `actions()` for resource-owned actions, including table row actions
- `relations()` for nested relation managers and legacy read-only relation payloads
- `pages()` for resource-owned page declarations

Actions and pages are still declared through their dedicated methods, but they now participate in the same package-owned resource surface model as `table()`, `form()`, and `infolist()`. That keeps custom resource pages and resource-level actions from becoming a separate ad hoc subsystem.

Table row actions are configured from `Resource::actions()`, not from the table builder. Use `EditAction::make()` and `DeleteAction::make()` when the index table should expose per-record controls; delete is explicit opt-in and remains policy-aware.


## Executable resource actions

Use `Action::make()` in `Resource::actions()`. The original `ActionContract`
remains supported. Opt into the explicit `ActionScope::Collection` for index
actions or `ActionScope::Record` for row and record-screen actions. Without
an explicit scope, legacy placement and the original `record` / `payload`
handler arguments remain unchanged.

```php
use Pepperfm\Flashboard\Contracts\Actions\ActionScope;
use Pepperfm\Flashboard\Core\Actions\Builders\Action;
use Pepperfm\Flashboard\Core\Forms\Builders\Form;
use Pepperfm\Flashboard\Core\Forms\Fields\NumberInput;
use Pepperfm\Flashboard\Core\Forms\Fields\Textarea;
use Pepperfm\Flashboard\Core\Runtime\Actions\ActionExecutionResult;

Action::make('grant-access')
    ->scope(ActionScope::Record)
    ->ability('grantAccess')
    ->modal('Grant temporary access')
    ->modalDescription('Set the access period and explain the change.')
    ->form(static fn (array $context): Form => Form::make()
        ->schema([
            NumberInput::make('days')->required(),
            Textarea::make('reason')->required(),
        ])
        ->rules([
            'days' => ['integer', 'min:1', 'max:365'],
            'reason' => ['string', 'max:1000'],
        ])
        ->defaults(['days' => 7]))
    ->handleUsing(static function (array $context): ActionExecutionResult {
        // Delegate to the host domain service with model, user, validated payload
        // and idempotency_key. The host owns atomicity and replay protection.
        return new ActionExecutionResult(true, 'Access granted.', null);
    });
```

The example illustrates the API, not an actual domain mutation. The host
must implement the operation and register the declared resource policy
with Laravel Gate. An explicit `ability()` is fail-closed when its policy
or method is unavailable. `authorizeUsing(fn (array $context): bool => ...)`
can add a domain-specific authorization check; `visibleWhen(...)` controls
visibility and also prevents direct invocation when false. Neither
confirmation nor a client-supplied record ID grants permission.

A form is prepared only after authorization through a protected GET
endpoint and resolved again on POST. The context includes the scoped
`model`, its `record` key, authenticated `user`, and `resource` class.
The handler receives only validated `payload` plus a separate UUID
`idempotency_key` for form submissions. The library does not write or
replay an idempotency ledger; use that key in the domain service.

For a simple confirmation use `requiresConfirmation()` without a form.
For state-dependent cancellation combine `visibleWhen()` with the host's
domain authorization and current-state check inside the handler.
Return an `ActionExecutionResult(false, $message, null)` for a business
failure; legacy array handler results remain successful data results.
JSON clients receive 200 on success, 409 for a business failure, 422 for
validation, 403/404 for denied/missing resources. Inertia navigation uses
the existing success/error flash notification pipeline. Do not rely on
HTTP success alone to infer that a domain operation succeeded.

Supported fields and transport are described in the forms documentation.

## Form Relation Fields

`BelongsTo` belongs in `form()` when the create/edit screen should write one local foreign key:

```php
BelongsTo::make('customer_id', 'Customer')
    ->resource(CustomersResource::class)
    ->titleAttribute('name')
    ->searchable(['name', 'email'])
    ->required();
```

The field renders as a lazy searchable select, submits only the scalar FK value, and persists through the normal `forceFill()` form save path. The third `make()` argument can override the relationship name, but Flashboard infers common FK names such as `customer_id` -> `customer`. Explicit `resource()` wins over auto-discovery; otherwise a related resource can be inferred from a single registered resource whose `model()` matches the Eloquent related model.

`BelongsToMany` belongs in `form()` when the create/edit screen should write pivot membership for the current record:

```php
BelongsToMany::make('tags', 'Tags')
    ->resource(TagsResource::class)
    ->titleAttribute('name')
    ->searchable(['name', 'slug'])
    ->modifyQueryUsing(static fn (Builder $query): Builder => $query->where('archived', false))
    ->maxItems(8);
```

The field renders as a lazy multi-select, submits an array of related record keys, and syncs the Eloquent `BelongsToMany` relation after the parent record is saved in the same transaction. Omitted fields are left untouched; an explicit empty array detaches all selected records. Pivot attributes and related-record create/update/delete flows stay outside this field.

Keep these separate from `Resource::relations()`. `BelongsTo` writes the current record's scalar FK from the form, `BelongsToMany` syncs the current record's pivot membership, and `HasOne` / `HasMany` are inverse relation managers because they mutate related records' FK values from the parent resource context.

## Inverse Relation Managers

Use `HasOne` and `HasMany` in `relations()` when a parent resource should display and manage related records without embedding those controls into the normal form field schema.

```php
use Illuminate\Database\Eloquent\Builder;
use Pepperfm\Flashboard\Core\Relations\HasMany;
use Pepperfm\Flashboard\Core\Relations\HasOne;

public static function relations(): array
{
    return [
        HasOne::make('profile', 'Profile')
            ->resource(OrderProfilesResource::class)
            ->attachable()
            ->detachable()
            ->replaceable(),
        HasMany::make('items', 'Items')
            ->resource(OrderItemsResource::class)
            ->searchable(['name', 'sku'])
            ->perPage(10)
            ->modifyRecordsQueryUsing(static fn (Builder $query): Builder => $query->with('product'))
            ->modifyAttachOptionsQueryUsing(static fn (Builder $query): Builder => $query->where('archived', false))
            ->attachable()
            ->detachable()
            ->syncable(),
    ];
}
```

`make(string $key, ?string $label = null, ?string $relationship = null)` follows the typed-node label convention. The relationship name is inferred from `$key` by default; pass the third argument or call `relationship()` when the Eloquent method differs. Flashboard resolves Eloquent `HasOne` / `HasMany` metadata through the Laravel integration layer, infers a related resource when exactly one registered resource matches the related model, and lets `resource()` override inference.

Use `modifyRecordsQueryUsing(fn (Builder $query): Builder => ...)` to customize displayed/current related records, `modifyAttachOptionsQueryUsing(fn (Builder $query): Builder => ...)` to customize attach/replace/sync candidate options, or `modifyQueryUsing(fn (Builder $query): Builder => ...)` when the same modifier should apply to both. These callbacks are server-only, run after related resource query extensions, must return an Eloquent `Builder`, and are not included in runtime payloads.

Relation managers render on detail screens by default. Use `showOnEdit()` when the manager should also appear below an edit form. Legacy `RelationDefinition` output remains read-only and keeps the old badge-style payload.

Mutation modes are opt-in:

- `attachable()` moves one related record by setting its FK to the parent key
- `detachable()` clears a related FK only when the FK is nullable or otherwise safe
- `replaceable()` on `HasOne` detaches the current related record and attaches the replacement in one transaction
- `syncable()` on `HasMany` explicitly moves selected records and detaches omitted current records; it never deletes records The panel requires all current relation pages to be loaded and submits an expected membership snapshot; an incomplete or stale snapshot is rejected before mutation.

Relation records, attach options, and mutation actions are served through protected nested resource routes. Related resource query extensions, relation query modifiers, and policy checks are applied before records or options are exposed, and selected records are re-resolved server-side for every mutation. Related create actions open the related resource create form with a server-resolved parent context; submitted FK values are overwritten by the server before persistence, so client-side FK tampering does not decide the relationship.

Normal relation-manager reads and mutations do not log. HTTP-boundary failures may emit sanitized WARN/ERROR context such as resource class, relation key, action, failure category, exception class, and coarse SQL state only. Search terms, selected IDs, labels, titles, model attributes, and request payloads should not be logged.

## Form Authoring Guidance

- prefer `$form->schema([...])` for simple CRUD resources
- introduce `Section` and `Tabs` nodes inside `schema()` when the form has meaningful operator-facing grouping
- use purpose-built fields such as `TextInput`, `Textarea`, `NumberInput`, `DateInput`, `FileUpload`, `RichText`, `PasswordInput`, `BelongsTo`, `BelongsToMany`, `Select`, `Checkbox`, and `Toggle`; `Toggle` renders as a switch-style boolean control
- pass the label as the optional second argument to keyed typed nodes, for example `TextInput::make('name', 'Name')`; keep `->label()` for later overrides
- use `columns()`, `gap()`, `columnSpan()`, and `fullWidth()` to place multiple fields on one row without extra row/container nodes
- use `layout(FormLayoutMode::Flex)` with `direction()/justify()/align()/wrap()` only when a grouped form needs inline controls instead of a grid
- keep array definitions only as a migration bridge; typed nodes remain the canonical package API

If you do need grouped layout, Flashboard now renders one canonical schema tree recursively instead of branching between “simple”, “sections”, and “tabs” form modes.

## Configuration Styles

Flashboard currently supports both configuration styles:

- typed schema nodes such as `TextColumn::make('status', 'Status')`
- concept-aligned nodes such as `TextColumn::make('email', 'Email')`, `TextInput::make('name', 'Name')`, `BelongsTo::make('customer_id', 'Customer')`, `BelongsToMany::make('tags', 'Tags')`, `DateInput::make('published_on', 'Published on')`, `FileUpload::make('receipt', 'Receipt')`, `RichText::make('body', 'Body')`, `Section::make('content', 'Content')->schema([...])`, and `Tabs::make('settings')->tabs([...])`
- legacy compatibility arrays such as `['key' => 'status', 'label' => 'Status']`

Typed nodes are the preferred public API going forward. Arrays remain supported while the package migrates the rest of the DSL toward the concept-first object style.

## Common Overrides

- `routeBasePath()`
- `navigationLabel()`
- `navigationGroup()`
- `navigationIcon()`
- `formRules()`
- `mutateFormDataBeforeSave()`
- `afterSave()`
- `policy()` — names a policy **registered on the resource model** via Laravel `Gate::policy()`; Flashboard rejects missing or mismatching registrations. `Resource::query()` must independently scope index rows for per-user/tenant visibility.

## Navigation Icons

Resources can customize their sidebar icon with `navigationIcon()`:

```php
public static function navigationIcon(): string
{
    return 'lucide:annoyed';
}
```

Icon names use the Nuxt UI/Iconify format without the leading `i-`: `collection:name`. Browse available names on [Icones](https://icones.js.org/). Flashboard passes the value to Nuxt UI with `i-` added under the hood and supports the `lucide` and `heroicons` collections, for example `lucide:annoyed` or `heroicons:cube`.

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

The command requires only the model class. Resource class, primary field, secondary field, navigation group, and detail screen prompts can be accepted as-is. By default, the resource class is inferred from the model, the generated table/form uses `id`, and the detail screen is disabled. Edit/delete table actions and create/edit form scaffolding are generated by default.
Generated resources default to `schema([...])` for simple forms. The generator chooses purpose-built form fields for common names: password-like fields become `PasswordInput` with a generated hashing mutation and are omitted from generated table/detail output, date-like fields become `DateInput`, upload-like fields become `FileUpload` with a default storage directory, body/content fields become `RichText`, and notes/description fields become `Textarea`.

When generated into `app/Flashboard`, the resource is auto-discovered by default from your panel provider.

### Relation title resolution

Relation managers use their explicit title attribute for display where available. If no physical title column exists, ordering and search use the related model key instead of emitting SQL for a missing or virtual column. Related options remain scoped and subject to resource visibility.


### Action authorization and preparation

GET and HEAD on the form endpoint only prepare the form; execution requires POST.
An explicitly mapped action ability requires its configured and registered policy,
including actions with the legacy scope. Unprotected legacy actions retain their
original handler arguments. Related option pagination counts authorized records;
when a per-record policy is present, the runtime scans ordered rows in batches.
For large datasets, apply authorization restrictions in the resource query where possible.

### Explicit read-only resources

> Available since 0.3.0.

Override `public static function isReadOnly(): bool` to return true. Flashboard omits standard create/edit/store/update/delete, relation mutation and custom action routes and denies mutation authorization checks. Table, detail, metrics and read-only relation managers remain available. Host-owned query scopes and policies still determine record visibility.

### Structured JSON detail entry (development)

```php
JsonEntry::make('metadata', 'Event data');
```

Declare an authorized model attribute containing plain JSON data or encoded JSON text. Built-in JSON casts preserve scalar values, object/list distinction and malformed input reporting; host accessors may return redacted plain values. Arbitrary objects are not serialized. The browser renders escaped plain text in a collapsible preformatted block and allows copying only complete values. The server bounds encoded input to 64 KiB, pretty output to 32 KiB, depth to 8, nodes to 200 and individual strings/keys to 2 KiB. Truncated and invalid values are marked explicitly. The model's hidden/visible attribute rules and resource field ability apply; nested sensitive keys must be removed by the host before exposing a JSON attribute. This is a presentation feature, not an audit collector or automatic secret redactor.

All metrics share one 12-column desktop grid in declaration order. Each card honors span(1–12), wraps to the next row when there is insufficient space, and retains its own height. Below the xl breakpoint cards occupy a single column. No automatic grouping by type, dense packing, explicit row placement or configurable chart height.

### Timeline (upcoming 0.4.0)

Use a timeline in `Resource::relations()` for existing history records:

```php
use Pepperfm\Flashboard\Core\Relations\Timeline;

Timeline::make('events', 'History')
    ->resource(OrderEventResource::class)
    ->titleAttribute('event')
    ->descriptionAttribute('description')
    ->dateAttribute('created_at')
    ->perPage(10);
```

Timeline is a permanently read-only HasMany presentation, not an event collector. The optional third `make()` argument selects a different Eloquent relation name. It defaults to `created_at`; the date must be a physical date/timestamp column, and the optional description a physical model column. Use the existing related resource query, policy and `modifyRecordsQueryUsing()` to scope events. The server orders by timestamp descending, then record key descending, excluding records without a date. A denied or hidden date excludes the event, and denied or hidden descriptions are omitted; titles fall back to the record key if not visible. Only bounded plain text, keys and authorized detail links are returned. The UI uses Nuxt UI Timeline and loads subsequent pages through the existing relation records endpoint (maximum 100 per page). No create, edit, attach, detach, sync or replace controls are exposed by the timeline; contextual mutations are denied on the server. Direct editing of a related resource elsewhere remains governed by its own policy. As with other offset-paginated relations, refresh to see concurrent changes. No new routes, storage, event bus or audit middleware.
