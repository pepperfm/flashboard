# Project Roadmap

> Extend Flashboard relation editing beyond `BelongsTo` by introducing explicit `HasOne` and `HasMany` relation managers with safe selection, nested resource actions, and deterministic backend-driven payloads.

## Milestones

- [x] **01. BelongsTo Relation Field Baseline** — existing relation metadata resolution, lazy single-select rendering, scalar FK persistence, authorization checks, and docs provide the foundation for inverse relation work
- [x] **02. Inverse Relation Product Boundary** — define `HasOne` and `HasMany` as explicit relation manager surfaces rather than silent scalar fields, including where they may appear on detail/edit screens
- [x] **03. Public Relation Definition API** — introduce typed `HasOne` and `HasMany` resource relation definitions with `make(string $key, ?string $label = null, ?string $relationship = null)`, resource inference, and explicit overrides
- [x] **04. Laravel Relation Metadata Resolution** — resolve Eloquent `hasOne` and `hasMany` metadata behind Laravel integration boundaries, including local key, foreign key, related model, related resource, and nullable detach capability
- [x] **05. Relation Manager Payload Contract** — add stable payload shapes for single-record and collection relation managers, including records, pagination, action availability, attach options, empty states, and related-resource navigation
- [x] **06. Nested Relation Data Sources And Routes** — add protected endpoints for loading related records, loading attachable options, and executing create, attach, detach, replace, and sync-like actions with authorization and scoping
- [x] **07. HasOne Manager UX** — render a one-record manager with empty, loading, error, existing-record, create, attach, open, edit, detach, and replace states using package-owned Vue/Nuxt UI wrappers
- [x] **08. HasMany Manager UX** — render a collection manager with related rows, search, pagination, create, attach, detach, optional bulk actions, and explicit replace/sync affordances only when enabled
- [x] **09. Persistence And Safety Semantics** — implement transaction-safe mutations that write related records' FK values, never delete by default, and require explicit opt-in for detach, replace, or sync behavior
- [x] **10. Authorization, Tenant Scope And Failure Policy** — ensure all relation reads and writes respect resource visibility, Laravel policies, query extensions, tenant scoping, and fail closed for invalid or inaccessible relationships
- [x] **11. Documentation, Examples And MCP Surface** — update package docs, generated examples, upgrade notes, docs mirror, raw markdown routes, and MCP artifacts with clear `BelongsTo` versus `HasOne`/`HasMany` guidance
- [x] **12. QA And Compatibility Gates** — cover PHP payloads, Laravel metadata resolution, mutation safety, Vue type safety, frontend build, existing `BelongsTo` compatibility, and host-app browser smoke when a runnable validation host is available

## HasOne And HasMany Implementation Map

This roadmap treats `HasOne` and `HasMany` as relation manager surfaces. They may look like fields in the admin UI, but they do not behave like normal form fields because saving them mutates related records, not only the current resource record.

### Product Boundary

- `BelongsTo` remains the normal form field for one local FK on the current model.
- `HasOne` manages zero or one related record whose FK usually lives on the related model.
- `HasMany` manages a related collection whose FK values usually live on many related records.
- Default behavior should be safe and explicit:
  - view related records
  - open related records
  - create a related record in nested context
  - attach existing records only when enabled
  - detach or replace only when enabled and confirmed
- Do not implement hidden automatic inverse syncing from a normal form submit.
- Do not treat many-to-many as `HasMany`; reserve `BelongsToMany` or a dedicated relation manager for pivot semantics.

### Target Authoring API

```php
use Pepperfm\Flashboard\Core\Relations\HasMany;
use Pepperfm\Flashboard\Core\Relations\HasOne;

public static function relations(): array
{
    return [
        HasOne::make('profile', 'Profile')
            ->resource(ProfileResource::class)
            ->attachable()
            ->detachable(),

        HasMany::make('orders', 'Orders')
            ->resource(OrderResource::class)
            ->searchable(['number', 'status'])
            ->perPage(10)
            ->attachable(),

        HasMany::make('recent_orders', 'Recent orders', 'orders')
            ->resource(OrderResource::class)
            ->readOnly(),
    ];
}
```

Rules:
- The first argument is the relation surface key used in payloads and routes.
- The second argument is the label, matching the typed schema `make($key, $label)` convention.
- The third argument is the Eloquent relationship name. If omitted, infer it from the key.
- `relationship(string $name)` remains available as a fluent override.
- `resource(string $resourceClass)` remains available as a fluent override, while the default should be inferred from the related model and registered resources.
- `HasOne` should default to a single-card manager.
- `HasMany` should default to a compact related-record table/list manager.
- Selection and synchronization behavior must be opt-in through explicit methods such as `attachable()`, `detachable()`, `replaceable()`, or `syncable()`.

### Public Contract Work

- Add typed relation definition classes under `src/Core/Relations/`:
  - `HasOne`
  - `HasMany`
- Keep them aligned with `RelationDefinitionContract` and current `Resource::relations()` usage.
- Add stable relation type values:
  - `has_one`
  - `has_many`
- Add explicit payload keys:
  - `type`
  - `key`
  - `label`
  - `relationship`
  - `related_model`
  - `related_resource`
  - `local_key`
  - `foreign_key`
  - `record_key_name`
  - `title_attribute`
  - `search_columns`
  - `records_url`
  - `options_url`
  - `actions`
  - `records`
  - `selected_record`
  - `selected_records`
  - `pagination`
  - `empty_state`
  - `read_only`
- Preserve existing array-based relation definitions as compatibility input where practical.

### Laravel Resolution And Data Sources

- Resolve Eloquent-specific `HasOne` and `HasMany` metadata behind `Integration/Laravel` services rather than widening the current `Contracts\Resources\Resource` beta exception.
- Infer metadata from real Eloquent relations where possible:
  - relationship method exists on the current model
  - relation instance is `Illuminate\Database\Eloquent\Relations\HasOne` or `HasMany`
  - local key comes from the relation
  - foreign key comes from the relation
  - related model comes from the relation query/model
  - related resource is inferred from the registered resource model map
- Add a relation records data source for nested related-record loading.
- Add an attach options data source for selectable existing records when `attachable()` is enabled.
- Use related resource queries when available so query extensions and future tenant scopes apply consistently.
- Support selected-record hydration for `HasOne` and paginated related-record hydration for `HasMany`.
- Reject invalid relation keys, unsupported relation classes, ambiguous resource inference, inaccessible resources, and disallowed mutation modes with 404/403 as appropriate.

### Routes And Mutation Actions

- Add protected nested relation routes under the current resource route group.
- Read routes:
  - load one `HasOne` record
  - load paginated `HasMany` records
  - load attachable options with search and selected hydration
- Mutation routes:
  - create related record in nested context
  - attach existing related record
  - detach related record when nullable and enabled
  - replace existing `HasOne` record when enabled
  - detach selected `HasMany` records when enabled
  - replace/sync selected `HasMany` records only when explicitly enabled
- Wrap write operations in transactions and return deterministic payload fragments or redirects compatible with the existing Inertia flow.
- Keep destructive or moving operations behind explicit confirmations in the UI.

### Frontend Rendering

- Add package-owned relation manager wrappers:
  - `resources/js/components/flashboard/relations/FBHasOneRelationManager.vue`
  - `resources/js/components/flashboard/relations/FBHasManyRelationManager.vue`
- Keep relation manager rendering separate from normal form-field rendering unless a shared container can be reused without blurring persistence semantics.
- Use Nuxt UI primitives for buttons, menus, tables/lists, popovers, modals, empty states, and loading states.
- `HasOne` required states:
  - empty relation
  - selected/existing record summary
  - loading
  - failed load
  - create related record
  - attach existing record
  - open/edit related record
  - detach/replace disabled or confirmed
- `HasMany` required states:
  - empty collection
  - paginated related records
  - search loading
  - failed load
  - create related record
  - attach existing records
  - detach selected records
  - explicit replace/sync flow when enabled
- Preserve compact, work-focused admin layout; relation managers should feel like operational controls, not marketing cards.

### Persistence And Safety

- `HasOne` attach should set the related record's FK to the current record key.
- `HasOne` replace should detach the previous related record only when detach is allowed, then attach the new record in one transaction.
- `HasOne` detach should be disabled when the related FK is non-nullable or policy access is missing.
- `HasMany` attach should set selected related records' FK to the current record key.
- `HasMany` detach should only clear FK values when nullable and enabled.
- `HasMany` replace/sync should be disabled by default because it can move records away from another parent or detach records unexpectedly.
- Never delete related records by default; deletion belongs to explicit related resource actions.
- Prefer related resource form and action hooks for custom business rules instead of embedding host-specific workflow logic in relation managers.

### Authorization, Scope And Failure Policy

- Check current resource access before loading or mutating a relation.
- Check related resource view/create/update permissions before exposing corresponding actions.
- Check per-record permissions for open, edit, detach, attach, and replace.
- Apply related resource query extensions to relation records and attach options.
- Prevent attach options from offering records the user cannot view or attach.
- Fail closed when metadata cannot be resolved or the relationship is not a supported Eloquent relation.
- Avoid leaking record existence through different error messages where authorization denies access.

### Minimal Logging Policy

- Keep normal relation loading, empty states, validation failures, and denied UI actions silent.
- Use Laravel's host-configured logger only for exceptional server-side conditions:
  - malformed relation manager route key
  - resolver failure caused by an invalid relationship declaration
  - mutation rejected because the configured mode is unsafe or unsupported
  - unexpected exception during relation loading or mutation
- Log only sanitized operational context:
  - resource class
  - relation key
  - relationship name
  - relation type
  - related resource class when known
  - action name
  - failure category
  - exception class
- Never log search terms, selected labels, record titles, model attributes, full request payloads, or user-submitted form values.

### Documentation And MCP Surface

- Update `docs/resources.md` with relation manager authoring examples.
- Update `docs/forms.md` to reinforce that `BelongsTo` is a scalar form field while `HasOne` and `HasMany` are inverse relation managers.
- Update `docs/contracts.md` with relation payload stability notes.
- Update `docs/upgrading.md` if existing `relations()` arrays receive a typed-node migration path.
- Mirror public docs changes in `flashboard-docs/content/`.
- Review `flashboard-docs/server/mcp/` and `flashboard-docs/server/routes/raw/` for drift when headings, examples, or searchable snippets change.

### QA Gates

- PHP tests:
  - `HasOne::make()` and `HasMany::make()` accept key, label, and relationship
  - relationship inference from relation keys
  - explicit `relationship()` and `resource()` overrides
  - Eloquent `HasOne` and `HasMany` metadata resolution
  - ambiguous related-resource inference requires explicit `resource()`
  - payloads include deterministic records/options/action URLs
  - attach, detach, replace, and sync modes fail closed unless enabled
  - authorization blocks inaccessible relation reads and writes
- Frontend checks:
  - relation manager payload types cover `has_one` and `has_many`
  - renderer selection resolves relation managers without touching normal field renderers
  - empty, loading, failed, existing, attach, detach, and pagination states render correctly
  - destructive actions require explicit confirmation
- Package checks:
  - focused PHPUnit feature tests for relation payloads, data sources, routing, and mutations
  - PHPStan
  - frontend typecheck/build
  - existing `BelongsTo` tests remain unchanged
  - host-app browser smoke test for a resource with `BelongsTo` on one side and `HasOne`/`HasMany` managers on the inverse side remains pending until a runnable validation host is available
- Contract checks:
  - existing form field payloads stay unchanged
  - existing detail relation payloads remain backward compatible or receive documented beta upgrade notes
  - docs MCP/raw markdown output stays aligned after documentation updates

## Completed

| Milestone | Date |
|-----------|------|
| 01. BelongsTo Relation Field Baseline | 2026-05-28 |
| 02. Inverse Relation Product Boundary | 2026-05-29 |
| 03. Public Relation Definition API | 2026-05-29 |
| 04. Laravel Relation Metadata Resolution | 2026-05-29 |
| 05. Relation Manager Payload Contract | 2026-05-29 |
| 06. Nested Relation Data Sources And Routes | 2026-05-29 |
| 07. HasOne Manager UX | 2026-05-29 |
| 08. HasMany Manager UX | 2026-05-29 |
| 09. Persistence And Safety Semantics | 2026-05-29 |
| 10. Authorization, Tenant Scope And Failure Policy | 2026-05-29 |
| 11. Documentation, Examples And MCP Surface | 2026-05-29 |
| 12. QA And Compatibility Gates | 2026-05-29 |
