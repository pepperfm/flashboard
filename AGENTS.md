# AGENTS.md

> Project map for AI agents. Keep this file up-to-date as the project evolves.

## Project Overview
Flashboard is a backend-driven admin runtime package for Laravel applications. Its typed Resource DSL, Eloquent tables, forms, detail screens, relations, actions and Inertia/Vue UI already exist, together with a guarded local AI Harness.
The project remains a **demo/alpha-MVP**. Work from the current implementation rather than assuming undocumented production-ready behavior.

## Tech Stack
- **Language:** PHP 8.5+
- **Framework:** Laravel 13 host application; Composer library
- **Database:** Host application database
- **ORM:** Eloquent in the consuming application
- **Frontend:** Inertia 3 + Vue 3 + Nuxt UI 4 + Vite; Bun for frontend development
- **AI Harness:** Laravel MCP 1.2+; local stdio server is disabled by default (`FLASHBOARD_AI_MCP=true` enables it)

## Project Structure
```text
.
├── .ai-factory/                 # AI Factory project context artifacts
│   ├── ARCHITECTURE.md          # Architecture rules and intended module boundaries
│   └── DESCRIPTION.md           # Project specification derived from the concept
├── CHANGELOG.md                 # Release notes and beta change history
├── README.md                    # Package entry documentation and local bootstrap notes
├── config/                      # Publishable package configuration
├── composer.json                # Package metadata and PSR-4 autoload configuration
├── docs/                        # End-user and maintainer documentation
├── examples/                    # Demo resources, pages, and host-app validation artifacts
├── package.json                 # Frontend asset toolchain scaffold for future admin UI assets
├── phpstan.neon.dist            # Static analysis configuration
├── phpunit.xml                  # Testbench test suite entrypoint
├── playground/                  # Manual validation notes for local package development
├── resources/                   # Package views and frontend source assets, including package-owned table/form wrappers and layout shells
├── routes/                      # Package route definitions loaded by the service provider
├── src/Ai/                      # Eloquent schema inspector, Resource compiler, local MCP server/tools
├── src/                         # Package contracts, runtime, UI and Laravel integration
├── stubs/                       # Generator stubs for demo and future package scaffolds
├── tests/                       # Package tests using Orchestra Testbench
├── tsconfig.json                # TypeScript baseline for package UI assets
├── vite.config.ts               # Vite build configuration for package assets
└── vendor/                      # Composer-installed dependencies
```

## Key Entry Points
| File | Purpose |
|------|---------|
| `composer.json` | Package name, type, PHP version, and namespace mapping |
| `package.json` | Frontend build entrypoint for the Inertia + Vue panel shell |
| `config/flashboard.php` | Panel bootstrap configuration and `ai.mcp_enabled` flag |
| `src/Ai/ModelCatalog.php` | Discovers Eloquent models and inspects DB columns / typed relations |
| `src/Ai/ResourceManager.php` | Validates resource specs, previews diffs, creates/updates managed PHP files |
| `src/Ai/Mcp/FlashboardMcpServer.php` | Registers six trusted local MCP tools |
| `docs/ai-harness.md` | User-facing MCP installation, prompts, write safety and troubleshooting |
| `src/Integration/Laravel/FlashboardServiceProvider.php` | Laravel package entrypoint and publish registration |
| `src/Core/Registry/PanelRegistry.php` | Runtime registry for discovered panel providers |
| `src/Integration/Laravel/Discovery/PanelDiscovery.php` | Discovery layer that loads configured panel providers, resources, and pages |
| `src/Core/Runtime/Context/RuntimeContextFactory.php` | Builds request-scoped runtime context for panel screens |
| `src/Core/Runtime/Resolvers/ScreenResolver.php` | Resolves the current route into a page or resource screen |
| `src/Integration/Laravel/Routing/PanelRouteRegistrar.php` | Registers auth, page, and resource panel routes from runtime registries |
| `src/Integration/Laravel/DataSources/ResourceListDataSource.php` | Executes Eloquent-backed list resource queries for table screens |
| `src/Integration/Laravel/DataSources/ResourceFilterOptionsDataSource.php` | Serves lazy backend-loaded select filter options with search, pagination, and selected-value hydration |
| `src/Integration/Laravel/Http/Controllers/ResourceFilterOptionsController.php` | Protected JSON endpoint for resource table filter option requests |
| `src/Core/Resources/ResourceSurfaceResolver.php` | Resolves resource surface availability, accessible resource-owned pages, and shared surface metadata |
| `src/Contracts/Tables/Filters/SelectFilterOptionsQuery.php` | Public resolver context for custom lazy select filter option loaders |
| `src/Contracts/Tables/Filters/SelectFilterOptionsResult.php` | Public result DTO for lazy select filter option responses |
| `src/Contracts/Forms/FieldRenderer.php` | Public enum for stable form renderer hints in normalized payloads |
| `src/Support/Schema/SchemaNodeNormalizer.php` | Normalizes typed schema nodes and legacy array definitions into deterministic runtime payload input |
| `src/Integration/Laravel/Persistence/ResourceFormPersister.php` | Handles create and update persistence flow for resource forms |
| `src/Core/Relations/RelationPayloadFactory.php` | Builds relation payloads for detail and nested resource contexts |
| `src/Core/Runtime/Workspaces/WorkspacePayloadAssembler.php` | Assembles workspace payloads for custom operator pages |
| `src/UI/Renderers/InertiaScreenRenderer.php` | Renders panel screens through Inertia instead of Blade-first server markup |
| `src/UI/Renderers/JsonScreenRenderer.php` | Renders versioned runtime payloads for API-style consumers |
| `resources/js/Pages/Flashboard/Screen.vue` | Main Inertia Vue page rendered with Nuxt UI components |
| `resources/js/components/flashboard/table/LazySelectFilter.vue` | Lazy backend-loaded Nuxt UI select menu for table filters |
| `resources/js/components/flashboard/forms/renderers/FormFieldRenderer.vue` | Central frontend renderer that maps normalized field payloads to Flashboard-owned wrapper components |
| `resources/js/components/flashboard/forms/layout/SimpleFormShell.vue` | Schema-first create/edit shell for simple CRUD forms rendered in one centered page card |
| `resources/js/components/flashboard/forms/layout/SectionedFormShell.vue` | Grouped create/edit shell for section-based resource forms |
| `resources/js/components/flashboard/forms/layout/TabbedFormShell.vue` | Grouped create/edit shell for tab-driven resource forms |
| `src/Integration/Laravel/Auth/PolicyBridge.php` | Bridges Laravel policies into package-level resource authorization |
| `docs/installation.md` | Primary installation and bootstrap guide |
| `docs/contracts.md` | Contract stability and compatibility policy |
| `examples/host-app/README.md` | Host-app validation walkthrough for package adoption |
| `tests/Feature/PanelRoutingTest.php` | Basic package routing smoke test using a lightweight container/router harness |
| `.ai-factory/DESCRIPTION.md` | AIF project description and scope |
| `.ai-factory/ARCHITECTURE.md` | AIF architecture guidelines and target module layout |

## Documentation
| Document | Path | Description |
|----------|------|-------------|
| README | `README.md` | Package landing page with live docs links |
| Installation Guide | `docs/installation.md` | Package install and bootstrap flow |
| AI Harness Guide | `docs/ai-harness.md` | Agent tool workflow, spec format, example prompts and safety limits |
| Resources Guide | `docs/resources.md` | Resource DSL, discovery, and authoring patterns |
| Forms Guide | `docs/forms.md` | Schema-tree form authoring and layout rules |
| Tables Guide | `docs/tables.md` | List screen, column, filter, and pagination behavior |
| Workspaces Guide | `docs/workspaces.md` | Custom page and operator workflow patterns |
| Extensions Guide | `docs/extensions.md` | Query, payload, action, and runtime extension points |
| Contracts Guide | `docs/contracts.md` | Beta contract stability and payload versioning notes |
| Upgrading Guide | `docs/upgrading.md` | Migration notes for config and DSL changes |
| Releases Guide | `docs/releases.md` | Release flow and diagnostics |
| Beta Checklist | `docs/beta-checklist.md` | Beta readiness checklist for maintainers |

Documentation companion:
- `flashboard.pepperfm.com` is the public docs site; its source is **not present in this checkout**.
- If a separate `flashboard-docs` checkout is available and in scope, review its `server/mcp/` and raw Markdown routes for drift. Do not claim they were updated without actually inspecting that repository.

## AI Context Files
| File | Purpose |
|------|---------|
| `AGENTS.md` | This file - project structure map |
| `.ai-factory/DESCRIPTION.md` | Project specification and tech stack |
| `.ai-factory/ARCHITECTURE.md` | Architecture decisions and guidelines |

## Agent Rules
- Never combine shell commands with `&&`, `||`, or `;` - execute each command as a separate tool call even if another document shows them combined.
- Treat the current typed Resource implementation and `docs/` as the source of truth; do not prioritize stale concepts over working behavior.
- Keep package code framework-aligned for Laravel, but do not leak host application business rules into the reusable library.
- Prefer contract-first changes: public interfaces and payload shapes should be explicit before UI or adapter details.
- For docs changes, review a separately available `flashboard-docs` project when in scope; otherwise explicitly note that the external docs MCP surface was not checked.

## AI Harness Agent Instructions

Read [docs/ai-harness.md](docs/ai-harness.md) before operating Flashboard MCP tools in a host Laravel application.

### Supported MCP contract

- This is a **local stdio** MCP server enabled by `FLASHBOARD_AI_MCP=true` in the **host app**. The current Flashboard `composer.json` already requires `laravel/mcp:^1.2`; do not tell users to install it again by default.
- There are nine tools: `list_models`, `inspect_model`, `list_resources`, `preview_resource`, `create_resource`, `update_resource`, `get_resource_spec`, `add_field` and `add_relation`. `list_models`, `inspect_model`, `list_resources`, `preview_resource`, and `get_resource_spec` are read-only. `create_resource` / `update_resource` write PHP; `add_field` / `add_relation` only write with `apply=true` and both hashes. None reads or mutates model records through MCP.
- Model discovery is currently limited to concrete autoloadable `App\Models` Eloquent classes. Model inspection reads table metadata and declared typed relation methods, not business rows.
- The generator creates Resources only under the host app's `app/Flashboard/Ai`; normal provider discovery must be configured. It accepts existing database columns, scalar fields (`text`, `textarea`, `number`, `date`, `select`, `checkbox`, `toggle`) and existing typed Eloquent relations (`belongs_to`, `belongs_to_many`, `has_one`, `has_many`) with registered related Resources. Select options are static.
- Flashboard's **PHP Resource DSL** supports more than this generator. Relations can now be generated from existing Eloquent methods; MCP does not create database relationships, migrations or new Eloquent methods. Do not claim support for REST APIs, charts, uploads, complex layouts or arbitrary PHP generation through MCP.

### Safe agent workflow

1. **Inspect first.** Call `list_models`, `inspect_model` and `list_resources` as relevant. Validate real column names, field types, hidden/fillable flags, existing Resource keys and whether the app needs a policy or scoped query. Do not invent schema, statuses or relation names.
2. **Preview before writing.** Call `preview_resource` with a complete specification and show the proposed file, relevant diff, limitations and any `writable=false` reason. When the user requested research or a proposal only, **stop here**.
3. **Require actual write intent.** Call `create_resource`, `update_resource`, or `add_field` / `add_relation` with `apply=true` only when the user has authorized creation/update. Possessing a hash is **not** user approval.
4. **Preserve exact preview state.** Reuse `proposal_hash` with the same specification; updates also require `expected_sha`. A changed spec, stale hash, route-key conflict, fingerprint mismatch or unwritable target requires re-inspection and a new preview. Never forge or remove fingerprint markers to overwrite handwritten code.
5. **Prefer no unnecessary changes.** Default `allow_delete=false`; use only fields the user needs. For features outside generator capabilities, explain the limitation and propose normal PHP Resource editing **as a separate coding task**.
6. **Verify outcomes.** After a write, check generated file/class, autoload/discovery, access Gate, applicable policies and focused runtime behavior. Never report tests, UI smoke tests or docs-site updates as performed when they were not.

### Security boundaries

- `accessFlashboard` Gate, registered model policies, model fillability and tenant-scoped `Resource::query()` belong to the host app. Generated Resources do not create authorization or multitenant isolation by themselves.
- Do not run migrations, `migrate:fresh`, seeders, destructive CRUD, or inspect user data just to scaffold a Resource. Request explicit authorization for any such work.
- Never copy credentials/secrets into prompts, tool outputs or generated PHP.
- The MCP process runs with the host application's environment privileges even though no HTTP MCP endpoint is exposed. Recommend a trusted local agent and development database for experiments.
- This is still an alpha/MVP: keep changes small, avoid speculative architecture, and distinguish confirmed functionality from assumptions.

### Incremental AI Harness operations

- For existing AI-managed files, use `get_resource_spec` to inspect the fingerprint-verified manifest.
- Preview `add_relation` or `add_field` without `apply`, review the diff, and only apply with `apply=true` plus matching `expected_sha` / `proposal_hash` after user approval.
- The related model must have a registered Resource and the method must declare an Eloquent relation return type. `belongs_to` / `belongs_to_many` generate form fields; `has_one` / `has_many` generate inverse managers with read-only default.
- Old AI files without `@flashboard-ai-spec` cannot be incrementally changed; use complete-spec preview/update to upgrade unchanged files, never rewrite fingerprints.
- Relation generation never creates tables, pivots, foreign keys, migrations, model methods, policies or access scopes.
