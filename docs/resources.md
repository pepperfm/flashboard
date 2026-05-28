# Resources

Resources are the declarative core of Flashboard.

## Minimal Resource

```php
<?php

declare(strict_types=1);

namespace App\Flashboard;

use App\Models\Order;
use Pepperfm\Flashboard\Contracts\Resources\Resource;
use Pepperfm\Flashboard\Core\Forms\Fields\Checkbox;
use Pepperfm\Flashboard\Core\Forms\Fields\DateInput;
use Pepperfm\Flashboard\Core\Forms\Fields\FileUpload;
use Pepperfm\Flashboard\Core\Forms\Fields\RichText;
use Pepperfm\Flashboard\Core\Forms\Fields\Select;
use Pepperfm\Flashboard\Core\Forms\Fields\Textarea;
use Pepperfm\Flashboard\Core\Forms\Fields\TextInput;
use Pepperfm\Flashboard\Core\Forms\Fields\Toggle;
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
- `relations()` for nested relation payloads
- `pages()` for resource-owned page declarations

Actions and pages are still declared through their dedicated methods, but they now participate in the same package-owned resource surface model as `table()`, `form()`, and `infolist()`. That keeps custom resource pages and resource-level actions from becoming a separate ad hoc subsystem.

Table row actions are configured from `Resource::actions()`, not from the table builder. Use `EditAction::make()` and `DeleteAction::make()` when the index table should expose per-record controls; delete is explicit opt-in and remains policy-aware.

## Form Authoring Guidance

- prefer `$form->schema([...])` for simple CRUD resources
- introduce `Section` and `Tabs` nodes inside `schema()` when the form has meaningful operator-facing grouping
- use purpose-built fields such as `TextInput`, `Textarea`, `NumberInput`, `DateInput`, `FileUpload`, `RichText`, `PasswordInput`, `Select`, `Checkbox`, and `Toggle`; `Toggle` renders as a switch-style boolean control
- pass the label as the optional second argument to keyed typed nodes, for example `TextInput::make('name', 'Name')`; keep `->label()` for later overrides
- use `columns()`, `gap()`, `columnSpan()`, and `fullWidth()` to place multiple fields on one row without extra row/container nodes
- use `layout(FormLayoutMode::Flex)` with `direction()/justify()/align()/wrap()` only when a grouped form needs inline controls instead of a grid
- keep array definitions only as a migration bridge; typed nodes remain the canonical package API

If you do need grouped layout, Flashboard now renders one canonical schema tree recursively instead of branching between “simple”, “sections”, and “tabs” form modes.

## Configuration Styles

Flashboard currently supports both configuration styles:

- typed schema nodes such as `TextColumn::make('status', 'Status')`
- concept-aligned nodes such as `TextColumn::make('email', 'Email')`, `TextInput::make('name', 'Name')`, `DateInput::make('published_on', 'Published on')`, `FileUpload::make('receipt', 'Receipt')`, `RichText::make('body', 'Body')`, `Section::make('content', 'Content')->schema([...])`, and `Tabs::make('settings')->tabs([...])`
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
- `policy()`

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
