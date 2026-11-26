# Changelog

## Unreleased

- isolate table navigation, action dispatch, form hydration and list query/payload assembly without changing public constructor contracts
- apply related-record and title-field authorization to initial form options; preserve non-visible existing BelongsToMany memberships on update
- add regression gates for list, relation and metrics query counts and payload sizes, scoped metadata isolation and user changes
- update Symfony http-foundation, http-kernel, mailer and routing security patches in the development lockfile
- verify independent Laravel host installation and upgrade with precompiled assets

## 0.4.0 - 2026-11-26

- add bounded, escaped structured JSON detail entries with collapsible presentation and safe copy behavior
- add scoped, read-only relation timelines with deterministic ordering and paginated event loading
- support scoped related text paths in table search, sort and detail presentation
- render resource metrics in one responsive grid in declared order, respecting configured spans
- document the new resource APIs and expose manual DSL capabilities through AI inspection
- declare ext-mbstring and refresh bundled frontend assets

## 0.3.2 - 2026-11-26

- keep Laravel validation rules server-side in CRUD forms; fix Inertia closure rule serialization and preserve rule objects in relation validation
- omit hidden field defaults and prevent form extensions from reintroducing server rules
- remove tooltip delays throughout the panel
- reject direct read-only relation mutations with HTTP 403

## 0.3.1 - 2026-11-26

- add inclusive UTC date-range table filters with strict validation and field authorization
- add scoped sum and average metrics with primary-key deduplication and decimal string payloads
- support date_range, sum and avg in local MCP generation and capabilities
- verify SQLite and PostgreSQL, independent host preview and scoped order analytics

## 0.3.0 - 2026-11-26

- expand local AI Harness to 13 tools with capability discovery, registered resource inspection, validation and shared panel diagnostics
- generate read-only resources, BackedEnum fields, date filters and count/trend/breakdown metrics; preserve legacy specifications and JSON text responses through the public MCP API
- protect generated files with exact preview hashes, managed fingerprints, path validation and atomic publication under a local advisory lock
- omit mutation routes for explicit read-only resources and enforce policies on create/edit links and contextual relation creation
- fix paginated relation synchronization, stale membership validation and relation option selector loading
- render numeric form fields with UInputNumber; use theme-aware detail labels and remove technical payload counters
- verify independent Laravel hosts with and without their own frontend; update bundled assets and documentation

## 0.2.100 - 2026-11-26

- enforce related resource query scopes, query extensions, field query modifiers and record view policies for submitted BelongsTo and BelongsToMany selections
- reject unavailable related keys before saving parent attributes or pivot membership; preserve null and empty selections, require explicit model fallback when no related resource is registered
- add read-only flashboard:doctor diagnostics with JSON output and shared published asset manifest checks
- restore CI verification against SQLite and PostgreSQL, PHPStan and frontend builds

## 0.2.81 - 2026-11-26

- resolve nested relation manager keys by route name rather than positional controller arguments for records, options, attach, detach, replace and sync
- cover the real HTTP dispatch path with UUID and integer parent keys

## 0.2.80 - 2026-11-26

- add explicitly scoped collection/record actions and validated modal forms with domain result handling
- enforce action visibility and authorization, separate GET/HEAD form preparation from POST execution, and preserve legacy ActionContract implementations
- validate action inputs and support an idempotency key for host domain services
- normalize backed enum metric breakdowns and support custom display labels
- support PostgreSQL UUID equality search and type-aware qualified JOIN columns; reject invalid UUID action lookups with 404
- resolve missing relation title columns and paginate authorized related records and options correctly
- include HTTP integration tests in the default PHPUnit suite and rebuild the bundled UI assets
- update package and companion documentation, including upgrade instructions

## 0.2.72 and earlier 0.2.x runtime updates

- allow per-metric `span(1..12)` in a responsive 12-column dashboard grid, preserving previous default widths
- add chart body spacing to reduce clipping against card boundaries

- add opt-in Resource metrics (count, daily trends, breakdowns) and Nuxt UI charts
- resolve detail/edit/action records through Resource queries with query extensions
- enforce field-level permissions on direct form create/update requests
- remove unconfigured model attributes from detail and inverse-relation payloads; filter hidden form defaults and derived state
- restrict relation reads and updates to explicitly scoped related Resources
- keep Blade views inside the Composer package by default; always refresh published precompiled assets
- add regression coverage for scoped lookups, direct form writes, related data, form state, and installer behavior

## 0.2.1 - 2026-05-29

- add server-only relation query modifiers for `BelongsTo`, `HasOne`, and `HasMany` fields/managers; callbacks receive and must return an Eloquent `Builder`
- add `BelongsToMany` form fields with lazy multi-select option loading, selected hydration, validation, and transaction-safe pivot sync

## 0.2.0 - 2026-05-29

- add advanced resource form fields for dates, uploads, rich text, passwords, and relation selects
- add keyed typed-node label arguments across form fields, table nodes, and relation definitions
- add `BelongsTo` relation select fields with lazy protected option loading and relationship/resource inference
- add `HasOne` and `HasMany` inverse relation managers with safe attach, detach, replace, and sync-style controls
- move Laravel-bound screen payload assembly behind the Laravel integration layer and decouple core screen access from the concrete policy bridge
- update package docs, companion docs, raw markdown, and MCP-facing content for the new fields and relation manager APIs

## 0.1.12 - 2026-05-26

- tighten destructive row-action confirmation popover styling to use Nuxt UI content and utility classes

## 0.1.11 - 2026-05-26

- show a row-action confirmation popover for destructive table actions instead of using a browser confirm dialog

## 0.1.10 - 2026-05-26

- refresh published build assets by default when running `flashboard:build-assets` so stale manifests do not keep loading old UI chunks

## 0.1.9 - 2026-05-26

- add resource-level table row actions with explicit edit/delete action builders and policy-aware delete routing
- move generated resource row actions to `Resource::actions()` and remove table-builder row action configuration
- add typed textarea, number, and checkbox form fields with frontend renderers and generator support
- tighten policy-aware row actions, form data loading, and delete persistence coverage
- update package and companion docs, raw markdown, and MCP-facing content for the new actions API

## 0.1.8 - 2026-05-22

- expose column-level `searchable()` as global resource table search and `sortable()` as server-side sortable headers in the Inertia UI
- preserve filters/search/sort URL state while resetting pagination for table state changes
- restrict resource list search and sort queries to visible columns and treat global search wildcards as literal input
- document column search and sorting behavior in package and companion docs

## 0.1.7 - 2026-05-22

- add date table filters with Nuxt UI date picker controls and strict backend `whereDate()` filtering
- add `DateColumn` with opt-in PHP date formatting through `format()`
- add input-filter throttled autosubmit behavior and keep active table reset controls inline with totals
- document date columns, date filters, and improve docs MCP search for multi-token queries

## 0.1.4 - 2026-05-22

- add multiple select table filters with lazy backend loading, selected-value hydration, capped `whereIn()` input, and docs
- move simple service-provider singleton registrations to Laravel `#[Singleton]` attributes
- trim package boot logging setup and refine provider configuration examples

## 0.1.3 - 2026-05-21

- render table filters above resource index tables, preserve selected filter values, expose a reset action, support searchable select filters, and allow filters to target a separate query column
- add lazy backend-loaded select filters with server-side search, scroll pagination, selected-value hydration, and custom option resolvers

## 0.1.2 - 2026-05-21

- require only the model prompt in `flashboard:make-resource` and infer resource/id defaults for the rest
- render `id` form fields as frontend-enforced readonly inputs
- hide autogenerated `id` fields from create forms
- right-align resource table row actions and distribute data columns across the remaining table width

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
