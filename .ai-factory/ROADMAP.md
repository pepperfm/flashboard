# Project Roadmap

> Build Flashboard into an installable Laravel admin runtime with declarative resources, consistent UX, and strong escape hatches for real operator workflows.

## Milestones

- [x] **01. Product Concept & Positioning** — define the product thesis, target use cases, DX principles, and the distinction between CRUD tooling and a full admin runtime
- [x] **02. Project Context & Architecture Baseline** — formalize project description, architectural boundaries, AI context, and the target modular package shape
- [x] **03. Package Foundation & Boot Process** — create the Composer package skeleton, service provider, config publishing, panel bootstrap entrypoints, and installation flow for host apps
- [x] **04. Public Contracts & Builder API** — define the stable package-facing contracts for panel, resource, table, form, detail, actions, pages, navigation, and extension hooks
- [x] **05. Resource Registry & Runtime Kernel** — implement the internal registry, metadata lifecycle, screen resolution, payload assembly pipeline, and shared runtime orchestration
- [x] **06. Routing, Auth Integration & Panel Access** — wire Laravel routes, middleware, auth guards, panel login flow, session handling, and protected `/admin` entry
- [x] **07. Layout Shell, Navigation & Panel Chrome** — ship the reusable admin shell with header, sidebar, breadcrumbs, user menu, dashboard frame, and permission-aware navigation
- [x] **08. Table Engine & List Resource Screens** — deliver the first production-ready list experience with columns, filters, scopes, search, sorting, pagination, row actions, and bulk actions
- [x] **09. Form Engine & Create/Edit Workflows** — support sections, tabs, fields, validation, defaults, mutation hooks, save pipelines, notifications, and complete create/edit resource flows
- [x] **10. Detail Views, Infolists & Action Framework** — add read-only detail screens, infolist-style rendering, contextual actions, confirmations, modals, and reusable action execution rules
- [x] **11. Relations, Nested Contexts & Resource Composition** — support relation managers, nested resources, relation hubs, shared query abstractions, and cross-resource composition patterns
- [x] **12. Custom Pages & Operator Workspaces** — introduce custom page contracts for queues, review screens, processing consoles, approval flows, and other non-CRUD operator scenarios
- [x] **13. Backend-Driven UI Contract & Rendering Layer** — stabilize the normalized payload protocol and rendering primitives so the UI layer can remain consistent and swappable over time
- [x] **14. Authorization Model & Visibility Rules** — provide policy integration, fine-grained action visibility, field visibility, navigation gating, and permission-aware runtime decisions
- [x] **15. Theming, UX Consistency & Interaction Patterns** — unify notifications, overlays, confirmations, empty states, loading states, keyboard flows, and design tokens for a coherent admin UX
- [x] **16. Extension Surface & Escape Hatches** — harden custom query hooks, custom save logic, page render overrides, action extensions, field renderers, and integration points without framework fights
- [x] **17. Developer Tooling & Local DX** — add stubs, testing fixtures, demo resources, package commands, local playground app, and debugging helpers that make package adoption fast
- [x] **18. Test Strategy & Quality Gates** — build coverage for unit, integration, feature, and package-host compatibility tests, plus static analysis and regression protection for public contracts
- [x] **19. Documentation, Examples & Adoption Guides** — write installation docs, quick-start guides, advanced customization docs, architecture docs, and real examples for CRUD and operator workflows
- [x] **20. MVP Validation In Real Host App** — integrate the package into a real Laravel application, validate the 80/20 design, close API gaps, and refine contracts from real usage
- [x] **21. Beta Readiness & Compatibility Hardening** — finalize upgrade guarantees, package versioning rules, backward-compatibility expectations, and release hardening for external adopters
- [x] **22. Public Beta Release** — publish the first beta release with documented capabilities, known limits, migration guidance, and a clear feedback loop for early users
- [x] **23. Advanced Form Field Contract Expansion** — extend the form field contract for date, file, and rich-text renderers while keeping `PasswordInput` compatible with the existing input renderer where possible
- [x] **24. DateInput & PasswordInput Runtime** — ship low-risk scalar fields first, including deterministic payload normalization, inferred validation, Vue wrappers, generated stubs, and create/edit hydration
- [x] **25. FileUpload Runtime & Persistence Boundary** — add secure upload field support with host-owned storage configuration, multipart Inertia submission, validation, previews, replace/remove semantics, and minimal safe diagnostics
- [x] **26. RichText Runtime & Content Safety** — add a rich text field backed by Nuxt UI Editor with explicit content format choices, sanitization guidance, validation, and predictable persistence hooks
- [x] **27. Advanced Form Field Documentation & Examples** — update resource/form docs, examples, host-app walkthroughs, and docs MCP/raw markdown surfaces for DateInput, FileUpload, RichText, and PasswordInput
- [x] **28. Advanced Form Field QA & Compatibility Hardening** — cover PHP normalization, Laravel validation/persistence, TypeScript payload typing, browser smoke tests, file-upload edge cases, and upgrade notes

## Advanced Form Field Implementation Map

This roadmap expands the existing form system without replacing the current `Field` -> `FormSchemaNormalizer` -> `FieldRenderer` -> package-owned Vue wrapper flow. The canonical authoring API remains `$form->schema([...])`; `fields()`, `sections()`, and `tabs()` stay compatibility helpers.

### Shared Contract Work

- Add typed PHP field classes under `src/Core/Forms/Fields/` and keep each class small, fluent, and explicit:
  - `DateInput`
  - `FileUpload`
  - `RichText`
  - `PasswordInput`
- Extend `src/Core/Forms/Fields/Field.php` with new type constants only where the runtime needs distinct behavior:
  - `TYPE_DATE = 'date'`
  - `TYPE_FILE = 'file'`
  - `TYPE_RICH_TEXT = 'rich_text'`
  - `TYPE_PASSWORD = 'password'` only if password needs type-based policy beyond `input_type=password`
- Extend `src/Contracts/Forms/FieldRenderer.php` conservatively:
  - `Date = 'date'`
  - `FileUpload = 'file_upload'`
  - `RichText = 'rich_text'`
  - keep `PasswordInput` on `Input` with `input_type=password` unless a dedicated reveal/toggle renderer becomes part of the stable UX contract
- Update `src/Core/Forms/Normalization/FormSchemaNormalizer.php` so every new typed field normalizes into a deterministic payload, inferred Laravel rules, and stable layout metadata.
- Update `src/Integration/Laravel/DataSources/ResourceFormDataSource.php` so edit-state hydration is value-safe:
  - scalar fields normalize to strings or null
  - date values expose ISO strings
  - password fields do not hydrate existing values by default
  - file fields expose metadata only, never raw file contents
  - rich text exposes the configured persisted format
- Update `resources/js/components/flashboard/forms/renderers/resolveFormFieldRenderer.ts` and `FormFieldRendererMap.ts` so renderer selection remains explicit and development-mode unknown renderer errors continue to fail fast.
- Add package-owned wrappers under `resources/js/components/flashboard/forms/fields/` instead of wiring Nuxt UI components directly from generic renderer code.

### DateInput

Goal: first-class date-only CRUD fields with ISO date payloads and the same interaction quality as existing table date filters.

Where:
- `src/Core/Forms/Fields/DateInput.php`
- `src/Core/Forms/Normalization/FormSchemaNormalizer.php`
- `src/Integration/Laravel/DataSources/ResourceFormDataSource.php`
- `resources/js/components/flashboard/forms/fields/FBDateInput.vue`
- `resources/js/components/flashboard/forms/renderers/resolveFormFieldRenderer.ts`
- `tests/Feature/FormRendererPayloadTest.php`
- `tests/Feature/TypedSchemaNormalizationTest.php`
- `tests/Feature/ResourceFormRulesTest.php`

How:
- Store and submit date-only values as `YYYY-MM-DD`; leave timezone and datetime semantics to a future `DateTimeInput`.
- Reuse the pattern from `resources/js/components/flashboard/table/DatePickerFilter.vue`: `UInputDate` for segmented input, `UPopover` + `UCalendar` for picking, `@internationalized/date` for parsing and serialization.
- Add fluent PHP options such as `minDate()`, `maxDate()`, and `native(bool $condition = true)` only when backed by payload contract keys.
- Infer Laravel rules as `nullable|date_format:Y-m-d` or `required|date_format:Y-m-d`, and map min/max to `after_or_equal` / `before_or_equal` when provided.
- Keep payloads scalar; do not leak `CalendarDate` objects past the Vue wrapper.

### PasswordInput

Goal: a dedicated public API for password fields without logging, hydrating, or accidentally exposing secret values.

Where:
- `src/Core/Forms/Fields/PasswordInput.php`
- `src/Integration/Laravel/DataSources/ResourceFormDataSource.php`
- `src/Core/Forms/Normalization/FormSchemaNormalizer.php`
- `resources/js/components/flashboard/forms/fields/FBInput.vue` initially, or `FBPasswordInput.vue` if reveal/toggle UX becomes stable
- `tests/Feature/ResourceFormDataSourceTest.php`
- `tests/Feature/ResourceFormRulesTest.php`

How:
- Implement `PasswordInput` as a purpose-built class that sets `input_type=password` and defaults to a blank edit-state value.
- Preserve `TextInput::password()` for compatibility, but prefer `PasswordInput::make('password')` in docs and stubs.
- Add explicit helpers only for common validation intent, such as `minLength(int $length)`, `maxLength(int $length)`, and `confirmed(bool $condition = true)`, if they merge cleanly with explicit `rules()`.
- Never include current password hashes in form state; resources should use `mutateDataUsing()` or `mutateFormDataBeforeSave()` to hash and skip empty password submissions.
- Avoid a default reveal button until the UX contract is intentional. If added later, implement it in `FBPasswordInput.vue` with an accessible icon button and no value logging.

### FileUpload

Goal: secure file input support that works in reusable package contexts while leaving storage policy and business meaning to the host app.

Where:
- `src/Core/Forms/Fields/FileUpload.php`
- `src/Contracts/Forms/FieldRenderer.php`
- `src/Core/Forms/Normalization/FormSchemaNormalizer.php`
- `src/Integration/Laravel/Http/Controllers/ResourceFormController.php`
- `src/Integration/Laravel/Persistence/ResourceFormPersister.php`
- `src/Integration/Laravel/DataSources/ResourceFormDataSource.php`
- `resources/js/components/flashboard/forms/fields/FBFileUpload.vue`
- `resources/js/components/flashboard/FlashboardScreenContent.vue`
- `tests/Feature/ResourceFormRulesTest.php`
- `tests/Feature/ResourceFormDataSourceTest.php`

How:
- Use Nuxt UI `UFileUpload` through `FBFileUpload.vue`; support `accept`, `multiple`, `maxSize`, `maxFiles`, `preview`, and `disk`/`directory` metadata as explicit payload keys.
- Ensure Inertia submission switches to multipart when any file field has a `File` or `File[]` value; preserve current non-file form behavior for all other forms.
- Keep file persistence host-safe:
  - default to passing `UploadedFile` instances through validation and mutation hooks
  - provide opt-in package storage only when a field declares disk/directory behavior
  - persist file paths or structured metadata, not raw file objects
  - support replace, keep existing, and remove semantics on edit screens
- Infer validation rules from field configuration:
  - `file` or `array` + per-file rules for multiple uploads
  - `mimes` / `mimetypes` / `max` from fluent field options
  - explicit resource `rules()` remain able to override or extend inferred rules
- Do not log filenames, paths, temporary file names, file contents, or user-submitted metadata.

### RichText

Goal: rich text authoring that is powerful enough for admin content fields but explicit about format, sanitization, and persistence.

Where:
- `src/Core/Forms/Fields/RichText.php`
- `src/Contracts/Forms/FieldRenderer.php`
- `src/Core/Forms/Normalization/FormSchemaNormalizer.php`
- `src/Integration/Laravel/DataSources/ResourceFormDataSource.php`
- `resources/js/components/flashboard/forms/fields/FBRichText.vue`
- `resources/js/components/flashboard/forms/renderers/resolveFormFieldRenderer.ts`
- `tests/Feature/FormRendererPayloadTest.php`
- `tests/Feature/ResourceFormRulesTest.php`

How:
- Use Nuxt UI `UEditor` through `FBRichText.vue`; expose a small stable field API instead of forwarding arbitrary TipTap internals.
- Support explicit content formats:
  - `html` for current Laravel-friendly string persistence
  - `markdown` when a host app wants markdown storage
  - `json` only when the resource intentionally persists structured editor JSON
- Default to `html` or `markdown` for simpler CRUD storage; require an explicit opt-in for JSON payloads.
- Infer `nullable|string` / `required|string` for string formats and a dedicated array/json validation path for JSON format.
- Document that display-time sanitization remains the host application's responsibility unless Flashboard later introduces a sanitizer contract.
- Keep editor toolbar behavior conservative: headings, bold, italic, links, lists, and blockquote first; images/uploads require a separate media integration milestone.

### Minimal Logging Policy

- Use Laravel's host-configured logging only for exceptional server-side conditions that need operator visibility, such as rejected upload storage, malformed rich-text JSON, or database constraint failures.
- Do not add client-side logging for normal field input, validation errors, focus/blur, editor transactions, or upload progress.
- Never log password values, rich-text body content, uploaded file contents, filenames, temporary paths, or full request payloads.
- When logging is necessary, include only coarse operational context: resource class, field key, renderer, failure category, sanitized rule name, and exception class.
- Keep existing successful save/delete flows quiet; user-facing feedback should remain toast/session based rather than log based.

### Documentation And Compatibility

- Update `docs/forms.md` and `docs/resources.md` with field examples and payload expectations.
- Update `docs/upgrading.md` only if renderer enum additions or payload keys affect beta consumers.
- Review `flashboard-docs/server/mcp/` and `flashboard-docs/server/routes/raw/` whenever docs headings, examples, or field lists change.
- Update `src/Integration/Laravel/Console/MakeResourceCommand.php` so generated resources can choose `PasswordInput`, `DateInput`, and text-oriented fields without importing `FieldRenderer` directly.
- Keep legacy array definitions working via `type` and `renderer` fallback, but prefer typed fields in all new documentation.

### QA Gates

- PHP tests:
  - typed field `toArray()` payloads
  - renderer normalization and unknown renderer errors
  - inferred validation rules
  - default create/edit state
  - password non-hydration
  - file multiple/single rule inference
- Frontend checks:
  - `npm run check:types`
  - `npm run build`
  - browser smoke coverage for create and edit screens with date, file, rich text, and password fields
- Package checks:
  - `composer test`
  - `composer analyse`
  - host-app validation for multipart Inertia submission and Laravel `UploadedFile` validation
- Contract checks:
  - normalized payload keys remain deterministic
  - existing `TextInput`, `Textarea`, `NumberInput`, `Select`, `Checkbox`, and `Toggle` fixtures remain unchanged
  - docs MCP/raw markdown output stays aligned when public docs change

## Completed

| Milestone | Date |
|-----------|------|
| 01. Product Concept & Positioning | 2026-03-20 |
| 02. Project Context & Architecture Baseline | 2026-03-20 |
| 03. Package Foundation & Boot Process | 2026-03-20 |
| 04. Public Contracts & Builder API | 2026-03-20 |
| 05. Resource Registry & Runtime Kernel | 2026-03-20 |
| 06. Routing, Auth Integration & Panel Access | 2026-03-20 |
| 07. Layout Shell, Navigation & Panel Chrome | 2026-03-20 |
| 08. Table Engine & List Resource Screens | 2026-03-20 |
| 09. Form Engine & Create/Edit Workflows | 2026-03-20 |
| 10. Detail Views, Infolists & Action Framework | 2026-03-20 |
| 11. Relations, Nested Contexts & Resource Composition | 2026-03-20 |
| 12. Custom Pages & Operator Workspaces | 2026-03-20 |
| 13. Backend-Driven UI Contract & Rendering Layer | 2026-03-20 |
| 14. Authorization Model & Visibility Rules | 2026-03-20 |
| 15. Theming, UX Consistency & Interaction Patterns | 2026-03-20 |
| 16. Extension Surface & Escape Hatches | 2026-03-20 |
| 17. Developer Tooling & Local DX | 2026-03-20 |
| 18. Test Strategy & Quality Gates | 2026-03-20 |
| 19. Documentation, Examples & Adoption Guides | 2026-03-20 |
| 20. MVP Validation In Real Host App | 2026-03-20 |
| 21. Beta Readiness & Compatibility Hardening | 2026-03-20 |
| 22. Public Beta Release | 2026-03-20 |
| 23. Advanced Form Field Contract Expansion | 2026-05-28 |
| 24. DateInput & PasswordInput Runtime | 2026-05-28 |
| 25. FileUpload Runtime & Persistence Boundary | 2026-05-28 |
| 26. RichText Runtime & Content Safety | 2026-05-28 |
| 27. Advanced Form Field Documentation & Examples | 2026-05-28 |
| 28. Advanced Form Field QA & Compatibility Hardening | 2026-05-28 |
