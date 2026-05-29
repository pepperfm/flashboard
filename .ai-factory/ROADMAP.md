# Project Roadmap

> Add `BelongsToMany::make` as a backend-driven many-to-many form field that edits pivot membership through a lazy, authorization-aware multi-select while preserving the existing `BelongsTo`, `HasOne`, and `HasMany` semantics.

## Milestones

- [x] **01. Relation Field Foundation For BelongsToMany** — existing `BelongsTo`, `HasOne`, `HasMany`, relation option data sources, query modifiers, form normalization, and relation documentation provide the baseline for many-to-many work
- [x] **02. Product Boundary And DX Contract** — define `BelongsToMany` as a normal form field for pivot membership, separate from scalar `BelongsTo` fields and inverse `HasOne`/`HasMany` relation managers
- [x] **03. Public Field API And Typed Node** — add `BelongsToMany::make(string $key, ?string $label = null, ?string $relationship = null)` with relationship inference, resource/model overrides, search, pagination, record key, title attribute, max selection, and query modifier APIs
- [x] **04. Payload And Renderer Contract** — introduce stable `belongs_to_many` field type payloads and a multi-select renderer contract that carries selected options, lazy option URLs, relation metadata, and related resource route capabilities
- [x] **05. Laravel BelongsToMany Metadata Resolution** — resolve real Eloquent `BelongsToMany` metadata behind Laravel integration boundaries, including related model/resource, pivot table, pivot keys, parent/related keys, title/search columns, and safe fallback rules
- [x] **06. Lazy Options, Search And Selected Hydration** — provide protected backend-loaded options with search, pagination, selected-value hydration, resource query extensions, field-level `modifyQueryUsing`, and consistent authorization behavior
- [x] **07. Form State, Validation And Normalization** — normalize submitted values as a list of related record keys, validate them as array/list input, handle empty values as an empty selection, and keep selected option labels out of trusted write state
- [x] **08. Pivot Persistence And Transaction Safety** — sync pivot membership after the parent model is saved, re-resolve submitted IDs through the authorized related query, never create/delete related records implicitly, and keep first pass pivot attributes out of scope
- [x] **09. Frontend Multi-Select UX** — render `BelongsToMany` through a package-owned Vue/Nuxt UI multi-select with lazy search, infinite loading, selected chips, clearable/disabled/required states, detail links, and compact admin ergonomics
- [x] **10. Authorization, Tenant Scope And Failure Policy** — ensure option reads and sync writes respect current resource access, related resource visibility, query extensions, tenant scopes, and fail closed without leaking inaccessible record existence
- [x] **11. Documentation, Examples And MCP Surface** — update package docs, examples, docs site content, raw markdown routes, and MCP snippets so users understand when to use `BelongsTo`, `BelongsToMany`, `HasOne`, and `HasMany`
- [x] **12. QA And Compatibility Gates** — cover public API, metadata resolution, option loading, selected hydration, persistence, authorization, frontend types/build, PHPStan, and backward compatibility for existing relation fields

## BelongsToMany Implementation Map

This roadmap treats `BelongsToMany` as a form field, not as a relation manager. It edits the membership rows of an Eloquent many-to-many relation for the current resource record. The initial version should sync related record IDs only; pivot attributes belong to a future dedicated pivot editor.

### Product Boundary

- `BelongsTo` remains the scalar field for one local FK on the current model.
- `BelongsToMany` edits the current model's many-to-many pivot membership through an Eloquent `belongsToMany` relationship.
- `HasOne` and `HasMany` remain inverse relation managers that mutate related records' FK values and render outside normal scalar field persistence.
- `BelongsToMany` should appear inside resource create/edit forms as a selectable list of related records.
- A create submit must save the parent record first, then sync the pivot relation after the record has a key.
- An update submit must sync the pivot relation after scalar attributes are saved.
- The field must not create, update, or delete related records. It only attaches and detaches pivot membership.
- Pivot attributes, ordering, duplicate pivot rows, custom intermediate models, and inline related-record creation are explicitly out of scope for the first implementation.

### Target Authoring API

```php
use Illuminate\Database\Eloquent\Builder;
use Pepperfm\Flashboard\Core\Forms\Fields\BelongsToMany;

BelongsToMany::make('tags', 'Tags')
    ->resource(TagResource::class)
    ->titleAttribute('name')
    ->searchable(['name', 'slug'])
    ->optionsPerPage(20)
    ->modifyQueryUsing(static fn (Builder $query): Builder => $query->where('is_active', true));

BelongsToMany::make('visible_tags', 'Visible tags', 'tags')
    ->resource(TagResource::class)
    ->maxItems(5);
```

Rules:
- The first argument is the form field key and defaults to the relationship name.
- The second argument is the label, matching the existing typed field convention.
- The third argument is the Eloquent relationship name. If omitted, infer it from the key.
- `relationship(string $name)` remains available as a fluent override.
- `resource(string $resourceClass)` should override related resource inference.
- `model(string $modelClass)` may be available as an explicit fallback when no related resource is registered, mirroring `BelongsTo`.
- `titleAttribute(string $attribute)`, `searchable(list<string>|string|bool $columns = true)`, `recordKeyName(string $column)`, and `optionsPerPage(int $count)` should align with `BelongsTo`.
- `modifyQueryUsing(callable $callback)` must require callbacks to return `Illuminate\Database\Eloquent\Builder`, matching the current relation query modifier standard.
- `maxItems(int $count)` should cap the frontend selection and backend accepted list length when configured.
- The field value is always a list of related record keys, even when a single value is submitted by a non-JS fallback.

### Public Contract Work

Implementation should add or update these package-level contracts:

- `src/Core/Forms/Fields/BelongsToMany.php`
  - typed field class with constants for payload attributes
  - `make($key, $label, $relationship)` factory
  - fluent metadata and query modifier methods
  - default renderer set to a multi-select relation renderer
- `src/Core/Forms/Fields/Field.php`
  - add `TYPE_BELONGS_TO_MANY = 'belongs_to_many'`
- `src/Contracts/Forms/FieldRenderer.php`
  - add a stable renderer hint such as `RelationMultiSelect = 'relation_multi_select'`
- `src/Support/Schema/SchemaNodeNormalizer.php`
  - no special behavior unless typed node normalization exposes a new edge case
- form schema flattening/normalization call sites
  - ensure `BelongsToMany` field arrays survive nested sections/tabs with key, label, type, renderer, and relation metadata intact

Preferred normalized payload keys:

- `type`: `belongs_to_many`
- `renderer`: `relation_multi_select`
- `key`
- `label`
- `relationship`
- `related_model`
- `related_resource`
- `related_table`
- `pivot_table`
- `foreign_pivot_key`
- `related_pivot_key`
- `parent_key`
- `related_key`
- `record_key_name`
- `title_attribute`
- `search_columns`
- `options_url`
- `options_per_page`
- `selected_options`
- `related_routes`
- `max_items`
- `required`
- `disabled`

### Laravel Resolution And Data Sources

Many-to-many Eloquent details should stay under `src/Integration/Laravel/` or relation-specific core resolvers that do not leak host app assumptions into contracts.

Required resolver behavior:

- Instantiate the current resource model from `Resource::model()`.
- Resolve the configured relationship method and require an `Illuminate\Database\Eloquent\Relations\BelongsToMany` instance.
- Read related model from `$relation->getRelated()`.
- Read pivot table from `$relation->getTable()`.
- Read foreign pivot key from `$relation->getForeignPivotKeyName()`.
- Read related pivot key from `$relation->getRelatedPivotKeyName()`.
- Read parent key from `$relation->getParentKeyName()`.
- Read related key from `$relation->getRelatedKeyName()`.
- Infer related resource from `ResourceRegistry::resourcesForModel()` when no explicit `resource()` was provided.
- Reject ambiguous related resource inference and require `resource()` in that case.
- Preserve explicit `model()` as an option-query fallback only after a real `BelongsToMany` relationship has resolved; persistence still requires the relationship for pivot sync.
- Use `titleAttribute('name')` by default and default search columns to the title attribute.

Options and hydration can extend the current relation options endpoint if the contract remains clean, or use a dedicated many-to-many endpoint if single-select assumptions would make the code brittle. Either way, the backend must:

- load selectable related records lazily
- support search across configured search columns
- paginate with deterministic `has_more` and `next_page` metadata
- hydrate already selected values through a `selected[]` or equivalent parameter
- apply related resource query extension hooks where available
- apply `modifyQueryUsing(fn (Builder $query): Builder => ...)`
- return only stable option shape: `value`, `label`, and optional `url`
- never trust frontend-provided labels or URLs during persistence

### Form State And Validation

The persisted input contract should be intentionally small:

- Submitted field value is `array<int|string>` after normalization.
- `null`, empty string, and empty array normalize to an explicit empty list; an omitted key means the relation was not submitted and should not be synced.
- Scalar values normalize to a one-item list for resilience, but frontend should always send arrays.
- Duplicate submitted IDs are removed while preserving deterministic order where practical.
- Non-scalar submitted values are rejected before sync.
- Required `BelongsToMany` means the final normalized list must contain at least one related record key.
- `maxItems()` means the normalized list count must not exceed the configured maximum.
- Related IDs must be re-resolved through the authorized related query before sync.
- Validation should fail with a normal form error when submitted IDs are invalid or inaccessible.

### Persistence And Pivot Safety

`ResourceFormPersister` should separate scalar model attributes from relationship sync work.

Expected flow:

- Before `forceFill`, remove `BelongsToMany` keys from scalar data so pivot arrays are not written as model attributes.
- Normalize and retain relation sync payloads in a dedicated structure.
- Save the parent model in the same transaction as pivot sync when the package owns the persistence flow.
- After parent save, call the resolved Eloquent relationship's `sync($ids)` with the authorized related keys.
- On create, sync only after the model key exists.
- On update, scalar data and pivot sync should either both complete or both roll back.
- Respect disabled/hidden field semantics if those fields are already excluded from submitted persistence.
- Run form and resource after-save hooks after the pivot sync if hooks are expected to observe final relation membership; document the chosen ordering.
- Do not support pivot attributes in the first pass.
- Do not support destructive deletes of related records.
- Avoid silently detaching records when the field is omitted from the submitted form due to authorization or disabled UI state.

### Frontend Rendering

Preferred frontend shape:

- Add `resources/js/components/flashboard/forms/fields/FBRelationMultiSelect.vue`.
- Add the renderer mapping in `FormFieldRenderer.vue`.
- Reuse safe pieces from `FBRelationSelect.vue` where practical:
  - lazy request scheduling
  - search debounce
  - pagination/infinite scroll
  - selected option hydration
  - URL-safe option links
  - request failure state
- Keep the component controlled by an array model value.
- Use Nuxt UI primitives for searchable multi-select behavior and selected chip rendering.
- Keep the layout compact: a single field wrapper with selected chips, clear button, loading state, and optional open-record buttons.
- Avoid visible explanatory text about implementation details; empty and error states should be short operational labels.
- Do not emit console logs.
- Do not store selected labels in the form model; labels are display-only option metadata.

Required UI states:

- no options loaded yet
- loading first page
- search loading
- loading next page
- selected values already hydrated from existing record
- selected value no longer visible or no longer accessible
- empty search result
- request failed
- disabled field
- required field with no values
- max items reached

### Authorization, Scope And Failure Policy

- Check current resource access before exposing or syncing a `BelongsToMany` field.
- Check related resource visibility before exposing related options.
- Reuse resource-level query extensions and future tenant scope hooks for option loading and selected hydration.
- Apply field-level `modifyQueryUsing` after the base related query and resource extensions are established.
- During sync, compare submitted IDs against the authorized resolved IDs, not raw database existence.
- Return validation-style errors for invalid selections in form submits.
- Return 403/404 from option endpoints when the resource, relation, or related resource is inaccessible.
- Avoid error messages that reveal whether an inaccessible related record exists.
- Keep route keys and relation names constrained to known field metadata, not arbitrary request input.

### Minimal Logging Policy

- Normal option loading, empty selections, validation failures, and denied UI actions should stay silent.
- Log only exceptional server-side conditions:
  - invalid `BelongsToMany` relationship declaration
  - ambiguous related resource inference
  - query modifier returning a non-Builder value
  - unexpected option loader failure
  - unexpected pivot sync exception
- Log only sanitized operational context:
  - resource class
  - field key
  - relationship name
  - related resource class when known
  - action name such as `options`, `hydrate`, or `sync`
  - failure category
  - exception class
- Never log submitted IDs, labels, search terms, full request payloads, model attributes, pivot values, or user-entered form data.

### Documentation And MCP Surface

Update package docs and the docs companion project in the same implementation line:

- `docs/forms.md`
  - add `BelongsToMany::make` examples
  - document array/list form state
  - clarify selected option hydration and lazy search
- `docs/resources.md`
  - describe relationship field authoring alongside `BelongsTo`
  - show explicit third-argument relationship override
  - show explicit `resource()` override when inference is ambiguous
- `docs/contracts.md`
  - document `belongs_to_many` payload and `relation_multi_select` renderer stability
  - document that pivot attributes are out of scope
- `docs/upgrading.md`
  - note beta payload additions and any renderer enum additions
- `examples/`
  - add a simple many-to-many demo resource pair when host validation fixtures allow it
- `flashboard-docs/content/`
  - mirror public docs changes
- `flashboard-docs/server/mcp/`
  - review MCP docs snippets/search surfaces for relation field drift
- `flashboard-docs/server/routes/raw/`
  - review raw markdown outputs if headings, filenames, or examples change

### QA Gates

PHP package tests:

- `BelongsToMany::make()` accepts key, label, and relationship.
- Relationship inference matches `BelongsTo` expectations where applicable.
- `relationship()`, `resource()`, `model()`, `titleAttribute()`, `searchable()`, `recordKeyName()`, `optionsPerPage()`, `maxItems()`, and `modifyQueryUsing()` serialize deterministic attributes.
- Metadata resolver accepts Eloquent `BelongsToMany` and rejects unsupported relation types.
- Ambiguous related resource inference requires explicit `resource()`.
- Option endpoint returns paginated searchable options.
- Selected hydration accepts multiple selected values.
- Query modifier must return `Builder`.
- Form data source includes selected options for existing records.
- Persister excludes pivot field keys from scalar `forceFill`.
- Create flow saves parent then syncs selected related IDs.
- Update flow syncs selected related IDs transactionally.
- Missing or disabled field does not detach existing relations accidentally.
- Invalid, inaccessible, or duplicate IDs are handled deterministically.
- Authorization blocks option reads and pivot sync.

Frontend checks:

- renderer mapping resolves `relation_multi_select`.
- TypeScript model value is an array.
- initial selected options render without extra user action.
- search, pagination, failed request, disabled, required, and max item states render.
- selected labels stay display-only and submitted model stays key-only.

Package validation:

- focused PHPUnit tests for field API, metadata, options, hydration, and persistence
- full PHPUnit suite
- PHPStan with the project memory limit convention
- frontend typecheck/build
- docs site typecheck/build after docs updates
- `git diff --check` in package and docs repos
- host-app smoke for a resource with `BelongsToMany::make` when a runnable validation host is available

Compatibility checks:

- existing `BelongsTo` payload and single-select frontend behavior remain unchanged.
- existing `HasOne` and `HasMany` relation manager behavior remains unchanged.
- existing relation query modifier semantics remain Builder-return-only.
- beta payload changes are documented before release.
- MCP/raw documentation surfaces stay aligned with public docs.

## Completed

| Milestone | Date |
|-----------|------|
| 01. Relation Field Foundation For BelongsToMany | 2026-05-29 |
| Legacy: BelongsTo Relation Field Baseline | 2026-05-28 |
| Legacy: Inverse Relation Product Boundary | 2026-05-29 |
| Legacy: Public Relation Definition API | 2026-05-29 |
| Legacy: Laravel Relation Metadata Resolution | 2026-05-29 |
| Legacy: Relation Manager Payload Contract | 2026-05-29 |
| Legacy: Nested Relation Data Sources And Routes | 2026-05-29 |
| Legacy: HasOne Manager UX | 2026-05-29 |
| Legacy: HasMany Manager UX | 2026-05-29 |
| Legacy: Persistence And Safety Semantics | 2026-05-29 |
| Legacy: Authorization, Tenant Scope And Failure Policy | 2026-05-29 |
| Legacy: Documentation, Examples And MCP Surface | 2026-05-29 |
| Legacy: QA And Compatibility Gates | 2026-05-29 |
