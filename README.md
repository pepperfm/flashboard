# Flashboard

Flashboard is a backend-driven Laravel admin runtime package for internal panels.
Full documentation lives at [flashboard.pepperfm.com](https://flashboard.pepperfm.com).

The current primary DX is a typed, declarative resource API:

- `table()` with typed columns and filters such as `TextColumn`, `BadgeColumn`, `DateColumn`, `SelectFilter`, `InputFilter`, and `DateFilter`
- `form()` with typed fields such as `TextInput`, `Textarea`, `NumberInput`, `DateInput`, `FileUpload`, `RichText`, `PasswordInput`, `BelongsTo`, `BelongsToMany`, `Select`, `Checkbox`, and `Toggle`, plus optional layout nodes such as `Section` and `Tab` when grouping is truly needed
- `detail()` / `infolist()` with typed entries such as `TextEntry`
- `relations()` with inverse `HasOne` and `HasMany` relation managers for protected attach, detach, replace, sync, and nested create flows
- `actions()` and `pages()` as part of the same package-owned resource surface model

Legacy array definitions remain supported as a compatibility bridge while host apps move to the typed DSL.

## Documentation

- [Read the documentation](https://flashboard.pepperfm.com)

## Current Scope

The repository currently contains a working package runtime:

- Composer package metadata and Laravel package discovery
- A Flashboard service provider and install command
- Baseline panel configuration and `/admin` route registration
- Public panel, page, resource, table, form, detail, action, and navigation contracts
- Fluent builder layer, typed schema node DSL, and discovery registries for panels, resources, and pages
- Runtime metadata, screen resolution, lifecycle hooks, and payload assembly for page/resource screens
- Package auth flow, route registrar, panel shell layout, permission-aware navigation, and Eloquent-backed list screen data source
- Create/edit persistence, detail hydration, relation payloads, action execution, and custom workspace page support
- Versioned UI payload envelope, renderers, policy bridge, notifications/overlays/state/theme, extension hooks, playground tooling, and test/quality scaffold
- Inertia + Vue panel shell with `@inertiaHead`, `@inertia`, a Vite-powered app entry, and Nuxt UI component primitives
- A real Inertia root view and Vue page shell so host applications can verify package wiring through the client runtime

The package is still beta-stage, but the core resource runtime, table engine, form engine, detail views, actions, relations, and operator workspace surfaces are now implemented enough for host-app validation.

## Install In A Host Laravel App

1. Require the package in the host application.
2. Run `php artisan flashboard:install`.
3. Generate a panel provider with `php artisan flashboard:make-provider`.
4. Generate a resource or page with `php artisan flashboard:make-resource` / `flashboard:make-page`.
5. Visit your panel path.

Generated resources now use the typed DSL by default and keep legacy arrays only as a migration fallback.

During install, Flashboard builds its frontend assets inside the installed package directory and then publishes the compiled files into the host application at `public/vendor/flashboard/build`. Use `php artisan flashboard:build-assets` when you need to rebuild and refresh those published assets later.

## Local Development

- `composer i`
- `bun i`
- `bun run build`
- `composer test`
- `composer analyse`

## Notes

- Flashboard is beta-stage: public contracts should stay explicit and versioned as the package hardens.
- The panel route boots through an Inertia root view and Vue page shell.
