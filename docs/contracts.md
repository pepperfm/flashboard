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

## Authorization Contract

- `policy()` opt-in on resources
- ability maps for actions, fields, relations
- page access through `canAccess()`
