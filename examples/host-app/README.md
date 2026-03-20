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
2. Publish config with `php artisan flashboard:install`
3. Copy or generate a demo resource into `app/Flashboard`
4. Register the demo resource and workspace page in `config/flashboard.php`
5. Verify:
   - `/admin/login`
   - `/admin`
   - `/admin/resources/demo_orders`
   - `/admin/resources/demo_orders/create`
   - `/admin/queues/review`

## Files In This Example

- `config/flashboard.php`
- `app/Flashboard/DemoOrdersResource.php`
- `app/Flashboard/DemoReviewQueuePage.php`
