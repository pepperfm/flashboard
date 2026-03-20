# Forms

Flashboard form payloads are generated from `form()` definitions on a resource.

## Example

```php
public static function form(\Pepperfm\Flashboard\Contracts\Forms\FormContract $form): \Pepperfm\Flashboard\Contracts\Forms\FormContract
{
    return $form
        ->fields([
            ['key' => 'status', 'label' => 'Status'],
            ['key' => 'notes', 'label' => 'Notes'],
        ])
        ->rules([
            'status' => ['required', 'string'],
        ]);
}
```

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
