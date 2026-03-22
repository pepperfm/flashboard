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

The installer will ask for a panel path such as `panel` or `admin` and generate a matching provider class name automatically, for example:

- `panel` → `PanelPanelProvider`
- `admin` → `AdminPanelProvider`
- `partner-balance` → `PartnerBalancePanelProvider`

It will also ask which frontend package manager should be used for asset setup:

- `bun`
- `npm`
- `pnpm`
- `yarn`
- `skip frontend install/build`

Unless you choose `skip`, the installer will run the selected package manager's install command and then build Flashboard's published frontend assets automatically.

If you want to rebuild package assets later without re-running the full installer, use:

```bash
php artisan flashboard:build-assets
```

This publishes:

- `resources/views/vendor/flashboard/*`
- `public/vendor/flashboard/build/*`

The package ships its own built frontend assets. A host application does not need to install Flashboard's Vue, Inertia, or Nuxt UI npm dependencies manually.

## Configure Flashboard

Generate a host-side panel provider:

```bash
php artisan flashboard:make-provider
```

Then register it in your host app bootstrap/provider list and configure Flashboard there:

```php
use Pepperfm\Flashboard\FlashboardConfig;
use Pepperfm\Flashboard\Integration\Laravel\FlashboardPanelProvider;

final class AdminPanelProvider extends FlashboardPanelProvider
{
    public function register(): void
    {
        $this->panelConfig()
            ->path('panel')
            ->discover();
    }
}
```

By default this scans `app/Flashboard` for classes ending with `Resource` or `Page`.
Use provider `register()` for primary configuration and provider `boot()` for extra host-side customization.

### Discovery Controls

```php
public function register(): void
{
    $this->panelConfig()
        ->path('panel')
        ->discoverResources(in: app_path('Flashboard'))
        ->discoverPages(in: app_path('Flashboard'))
        ->except(
            App\Flashboard\Support\DraftResource::class,
            'LegacyQueuePage',
            'Support/IgnoredResource.php',
        );
}
```

- `discover()` scans both resources and pages
- `discoverResources()` scans only `*Resource`
- `discoverPages()` scans only `*Page`
- `except()` excludes classes by FQCN, basename, or relative path
- `withoutDiscovery()` disables auto-discovery when you want explicit registration only

### Explicit Overrides

```php
public function register(): void
{
    $this->panelConfig()
        ->path('panel')
        ->discover()
        ->resource(App\Flashboard\UsersResource::class)
        ->page(App\Flashboard\ReviewQueuePage::class);
}
```

Explicit `resource()` and `page()` registration is merged with discovered classes and deduplicated automatically.

## Environment

Optional environment variables:

- `FLASHBOARD_NAME`
- `FLASHBOARD_PATH`
- `FLASHBOARD_GUARD`
- `FLASHBOARD_REPORT_BOOT`

## Fallback Config File

The package still ships `config/flashboard.php` as a fallback source for package defaults and compatibility with older setups.
For new host apps, the primary user-facing API is the panel provider.
If provider config, inline `Flashboard::configure()`, and fallback config all coexist, provider config wins.

## Access

- login: `/panel/login` when `path('panel')` is configured
- panel root: `/panel`
- resource index: `/panel/resources/<resource-key>`

## Quick Validation

For the fastest first run:

1. Copy `examples/host-app/app/Flashboard/ReviewQueuePage.php` into the host app at `app/Flashboard/ReviewQueuePage.php`
2. Run `php artisan flashboard:make-provider`
3. Run `php artisan flashboard:make-resource OrdersResource App\\Models\\Order`
4. Enable `$this->panelConfig()->path('panel')->discover()` inside the generated provider
5. Visit your configured panel login path, for example `/panel/login`

## Debug Surfaces

- Visit any panel route with `Accept: application/json` to inspect the runtime payload envelope.
- Use the playground helper: `php artisan flashboard:playground`
- Generate a resource: `php artisan flashboard:make-resource`
- Generate a page: `php artisan flashboard:make-page`
