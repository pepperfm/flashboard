# Forms

Flashboard form payloads are generated from `form()` definitions on a resource.
The preferred authoring path is schema-first: start with `$form->schema([...])` for simple create/edit screens and only introduce grouped layout when operators genuinely need it.

## Simple Forms First

```php
use Pepperfm\Flashboard\Contracts\Forms\FieldRenderer;
use Pepperfm\Flashboard\Core\Forms\Fields\Select;
use Pepperfm\Flashboard\Core\Forms\Fields\TextInput;

public static function form(\Pepperfm\Flashboard\Contracts\Forms\FormContract $form): \Pepperfm\Flashboard\Contracts\Forms\FormContract
{
    return $form
        ->schema([
            Select::make('status')->label('Status')->required(),
            TextInput::make('notes')
                ->label('Notes')
                ->renderer(FieldRenderer::Textarea),
        ])
        ->rules([
            'status' => ['required', 'string'],
        ]);
}
```

This path renders as a single centered `UPageCard` shell in the package UI without an artificial `Main` subsection card.

## Field Layout

Schema-first forms can now place multiple fields on one row without introducing nested layout nodes.

```php
use Pepperfm\Flashboard\Core\Forms\Fields\TextInput;

public static function form(\Pepperfm\Flashboard\Contracts\Forms\FormContract $form): \Pepperfm\Flashboard\Contracts\Forms\FormContract
{
    return $form
        ->columns(2)
        ->gap(4)
        ->schema([
            TextInput::make('first_name')->label('First name'),
            TextInput::make('last_name')->label('Last name'),
            TextInput::make('email')
                ->label('Email')
                ->email()
                ->columnSpan(2),
        ]);
}
```

Available layout helpers:

- `columns(int|array)` for grid containers
- `gap(int|array)` for grid or flex spacing
- `columnSpan(int|array)` for grid items
- `fullWidth()` as a shorthand for spanning the full grid width

Scalar `columns(2)` and `columns(3)` stay mobile-safe by default: Flashboard normalizes them to one column on small screens and activates multiple columns from `md` upward.

## Grouped Layouts

Use `sections()` or `tabs()` only when the form has meaningful visual grouping.

```php
use Pepperfm\Flashboard\Core\Forms\Fields\Select;
use Pepperfm\Flashboard\Core\Forms\Fields\TextInput;
use Pepperfm\Flashboard\Core\Forms\Fields\Toggle;
use Pepperfm\Flashboard\Core\Forms\Layout\Section;
use Pepperfm\Flashboard\Core\Forms\Layout\Tab;

public static function form(\Pepperfm\Flashboard\Contracts\Forms\FormContract $form): \Pepperfm\Flashboard\Contracts\Forms\FormContract
{
    return $form
        ->sections([
            Section::make('content')->label('Content')->schema([
                TextInput::make('name')->label('Name')->required(),
                TextInput::make('slug')->label('Slug'),
            ]),
        ])
        ->tabs([
            Tab::make('settings')->label('Settings')->schema([
                Select::make('status')->label('Status'),
                Toggle::make('is_active')->label('Is active'),
            ]),
        ]);
}
```

Sections and tabs support the same layout API as the root form:

```php
use Pepperfm\Flashboard\Contracts\Forms\FormLayoutAlign;
use Pepperfm\Flashboard\Contracts\Forms\FormLayoutDirection;
use Pepperfm\Flashboard\Contracts\Forms\FormLayoutJustify;
use Pepperfm\Flashboard\Contracts\Forms\FormLayoutMode;
use Pepperfm\Flashboard\Core\Forms\Fields\Select;
use Pepperfm\Flashboard\Core\Forms\Fields\TextInput;
use Pepperfm\Flashboard\Core\Forms\Layout\Section;

Section::make('filters')
    ->label('Filters')
    ->layout(FormLayoutMode::Flex)
    ->direction(FormLayoutDirection::Row)
    ->justify(FormLayoutJustify::Between)
    ->align(FormLayoutAlign::Center)
    ->gap(2)
    ->schema([
        Select::make('status')->label('Status'),
        TextInput::make('search')->label('Search'),
    ]);
```

Typed fields, sections, and tabs are the preferred public API. Legacy array definitions remain supported as a migration bridge.

## Renderer Contract

Normalized form payloads now expose an explicit `renderer` hint for every field.

- typed fields set a stable renderer automatically, for example `TextInput` -> `input`, `Select` -> `select`, `Toggle` -> `switch`
- override renderer intent explicitly when the visual control differs from the base field type, for example `TextInput::make('notes')->renderer(FieldRenderer::Textarea)`
- legacy arrays can opt into the same contract with `['key' => 'notes', 'renderer' => 'textarea']`
- the frontend maps these hints through package-owned wrappers: `FBInput`, `FBTextarea`, `FBSelect`, `FBSwitch`

Those wrappers stay thin over Nuxt UI (`UInput`, `UTextarea`, `USelect`, `USwitch`) so Flashboard owns the runtime contract without creating a second UI framework.

## Layout Contract

Normalized form payloads now expose package-owned layout metadata:

- container layout lives on `form.layout`, `section.layout`, and `tab.layout`
- field sizing lives on `field.layout.column_span`
- grid and flex settings are validated during normalization and fail fast on invalid combinations
- legacy arrays can opt into the same behavior with `layout`, `columns`, `gap`, `direction`, `justify`, `align`, `wrap`, and `column_span`

Invalid layout combinations such as `columns()` plus `direction()` on the same container raise an exception instead of being silently ignored.

## Runtime Flow

1. Flashboard resolves create/edit route
2. `ResourceFormDataSource` hydrates field state
3. `ResourceFormPersister` validates and saves data
4. `afterSave()` hooks and runtime hooks run
5. User is redirected to the detail screen

## Validation

- create rules: `creationRules()`
- update rules: `updateRules($record)`
- shared rules: `formRules()`

Flashboard still infers baseline validation from the normalized field payload and then merges explicit `rules()` on top. Renderer overrides such as `FieldRenderer::Textarea` keep string inference intact.

## Mutation Hooks

- builder-level: `mutateDataUsing()`
- resource-level: `mutateFormDataBeforeSave()`
- post-persist: `afterSave()`
