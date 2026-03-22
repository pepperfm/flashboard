# Flashboard

Flashboard is a backend-driven Laravel admin runtime package for internal panels.
Full documentation lives at [flashboard.pepperfm.com](https://flashboard.pepperfm.com).

The current primary DX is a typed, declarative resource API:

- `table()` with typed columns and filters such as `TextColumn`, `BadgeColumn`, `SelectFilter`
- `form()` with typed fields and a schema-first flow for simple CRUD forms, plus optional layout nodes such as `Section` and `Tab` when grouping is truly needed
- `detail()` / `infolist()` with typed entries such as `TextEntry`
- `actions()` and `pages()` as part of the same package-owned resource surface model

Legacy array definitions remain supported as a compatibility bridge while host apps move to the typed DSL.

## Documentation

| Guide | Live Docs | Source |
|-------|-----------|--------|
| Overview | [flashboard.pepperfm.com/overview](https://flashboard.pepperfm.com/overview) | [README](./README.md) |
| Installation | [Installation](https://flashboard.pepperfm.com/getting-started/installation) | [docs/installation.md](./docs/installation.md) |
| Resources | [Resources](https://flashboard.pepperfm.com/getting-started/resources) | [docs/resources.md](./docs/resources.md) |
| Forms | [Forms](https://flashboard.pepperfm.com/getting-started/forms) | [docs/forms.md](./docs/forms.md) |
| Tables | [Tables](https://flashboard.pepperfm.com/getting-started/tables) | [docs/tables.md](./docs/tables.md) |
| Workspaces | [Workspaces](https://flashboard.pepperfm.com/getting-started/workspaces) | [docs/workspaces.md](./docs/workspaces.md) |
| Extensions | [Extensions](https://flashboard.pepperfm.com/reference/extensions) | [docs/extensions.md](./docs/extensions.md) |
| Contracts | [Contracts](https://flashboard.pepperfm.com/reference/contracts) | [docs/contracts.md](./docs/contracts.md) |
| Upgrading | [Upgrading](https://flashboard.pepperfm.com/reference/upgrading) | [docs/upgrading.md](./docs/upgrading.md) |
| Releases | [Releases](https://flashboard.pepperfm.com/release/releases) | [docs/releases.md](./docs/releases.md) |
| Beta Checklist | [Beta Checklist](https://flashboard.pepperfm.com/release/beta-checklist) | [docs/beta-checklist.md](./docs/beta-checklist.md) |

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
