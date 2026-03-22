# Flashboard

Flashboard is a backend-driven Laravel admin runtime package for internal panels.

The current primary DX is a typed, declarative resource API:

- `table()` with typed columns and filters such as `TextColumn`, `BadgeColumn`, `SelectFilter`
- `form()` with typed fields and a schema-first flow for simple CRUD forms, plus optional layout nodes such as `Section` and `Tab` when grouping is truly needed
- `detail()` / `infolist()` with typed entries such as `TextEntry`
- `actions()` and `pages()` as part of the same package-owned resource surface model

Legacy array definitions remain supported as a compatibility bridge while host apps move to the typed DSL.

## Docs

- [Installation](./docs/installation.md)
- [Resources](./docs/resources.md)
- [Forms](./docs/forms.md)
- [Tables](./docs/tables.md)
- [Workspaces](./docs/workspaces.md)
- [Extensions](./docs/extensions.md)
- [Contracts](./docs/contracts.md)
- [Upgrading](./docs/upgrading.md)
- [Releases](./docs/releases.md)
- [Beta Checklist](./docs/beta-checklist.md)

## Current Scope

The repository currently contains the package foundation:

- Composer package metadata and Laravel package discovery
- A Flashboard service provider and install command
- Baseline panel configuration and `/admin` route registration
- Public panel, page, resource, table, form, detail, action, and navigation contracts
- Initial fluent builder layer, typed schema node DSL, and discovery registries for panels, resources, and pages
- Runtime metadata, screen resolution, lifecycle hooks, and payload assembly for page/resource screens
- Package auth flow, route registrar, panel shell layout, permission-aware navigation, and Eloquent-backed list screen data source
- Create/edit persistence, detail hydration, relation payloads, action execution, and custom workspace page support
- Versioned UI payload envelope, renderers, policy bridge, notifications/overlays/state/theme, extension hooks, playground tooling, and test/quality scaffold
- Inertia + Vue panel shell with `@inertiaHead`, `@inertia`, a Vite-powered app entry, and Nuxt UI component primitives
- A real Inertia root view and Vue page shell so host applications can verify package wiring through the client runtime

The full resource runtime, table engine, form engine, detail views, and operator workflows are still planned and tracked in [`.ai-factory/ROADMAP.md`](./.ai-factory/ROADMAP.md).

## Install In A Host Laravel App

1. Require the package in the host application.
2. Run `php artisan flashboard:install`.
3. Generate a panel provider with `php artisan flashboard:make-provider`.
4. Generate a resource or page with `php artisan flashboard:make-resource` / `flashboard:make-page`.
5. Visit your panel path.

Generated resources now use the typed DSL by default and keep legacy arrays only as a migration fallback.

## Local Development

- `composer install`
- `npm install`
- `npm run build`
- `composer test`
- `composer analyse`

## Notes

- The package is intentionally bootstrapped before feature completion.
- The panel route now boots through an Inertia root view and Vue page shell.
