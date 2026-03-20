# Host App Validation

This folder describes the reference host application integration flow for Flashboard.

## Goal

Validate the package in a Laravel 13 application with:

- session auth
- one demo resource
- one custom workspace page
- protected `/admin` routes

## Validation Steps

1. Require the package
2. Run `php artisan flashboard:install`
3. Generate a resource with `php artisan flashboard:make-resource`
4. Generate or copy a workspace page into `app/Flashboard`
5. Register both classes inline with `Flashboard::configure()`
5. Verify:
   - `/panel/login`
   - `/panel`
   - `/panel/resources/demo_orders`
   - `/panel/resources/demo_orders/create`
   - `/panel/queues/review`

## Files In This Example

- `bootstrap/app.php` or `AppServiceProvider.php`
- `app/Flashboard/DemoOrdersResource.php`
- `app/Flashboard/DemoReviewQueuePage.php`
