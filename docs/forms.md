# Forms

Flashboard form payloads are generated from `form()` definitions on a resource.
The canonical authoring path is schema-tree first: start with `$form->schema([...])` and treat layout containers as nodes inside that tree.

## Simple Forms First

```php
use Pepperfm\Flashboard\Core\Forms\Fields\Select;
use Pepperfm\Flashboard\Core\Forms\Fields\Textarea;

public static function form(\Pepperfm\Flashboard\Contracts\Forms\FormContract $form): \Pepperfm\Flashboard\Contracts\Forms\FormContract
{
    return $form
        ->schema([
            Select::make('status', 'Status')->required(),
            Textarea::make('notes', 'Notes'),
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
            TextInput::make('first_name', 'First name'),
            TextInput::make('last_name', 'Last name'),
            TextInput::make('email', 'Email')
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

Use `Section` and `Tabs` nodes inside `schema()` when the form has meaningful visual grouping.

```php
use Pepperfm\Flashboard\Core\Forms\Fields\Checkbox;
use Pepperfm\Flashboard\Core\Forms\Fields\NumberInput;
use Pepperfm\Flashboard\Core\Forms\Fields\Select;
use Pepperfm\Flashboard\Core\Forms\Fields\Textarea;
use Pepperfm\Flashboard\Core\Forms\Fields\TextInput;
use Pepperfm\Flashboard\Core\Forms\Fields\Toggle;
use Pepperfm\Flashboard\Core\Forms\Layout\Section;
use Pepperfm\Flashboard\Core\Forms\Layout\Tab;
use Pepperfm\Flashboard\Core\Forms\Layout\Tabs;

public static function form(\Pepperfm\Flashboard\Contracts\Forms\FormContract $form): \Pepperfm\Flashboard\Contracts\Forms\FormContract
{
    return $form
        ->schema([
            Section::make('content', 'Content')->schema([
                TextInput::make('name', 'Name')->required(),
                TextInput::make('slug', 'Slug'),
                Textarea::make('description', 'Description')->fullWidth(),
                NumberInput::make('sort_order', 'Sort order'),
            ]),
            Tabs::make('settings')->tabs([
                Tab::make('general', 'General')->schema([
                    Select::make('status', 'Status'),
                    Checkbox::make('is_featured', 'Featured'),
                    Toggle::make('is_active', 'Is active'),
                ]),
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

Section::make('filters', 'Filters')
    ->layout(FormLayoutMode::Flex)
    ->direction(FormLayoutDirection::Row)
    ->justify(FormLayoutJustify::Between)
    ->align(FormLayoutAlign::Center)
    ->gap(2)
    ->schema([
        Select::make('status', 'Status'),
        TextInput::make('search', 'Search'),
    ]);
```

Typed fields, sections, and tabs are the preferred public API. Legacy array definitions remain supported as a migration bridge.
Every keyed typed schema node accepts a human label as the optional second `make()` argument, for example `TextInput::make('name', 'Name')`. Use `->label()` when you want to override or compute the label later in the chain.
`sections()` and `tabs()` remain as compatibility helpers, but they now normalize into the same canonical schema tree instead of defining a separate runtime shape.

## Advanced Fields

Use purpose-built fields when the control has runtime behavior beyond plain text:

```php
use Pepperfm\Flashboard\Core\Forms\Fields\DateInput;
use Pepperfm\Flashboard\Core\Forms\Fields\FileUpload;
use Pepperfm\Flashboard\Core\Forms\Fields\PasswordInput;
use Pepperfm\Flashboard\Core\Forms\Fields\RichText;

DateInput::make('published_on', 'Published on')
    ->minDate('2026-01-01')
    ->maxDate('2026-12-31');

FileUpload::make('cover_image', 'Cover image')
    ->accept('image/*')
    ->mimes(['jpg', 'png', 'webp'])
    ->maxSize(2048)
    ->disk('public')
    ->directory('covers');

RichText::make('body', 'Body')
    ->html()
    ->minLength(20)
    ->fullWidth();

PasswordInput::make('password', 'Password')
    ->minLength(12)
    ->confirmed();

PasswordInput::make('password_confirmation', 'Confirm password');
```

Behavior notes:

- `DateInput` stores and validates date values as `Y-m-d`; edit payloads normalize date-like values to that shape.
- `FileUpload` renders through the package wrapper over Nuxt UI `UFileUpload`. When `storeFiles()` is enabled, or `disk()` / `directory()` is set, uploaded files are stored and the model receives the stored path or list of paths. Without package storage, uploaded file objects are available to mutation hooks and are then intentionally omitted from mass assignment. Edit forms can keep, replace, or clear existing file references; clear requests use a package-owned `__remove` companion field.
- edit payloads never include file contents; they expose only `existing_files` metadata with safe `name`, `path`, and optional `url` values.
- `RichText` supports `html()`, `markdown()`, and `json()` content formats. JSON rich text is validated as an array; HTML and Markdown are validated as strings.
- `PasswordInput` is rendered as a password input, is never hydrated from the record on edit, and empty edit submissions are skipped so an unchanged password is not blanked. When using `confirmed()`, add a matching `*_confirmation` field; Flashboard strips confirmation values before persistence.

## Relation Fields

Use `BelongsTo` when a form should store one local foreign key and let the operator pick one related record.

```php
use App\Flashboard\CategoryResource;
use App\Flashboard\TagResource;
use Illuminate\Database\Eloquent\Builder;
use Pepperfm\Flashboard\Core\Forms\Fields\BelongsTo;
use Pepperfm\Flashboard\Core\Forms\Fields\BelongsToMany;

BelongsTo::make('category_id', 'Category')
    ->resource(CategoryResource::class)
    ->titleAttribute('name')
    ->searchable(['name', 'slug'])
    ->modifyQueryUsing(static fn (Builder $query): Builder => $query->with('parent'))
    ->required();

BelongsToMany::make('tags', 'Tags')
    ->resource(TagResource::class)
    ->titleAttribute('name')
    ->searchable(['name', 'slug'])
    ->modifyQueryUsing(static fn (Builder $query): Builder => $query->with('group'))
    ->maxItems(8);
```

`BelongsTo::make(string $key, ?string $label = null, ?string $relationship = null)` follows the same label convention as other typed fields. When the third argument is omitted, Flashboard infers the Eloquent relationship from FK-like keys: `category_id`, `category_uuid`, and `category_ulid` all resolve to `category`. Use `relationship('...')` when the relationship method differs from the FK key.

Resolution rules:

- the field stores and submits the scalar key named by `$key`, for example `category_id`
- Eloquent `BelongsTo` metadata resolves the related model, local foreign key, owner key, related table, and record key
- `resource(CategoryResource::class)` overrides related resource resolution
- when no explicit resource is set, Flashboard can infer one from the registered resource whose `model()` matches the related model; ambiguous matches fail fast and should be fixed with `resource()`
- `model(RelatedModel::class)` enables an explicit model fallback for option loading when no related resource exists
- `modifyQueryUsing(fn (Builder $query): Builder => ...)` applies a server-only field-level query modifier after the related resource query and query extensions; the callback must return an Eloquent `Builder`
- empty submitted values are normalized to `null`; v1 does not call `associate()` implicitly

At runtime `BelongsTo` renders as a lazy `relation_select` field. The form payload includes relation metadata plus `options_url`, `options_per_page`, `selected_option` on edit, and `related_routes` only when the related resource is accessible and has a detail surface. The options endpoint is protected, searchable, paginated, and uses the related resource query plus query extensions when a related resource is available. Field-level query modifiers are not serialized into payloads; they run only on the server for option pages and selected-option hydration.

Validation still starts from the normalized field payload. Required relation fields infer `required`; optional ones infer `nullable`; both receive an `exists:<related_table>,<owner_key>` rule when relation metadata resolves safely. Explicit form builder rules merge on top.

Use `BelongsToMany` when the current record owns a pivot membership and the form should choose several related records. `BelongsToMany::make(string $key, ?string $label = null, ?string $relationship = null)` infers the relationship from `$key`, accepts the same explicit `resource()`, `model()`, `titleAttribute()`, `searchable()`, `optionsPerPage()`, and `modifyQueryUsing()` helpers, and adds `maxItems()` for a backend-enforced selection cap.

At runtime `BelongsToMany` renders as `relation_multi_select`. Form state is an array of related record keys, edit payloads include `selected_options`, and options use the same protected `_relation-options/{field}` route with repeated `selected[]` hydration. The persister removes the array from scalar mass assignment, saves the parent model inside a transaction, re-resolves submitted keys through the authorized related query, and then calls Eloquent `sync($ids)`. Omitted fields are not synced; an explicit empty array calls `sync([])`. Pivot attributes, ordering, creation, update, and deletion of related records are out of scope for this field.

`BelongsTo` and `BelongsToMany` are form fields, not inverse relation managers. Use `Resource::relations()` with `HasOne` or `HasMany` when the parent resource should manage records on the inverse side. Those managers render outside the normal form schema, use protected nested routes, and overwrite any nested-create FK from server-resolved parent context before persistence.

Relation option loading stays silent during normal use. HTTP-boundary failures may log sanitized WARN/ERROR context such as resource class, field key, failure category, and exception class; search terms, selected values, labels, model attributes, and full payloads should not be logged.

## Renderer Contract

Normalized form payloads now expose an explicit `renderer` hint for every field.

- typed fields set a stable renderer automatically, for example `TextInput` -> `input`, `DateInput` -> `date`, `FileUpload` -> `file_upload`, `RichText` -> `rich_text`, `BelongsTo` -> `relation_select`, `BelongsToMany` -> `relation_multi_select`, `Toggle` -> `switch`
- use purpose-built field classes for common controls: `TextInput`, `Textarea`, `NumberInput`, `DateInput`, `FileUpload`, `RichText`, `PasswordInput`, `BelongsTo`, `BelongsToMany`, `Select`, `Checkbox`, and `Toggle`
- override renderer intent explicitly only for custom or transitional controls where no purpose-built field exists
- legacy arrays can opt into the same contract with `['key' => 'notes', 'renderer' => 'textarea']`
- the frontend maps these hints through package-owned wrappers: `FBInput`, `FBTextarea`, `FBDateInput`, `FBFileUpload`, `FBRichText`, `FBRelationSelect`, `FBRelationMultiSelect`, `FBSelect`, `FBCheckbox`, and `FBSwitch`

Those wrappers stay thin over Nuxt UI (`UInput`, `UTextarea`, `UInputDate`, `UCalendar`, `UFileUpload`, `UEditor`, `USelect`, `USelectMenu`, `UCheckbox`, `USwitch`) so Flashboard owns the runtime contract without creating a second UI framework. PHP cannot expose a `Switch` class because `switch` is a reserved keyword, so the switch-style field is named `Toggle`.

## Layout Contract

Normalized form payloads now expose:

- one canonical `schema` tree for recursive rendering
- flattened compatibility data in `fields`, `sections`, and `tabs` while the transition is still in progress
- container layout on `form.layout`, `section.layout`, and `tab.layout`
- field sizing on `field.layout.column_span`

Invalid layout combinations such as `columns()` plus `direction()` on the same container still raise an exception instead of being silently ignored.

## Runtime Flow

1. Flashboard resolves create/edit route
2. `ResourceFormDataSource` hydrates field state
3. `ResourceFormPersister` validates and saves data
4. `afterSave()` hooks and runtime hooks run; runtime hook payloads and record context redact password values and replace file values with minimal metadata
5. User is redirected to the detail screen

## Validation

- create rules: `creationRules()`
- update rules: `updateRules($record)`
- shared rules: `formRules()`

Flashboard still infers baseline validation from the normalized field payload and then merges explicit `rules()` on top. Text fields infer strings, `NumberInput` infers numeric values, `DateInput` infers `date_format:Y-m-d`, `FileUpload` infers file rules, `RichText::json()` infers arrays, `BelongsTo` infers `exists:<related_table>,<owner_key>`, `BelongsToMany` infers `array` plus `exists:<related_table>,<related_key>` for each item, and `Checkbox` / `Toggle` infer booleans.
On create screens, visible `Checkbox` and `Toggle` fields default to `false` unless `defaults()` provides a value.

## Mutation Hooks

- builder-level: `mutateDataUsing()`
- resource-level: `mutateFormDataBeforeSave()`
- post-persist: `afterSave()`
