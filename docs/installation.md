# Installation

## Preparing a source release (maintainers)

This demo-alpha package keeps compiled assets in Git and has no release CI. Before committing a release, run the frontend checks and rebuild the package distribution locally:

```sh
bun install --frozen-lockfile
bun run dist:prepare
git add -A public/build
```

The Vite build replaces `public/build` contents, and `git add -A` also stages deletion of obsolete hashed files. Review `git diff --cached -- public/build` and confirm the committed manifest references the current build before tagging. Host applications continue to use bundled assets without requiring Bun.


## Requirements

- PHP 8.5+
- Laravel 13 host application
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

Tagged releases include compiled frontend assets. The installer uses these directly and **does not require Node.js, Bun, or a package manager** in a host application.

When installing from a source checkout without `public/build/manifest.json`, the installer asks which frontend package manager to use:

- `bun`
- `npm`
- `pnpm`
- `yarn`
- `skip frontend install/build`

When assets are absent, the installer builds the frontend in the package directory and publishes the resulting files. Choosing `skip` without precompiled assets fails the installation instead of silently leaving a broken panel.

If you want to rebuild package assets later without re-running the full installer, use:

```bash
php artisan flashboard:build-assets
```

This command runs inside the installed Flashboard package directory, refreshes the compiled assets after a successful build, and only overwrites published views when you pass `--force`. It is intended for package development or custom source builds, not routine installation.

You can bypass the interactive package-manager prompt with an explicit flag:

```bash
php artisan flashboard:build-assets --bun
php artisan flashboard:build-assets --pnpm
php artisan flashboard:build-assets --skip
```

This publishes:

- `resources/views/vendor/flashboard/*`
- `public/vendor/flashboard/build/*`

The host application does not need to add Flashboard's Vue, Inertia, or Nuxt UI dependencies to its own frontend build. Those dependencies belong to the package build, which `flashboard:install` and `flashboard:build-assets` run from the installed package directory.

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

**Authorization is deny-by-default.** Flashboard requires an authenticated user *and* the `accessFlashboard` Laravel Gate ability before any panel page, API endpoint, or mutation is accessible.

Define the ability in a host application's service provider:

```php
use App\Models\User;
use Illuminate\Support\Facades\Gate;

public function boot(): void
{
    Gate::define('accessFlashboard', static fn (User $user): bool => (bool) $user->is_admin);
}
```

Adjust the condition to your own user model. The ability name can be changed via `config('flashboard.auth.access_ability')`. Removing or leaving it undefined does **not** grant access. Login requests are rate limited to six attempts per minute; use application-level throttling and monitoring for stricter requirements.

Resource `policy()` declarations are **not auto-registered**. If provided, register the policy in your host application with `Gate::policy(Model::class, Policy::class)`. A missing or mismatching registration is rejected. For multi-tenant resources, scope `Resource::query()` to visible records: row-level policies alone do not filter index results.

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
- Generate a component-backed page: `php artisan flashboard:make-page ReviewQueuePage --component`
