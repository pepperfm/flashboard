# Installation

## Requirements

- PHP 8.4+
- Laravel 12 or 13 host application
- Session-based authentication in the host app

## Composer

```bash
composer require pepperfm/flashboard
```

If you are testing the package from a local checkout, add a path repository in the host application's `composer.json` first:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../flashboard"
        }
    ]
}
```

## Package Bootstrap

```bash
php artisan flashboard:install
```

This publishes:

- `resources/views/vendor/flashboard/*`
- `public/vendor/flashboard/build/*`

The package ships its own built frontend assets. A host application does not need to install Flashboard's Vue, Inertia, or Nuxt UI npm dependencies manually.

## Configure Flashboard

Configure Flashboard inline in your host app bootstrap:

```php
use Pepperfm\Flashboard\Flashboard;

Flashboard::configure()
    ->path('panel')
    ->resource(App\Flashboard\DemoOrdersResource::class)
    ->page(App\Flashboard\DemoReviewQueuePage::class);
```

## Environment

Optional environment variables:

- `FLASHBOARD_NAME`
- `FLASHBOARD_PATH`
- `FLASHBOARD_GUARD`
- `FLASHBOARD_REPORT_BOOT`

## Access

- login: `/admin/login`
- panel root: `/admin`
- resource index: `/admin/resources/<resource-key>`

## Quick Validation

For the fastest first run:

1. Copy `examples/host-app/app/Flashboard/DemoReviewQueuePage.php` into the host app at `app/Flashboard/DemoReviewQueuePage.php`
2. Run `php artisan flashboard:make-resource DemoOrdersResource App\\Models\\Order`
3. Register both classes with `Flashboard::configure()`
4. Visit your configured panel login path, for example `/panel/login`

## Debug Surfaces

- Visit any panel route with `Accept: application/json` to inspect the runtime payload envelope.
- Use the playground helper: `php artisan flashboard:playground`
- Generate a resource: `php artisan flashboard:make-resource`
- Generate a page: `php artisan flashboard:make-page`
