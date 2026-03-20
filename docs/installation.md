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

- `config/flashboard.php`
- `resources/views/vendor/flashboard/*`
- `public/vendor/flashboard/build/*`

The package ships its own built frontend assets. A host application does not need to install Flashboard's Vue, Inertia, or Nuxt UI npm dependencies manually.

## Configure Discovery

Register your pages and resources in `config/flashboard.php`:

```php
'discovery' => [
    'providers' => [],
    'resources' => [
        App\Flashboard\DemoOrdersResource::class,
    ],
    'pages' => [
        App\Flashboard\DemoReviewQueuePage::class,
    ],
],
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
2. Run `php artisan flashboard:make-demo-resource`
3. Register both classes in `config/flashboard.php`
4. Visit `/admin/login`

## Debug Surfaces

- Visit any panel route with `Accept: application/json` to inspect the runtime payload envelope.
- Use the playground helper: `php artisan flashboard:playground`
- Generate a demo resource: `php artisan flashboard:make-demo-resource`
