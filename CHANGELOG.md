# Changelog

## 0.1.1 - 2026-05-21

- add a typed public resource schema DSL for tables, forms, and detail/infolist screens
- normalize typed nodes and legacy arrays through shared runtime schema normalizers
- expose unified resource surface metadata, including access-filtered resource-owned pages
- update `flashboard:make-resource` and example resources to generate the typed DSL by default
- expand Inertia + Nuxt UI screen rendering to use richer schema metadata for tables, forms, sections, tabs, toggles, selects, and detail entries
- declare frontend package-manager options on `flashboard:install`
- support Inertia Laravel 3 for Laravel 13 host applications
- exclude development-only files from Composer path mirrors and release archives

## 0.1.0-beta.1

- bootstrap Laravel package foundation
- add panel, page, resource, table, form, detail, action, navigation contracts
- add runtime metadata, payload assembly, registries, and discovery
- add auth flow, panel shell, permission-aware navigation, and Eloquent-backed list screens
- add form persistence, detail hydration, relations, action execution, and custom workspace support
- add versioned UI payload contracts, renderers, policy bridge, extension hooks, playground tooling, and test scaffold
- add fluent inline configuration, prompt-driven `make-resource` / `make-page` commands, and auto-discovery for `app/Flashboard`
- add provider-first panel configuration with `make-provider` and keep inline config as compatibility mode
