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
- normalized form fields expose stable renderer hints, including `input`, `textarea`, `select`, `checkbox`, `switch`, `date`, `file_upload`, `rich_text`, `relation_select`, and `relation_multi_select`
- password fields use the normal `input` renderer with `input_type=password`; renderers must not persist or display hydrated secret values
- file upload payloads may expose `existing_files` metadata, but must not expose file contents or raw temporary upload details
- relation select payloads may expose `relationship`, `related_model`, `related_resource`, `foreign_key`, `owner_key`, `record_key_name`, `related_table`, `title_attribute`, `search_columns`, `options_url`, `options_per_page`, `selected_option`, and `related_routes`
- relation multi-select payloads may expose `relationship`, `related_model`, `related_resource`, `related_table`, `pivot_table`, `foreign_pivot_key`, `related_pivot_key`, `parent_key`, `related_key`, `record_key_name`, `title_attribute`, `search_columns`, `options_url`, `options_per_page`, `max_items`, `selected_options`, and `related_routes`
- `relation_select` options are loaded through protected resource routes and return `items` plus `meta.has_more` / `meta.next_page`; selected values must be hydrated through the same related-resource query extensions and field-level `modifyQueryUsing()` rules as normal option pages
- `relation_multi_select` uses the same protected options route, accepts repeated `selected[]` values for selected hydration, submits an array of scalar related keys, and treats pivot attributes as out of scope
- inverse relation manager payloads use `type=has_one` or `type=has_many` and may expose `records_url`, `options_url`, `actions`, `records`, `selected_record`, `selected_records`, `pagination`, `empty_state`, `related_model`, `related_resource`, `local_key`, `foreign_key`, `record_key_name`, `title_attribute`, `search_columns`, and `read_only`
- relation manager action payloads are explicit objects with `key`, `method`, `url`, `label`, `icon`, `visible`, and `requires_confirmation`; renderers should not infer mutation URLs from relation keys
- relation manager records/options/mutations must be resolved server-side through protected resource routes, related resource query extensions, and per-manager query modifiers; selected IDs from clients are inputs to re-resolution, not trusted relationship metadata
- runtime hook payloads and record context redact password values and replace file values with minimal metadata before dispatch
- Blade consumers should treat layout state, overlays, and notifications as public payloads
- layout notifications expose `id`, `level`, and `message`; renderers may display them as transient toasts or equivalent feedback

## Public Resource API

- resource configuration is split into dedicated surfaces: `table()`, `form()`, `detail()`, `infolist()`, `actions()`, and `pages()`
- `detail()` remains supported and `infolist()` is its friendly alias for concept-aligned resource APIs
- typed schema nodes are the preferred package-facing API for columns, fields, sections, tabs, filters, scopes, and entries
- keyed typed schema factories accept `make(string $key, ?string $label = null)`; use the second argument for static labels and `->label()` for later overrides
- `BelongsTo::make(string $key, ?string $label = null, ?string $relationship = null)` adds an optional third relationship override while preserving the same label convention
- `BelongsToMany::make(string $key, ?string $label = null, ?string $relationship = null)` declares a form-level pivot sync field; it requires a real Eloquent `BelongsToMany` relationship for persistence and may cap submitted arrays with `maxItems()`
- `HasOne::make(string $key, ?string $label = null, ?string $relationship = null)` and `HasMany::make(string $key, ?string $label = null, ?string $relationship = null)` declare inverse relation managers; relationship inference is based on the key, while `relationship()` and `resource()` remain explicit overrides
- relation query callbacks such as `BelongsTo::modifyQueryUsing()`, `BelongsToMany::modifyQueryUsing()`, `HasOne::modifyRecordsQueryUsing()`, `HasMany::modifyAttachOptionsQueryUsing()`, and relation-manager `modifyQueryUsing()` are server-only closures, are never serialized into payloads, and must return an Eloquent `Builder`
- legacy array definitions remain supported as a compatibility bridge during the DSL migration
- runtime consumers should depend on normalized payload output, not on ad hoc legacy array keys such as `name`
- table row action payloads are backend-driven and permission-aware; renderers should consume normalized row `actions` instead of inventing view/edit/delete buttons
- resource `actions()` owns both executable resource actions and table row action declarations; renderers consume row-level payloads from row `actions`

## Authorization Contract

- `policy()` opt-in on resources
- ability maps for actions, fields, relations
- page access through `canAccess()`
