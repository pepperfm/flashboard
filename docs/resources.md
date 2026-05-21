# Resources

Resources are the declarative core of Flashboard.

## Minimal Resource

```php
<?php

declare(strict_types=1);

namespace App\Flashboard;

use App\Models\Order;
use Pepperfm\Flashboard\Contracts\Resources\Resource;
use Pepperfm\Flashboard\Contracts\Forms\FieldRenderer;
use Pepperfm\Flashboard\Core\Forms\Fields\Select;
use Pepperfm\Flashboard\Core\Forms\Fields\TextInput;
use Pepperfm\Flashboard\Core\Forms\Fields\Toggle;
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
                TextColumn::make('id')->label('ID')->sortable(),
                BadgeColumn::make('status')->label('Status')->searchable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Status')->lazy(),
            ]);
    }

    public static function form(\Pepperfm\Flashboard\Contracts\Forms\FormContract $form): \Pepperfm\Flashboard\Contracts\Forms\FormContract
    {
        return $form
            ->columns(2)
            ->schema([
                Select::make('status')->label('Status'),
                TextInput::make('name')->label('Name')->required(),
                TextInput::make('email')->label('Email')->email(),
                TextInput::make('notes')
                    ->label('Notes')
                    ->columnSpan(2)
                    ->renderer(FieldRenderer::Textarea),
                Toggle::make('is_active')->label('Is active'),
            ]);
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
- `actions()` for page or record actions
- `relations()` for nested relation payloads
- `pages()` for resource-owned page declarations

Actions and pages are still declared through their dedicated methods, but they now participate in the same package-owned resource surface model as `table()`, `form()`, and `infolist()`. That keeps custom resource pages and resource-level actions from becoming a separate ad hoc subsystem.

## Form Authoring Guidance

- prefer `$form->schema([...])` for simple CRUD resources
- introduce `Section` and `Tabs` nodes inside `schema()` when the form has meaningful operator-facing grouping
- use `FieldRenderer` overrides when the control should render differently than the base field type, for example `TextInput::make('notes')->renderer(FieldRenderer::Textarea)`
- use `columns()`, `gap()`, `columnSpan()`, and `fullWidth()` to place multiple fields on one row without extra row/container nodes
- use `layout(FormLayoutMode::Flex)` with `direction()/justify()/align()/wrap()` only when a grouped form needs inline controls instead of a grid
- keep array definitions only as a migration bridge; typed nodes remain the canonical package API

If you do need grouped layout, Flashboard now renders one canonical schema tree recursively instead of branching between “simple”, “sections”, and “tabs” form modes.

## Configuration Styles

Flashboard currently supports both configuration styles:

- typed schema nodes such as `Column::make('status')->label('Status')`
- concept-aligned nodes such as `TextColumn::make('email')`, `TextInput::make('name')`, `Section::make('content')->schema([...])`, and `Tabs::make('settings')->tabs([...])`
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

The command requires only the model class. Resource class, primary field, secondary field, navigation group, and detail screen prompts can be accepted as-is. By default, the resource class is inferred from the model, the generated table/form uses `id`, and the detail screen is disabled. Create/edit form scaffolding is generated by default.
Generated resources default to `schema([...])` for simple forms and add explicit textarea renderer hints for notes-like fields.

When generated into `app/Flashboard`, the resource is auto-discovered by default from your panel provider.
