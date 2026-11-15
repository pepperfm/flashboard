# Host App Validation

This folder describes the reference host application integration flow for Flashboard.

## Goal

Validate the package in a Laravel 13 application with:

- session auth
- one reference resource
- one custom workspace page
- protected `/admin` routes

## Validation Steps

1. Require the package
2. Run `php artisan flashboard:install`
3. Generate a panel provider with `php artisan flashboard:make-provider`
4. Generate a resource with `php artisan flashboard:make-resource`
5. Generate or copy a workspace page into `app/Flashboard`
6. Enable `$this->panelConfig()->path('panel')->discover()` in the generated provider
7. Verify:
   - `/panel/login`
   - `/panel`
   - `/panel/resources/orders`
   - `/panel/resources/orders/create`
   - `/panel/queues/review`

## Independent host-app smoke checks (before production)

Repeat the basic validation in two **separate** Laravel 13 host applications:

- Laravel 13 with no existing frontend: install with precompiled assets, log in,
  create/read/update/delete an authorized resource record, inspect relations
  and JSON payloads, and verify an unauthorized user cannot access the panel.
- Laravel 13 with Inertia/Vue (and optionally Nuxt UI + SSR): put Flashboard
  on a separate URL prefix, follow a normal HTML link into the admin area,
  navigate inside Flashboard, then navigate back with a full page load.
  Verify that host Vue pages and FB Vue pages do not share root views or bundles.

In each host check that denied `fieldAbilityMap()` fields cannot be submitted
by direct HTTP requests, that hidden/scoped-out records return 404/403, and
that neither detail nor inverse relation payloads expose raw model attributes.

Reinstall or upgrade the Composer package and re-run
`php artisan flashboard:install`; its published build manifest should update
without writing a copy of Flashboard Blade templates into the host.
If the host already has a customized published view, test it separately.

This is a manual smoke checklist, **not a claim** that these host checks run
automatically in package tests.

## Files In This Example

- `app/Providers/Flashboard/AdminPanelProvider.php`
- `app/Flashboard/OrdersResource.php`
- `app/Flashboard/ReviewQueuePage.php`

## Component Page Check

To validate a Nova-like custom page component, generate a page with:

```bash
php artisan flashboard:make-page ReviewQueuePage --component
```

The command creates:

- `app/Flashboard/ReviewQueuePage.php`
- `resources/js/flashboard/pages/ReviewQueuePage.vue`
- `resources/js/flashboard/pages.ts`

Add `resources/js/flashboard/pages.ts` to the host Vite inputs.
The generated page resolves its script URL with `app(\Illuminate\Foundation\Vite::class)->asset('resources/js/flashboard/pages.ts')`, so the entry must exist in the host Vite manifest.

If you need to keep helper classes in `app/Flashboard`, exclude them explicitly:

```php
public function register(): void
{
    $this->panelConfig()
        ->path('panel')
        ->discover()
        ->except(
            App\Flashboard\Support\DraftResource::class,
            'Support/IgnoredResource.php',
        );
}
```
