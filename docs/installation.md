# Installation

## Requirements

- PHP 8.4+
- Laravel 13 host application
- Session-based authentication in the host app

## Composer

```bash
composer require pepperfm/flashboard
```

## Package Bootstrap

```bash
php artisan flashboard:install
```

This publishes:

- `config/flashboard.php`
- `resources/views/vendor/flashboard/*`

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

## Debug Surfaces

- Visit any panel route with `Accept: application/json` to inspect the runtime payload envelope.
- Use the playground helper: `php artisan flashboard:playground`
- Generate a demo resource: `php artisan flashboard:make-demo-resource`
