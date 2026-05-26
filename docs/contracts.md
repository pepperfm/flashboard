# Contracts

## Stability Policy

Flashboard currently exposes a beta contract surface.

### Stable-Intent Layers

- `src/Contracts/*`
- route naming conventions under `flashboard.*`
- versioned screen payload envelope with `schema_version`

### Soft-Stable Layers

- builder implementations in `src/Core/*/Builders`
- runtime assembler internals
- layout payload details

### Beta Rules

- breaking changes are allowed until `1.0`
- every breaking change must be documented in `CHANGELOG.md`
- payload schema changes must update `SchemaVersion`
- renamed contracts must include upgrade notes

## Renderer Contract

- JSON consumers should read `schema_version`
- Blade consumers should treat layout state, overlays, and notifications as public payloads

## Public Resource API

- resource configuration is split into dedicated surfaces: `table()`, `form()`, `detail()`, `infolist()`, `actions()`, and `pages()`
- `detail()` remains supported and `infolist()` is its friendly alias for concept-aligned resource APIs
- typed schema nodes are the preferred package-facing API for columns, fields, sections, tabs, filters, scopes, and entries
- legacy array definitions remain supported as a compatibility bridge during the DSL migration
- runtime consumers should depend on normalized payload output, not on ad hoc legacy array keys such as `name`
- table row action payloads are backend-driven and permission-aware; renderers should consume normalized row `actions` instead of inventing view/edit/delete buttons
- resource `actions()` owns both executable resource actions and table row action declarations; renderers consume row-level payloads from row `actions`

## Authorization Contract

- `policy()` opt-in on resources
- ability maps for actions, fields, relations
- page access through `canAccess()`
