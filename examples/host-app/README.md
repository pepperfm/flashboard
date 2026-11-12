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
