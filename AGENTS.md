# AGENTS.md

> Project map for AI agents. Keep this file up-to-date as the project evolves.

## Project Overview
Flashboard is a concept-first PHP package for Laravel applications that aims to provide a backend-driven admin runtime for internal `/admin` panels.
It is currently in early implementation: the product direction is documented, package bootstrap wiring is in place, and the runtime features are being built from that foundation.

## Tech Stack
- **Language:** PHP 8.4
- **Framework:** Laravel package (Laravel 13 host support target)
- **Database:** Host application database
- **ORM:** Eloquent in the consuming application
- **Frontend:** Inertia + Vue + Nuxt UI + Vite

## Project Structure
```text
.
├── .ai-factory/                 # AI Factory project context artifacts
│   ├── ARCHITECTURE.md          # Architecture rules and intended module boundaries
│   └── DESCRIPTION.md           # Project specification derived from the concept
├── .ai-factory.json             # AI Factory skill and MCP metadata for local agents
├── .claude/skills/              # AI Factory skills mirrored for Claude-based agents
├── .codex/skills/               # AI Factory skills mirrored for Codex-based agents
├── .github/                     # Issue and PR templates for beta workflow
├── .idea/                       # Local IDE settings
├── .mcp.json                    # Project-level MCP configuration
├── ADMIN_PANEL_LIBRARY_CONCEPT.md # Product concept and source of truth for the package direction
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
├── resources/                   # Package views and frontend source assets, including package-owned form wrappers and layout shells
├── routes/                      # Package route definitions loaded by the service provider
├── src/                         # Package source code grouped by contracts, schema foundations, core runtime, UI, and Laravel integration
├── stubs/                       # Generator stubs for demo and future package scaffolds
├── tests/                       # Package tests using Orchestra Testbench
├── tsconfig.json                # TypeScript baseline for package UI assets
├── vite.config.ts               # Vite build configuration for package assets
└── vendor/                      # Composer-installed dependencies
```

## Key Entry Points
| File | Purpose |
|------|---------|
| `ADMIN_PANEL_LIBRARY_CONCEPT.md` | Product concept, DX goals, and package mental model |
| `composer.json` | Package name, type, PHP version, and namespace mapping |
| `package.json` | Frontend build entrypoint for the Inertia + Vue panel shell |
| `config/flashboard.php` | Default panel bootstrap configuration |
| `src/Integration/Laravel/FlashboardServiceProvider.php` | Laravel package entrypoint and publish registration |
| `src/Core/Registry/PanelRegistry.php` | Runtime registry for discovered panel providers |
| `src/Integration/Laravel/Discovery/PanelDiscovery.php` | Discovery layer that loads configured panel providers, resources, and pages |
| `src/Core/Runtime/Context/RuntimeContextFactory.php` | Builds request-scoped runtime context for panel screens |
| `src/Core/Runtime/Resolvers/ScreenResolver.php` | Resolves the current route into a page or resource screen |
| `src/Integration/Laravel/Routing/PanelRouteRegistrar.php` | Registers auth, page, and resource panel routes from runtime registries |
| `src/Integration/Laravel/DataSources/ResourceListDataSource.php` | Executes Eloquent-backed list resource queries for table screens |
| `src/Core/Resources/ResourceSurfaceResolver.php` | Resolves resource surface availability, accessible resource-owned pages, and shared surface metadata |
| `src/Contracts/Forms/FieldRenderer.php` | Public enum for stable form renderer hints in normalized payloads |
| `src/Support/Schema/SchemaNodeNormalizer.php` | Normalizes typed schema nodes and legacy array definitions into deterministic runtime payload input |
| `src/Integration/Laravel/Persistence/ResourceFormPersister.php` | Handles create and update persistence flow for resource forms |
| `src/Core/Relations/RelationPayloadFactory.php` | Builds relation payloads for detail and nested resource contexts |
| `src/Core/Runtime/Workspaces/WorkspacePayloadAssembler.php` | Assembles workspace payloads for custom operator pages |
| `src/UI/Renderers/InertiaScreenRenderer.php` | Renders panel screens through Inertia instead of Blade-first server markup |
| `src/UI/Renderers/JsonScreenRenderer.php` | Renders versioned runtime payloads for API-style consumers |
| `resources/js/Pages/Flashboard/Screen.vue` | Main Inertia Vue page rendered with Nuxt UI components |
| `resources/js/components/flashboard/forms/renderers/FormFieldRenderer.vue` | Central frontend renderer that maps normalized field payloads to Flashboard-owned wrapper components |
| `resources/js/components/flashboard/forms/layout/SimpleFormShell.vue` | Schema-first create/edit shell for simple CRUD forms rendered in one centered page card |
| `resources/js/components/flashboard/forms/layout/SectionedFormShell.vue` | Grouped create/edit shell for section-based resource forms |
| `resources/js/components/flashboard/forms/layout/TabbedFormShell.vue` | Grouped create/edit shell for tab-driven resource forms |
| `src/Integration/Laravel/Auth/PolicyBridge.php` | Bridges Laravel policies into package-level resource authorization |
| `docs/installation.md` | Primary installation and bootstrap guide |
| `docs/contracts.md` | Contract stability and compatibility policy |
| `examples/host-app/README.md` | Host-app validation walkthrough for package adoption |
| `tests/Feature/PanelRoutingTest.php` | Basic package routing smoke test using a lightweight container/router harness |
| `.mcp.json` | MCP servers available for repository work |
| `.ai-factory/DESCRIPTION.md` | AIF project description and scope |
| `.ai-factory/ARCHITECTURE.md` | AIF architecture guidelines and target module layout |

## Documentation
| Document | Path | Description |
|----------|------|-------------|
| README | `README.md` | Package landing page with live docs links |
| Admin Panel Library Concept | `ADMIN_PANEL_LIBRARY_CONCEPT.md` | Core product concept and intended package behavior |
| Installation Guide | `docs/installation.md` | Package install and bootstrap flow |
| Resources Guide | `docs/resources.md` | Resource DSL, discovery, and authoring patterns |
| Forms Guide | `docs/forms.md` | Schema-tree form authoring and layout rules |
| Tables Guide | `docs/tables.md` | List screen, column, filter, and pagination behavior |
| Workspaces Guide | `docs/workspaces.md` | Custom page and operator workflow patterns |
| Extensions Guide | `docs/extensions.md` | Query, payload, action, and runtime extension points |
| Contracts Guide | `docs/contracts.md` | Beta contract stability and payload versioning notes |
| Upgrading Guide | `docs/upgrading.md` | Migration notes for config and DSL changes |
| Releases Guide | `docs/releases.md` | Release flow and diagnostics |
| Beta Checklist | `docs/beta-checklist.md` | Beta readiness checklist for maintainers |

Documentation companion project:
- `../flashboard-docs/` — separate Nuxt UI docs site and MCP surface for `flashboard.pepperfm.com`
- Whenever docs pages, headings, navigation structure, or example formats change, review `../flashboard-docs/server/mcp/` and `../flashboard-docs/server/routes/raw/` for MCP/output drift

## AI Context Files
| File | Purpose |
|------|---------|
| `AGENTS.md` | This file - project structure map |
| `.ai-factory/DESCRIPTION.md` | Project specification and tech stack |
| `.ai-factory/ARCHITECTURE.md` | Architecture decisions and guidelines |
| `.mcp.json` | Project-level MCP server configuration |

## Agent Rules
- Never combine shell commands with `&&`, `||`, or `;` - execute each command as a separate tool call even if another document shows them combined.
- Treat `ADMIN_PANEL_LIBRARY_CONCEPT.md` as the primary product-direction artifact until implementation files exist.
- Keep package code framework-aligned for Laravel, but do not leak host application business rules into the reusable library.
- Prefer contract-first changes: public interfaces and payload shapes should be explicit before UI or adapter details.
- Treat the docs MCP surface as part of the documentation contract: when docs change, verify whether `flashboard-docs/server/mcp/` tools or raw markdown routes must change too.
