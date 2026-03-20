# Upgrading

## Before Upgrading

- read `CHANGELOG.md`
- compare `docs/contracts.md`
- confirm whether `schema_version` changed

## Upgrade Checklist

1. Review whether your host app should move from explicit registration to `Flashboard::configure()->discover()`
2. Keep explicit `resource()` / `page()` registration only for overrides or non-standard directories
3. Re-run host-app validation from `examples/host-app/README.md`
4. Verify custom extensions against new contracts
5. Re-test protected panel routes and JSON payload consumers

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

## Breaking-Change Classes

- contract rename
- route naming change
- payload shape change
- policy mapping behavior change
- builder API signature change
