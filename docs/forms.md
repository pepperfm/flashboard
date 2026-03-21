# Forms

Flashboard form payloads are generated from `form()` definitions on a resource.

## Example

```php
public static function form(\Pepperfm\Flashboard\Contracts\Forms\FormContract $form): \Pepperfm\Flashboard\Contracts\Forms\FormContract
{
    return $form
        ->sections([
            \Pepperfm\Flashboard\Core\Forms\Layout\Section::make('main')->label('Main')->schema([
                \Pepperfm\Flashboard\Core\Forms\Fields\TextInput::make('status')->label('Status')->required(),
                \Pepperfm\Flashboard\Core\Forms\Fields\TextInput::make('notes')->label('Notes'),
            ]),
        ])
        ->rules([
            'status' => ['required', 'string'],
        ]);
}
```

Typed fields, sections, and tabs are the preferred public API. Legacy array definitions remain supported as a migration bridge.

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

## Mutation Hooks

- builder-level: `mutateDataUsing()`
- resource-level: `mutateFormDataBeforeSave()`
- post-persist: `afterSave()`
