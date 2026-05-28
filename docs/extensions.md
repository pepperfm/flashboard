# Extensions

Flashboard exposes extension contracts for the 20 percent path.

## Available Contracts

- `ActionExtensionContract`
- `PayloadExtensionContract`
- `QueryExtensionContract`
- `RuntimeHookContract`

## Registration Points

Resources expose:

- `queryExtensions()`
- `payloadExtensions()`
- `actionExtensions()`
- `runtimeHooks()`

## Examples

### Query Extension

```php
final class TenantQueryExtension implements \Pepperfm\Flashboard\Contracts\Extensions\QueryExtensionContract
{
    public function extend(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('tenant_id', tenant('id'));
    }
}
```

### Runtime Hook

```php
final class AuditHook implements \Pepperfm\Flashboard\Contracts\Extensions\RuntimeHookContract
{
    public function handle(string $hook, array $context = []): void
    {
        logger()->warning('Flashboard runtime hook triggered', [
            'hook' => $hook,
            'payload_keys' => array_keys((array) ($context['payload'] ?? [])),
        ]);
    }
}
```

## Guidance

- prefer extensions over forks
- keep extensions resource-scoped
- reserve payload extensions for shape changes that cannot be expressed declaratively
- avoid logging full hook contexts; resource form hooks redact passwords and upload objects, but extension logs should still stay minimal
