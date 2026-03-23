# Architecture: Modular Monolith

## Overview
Flashboard should start as a modular monolith packaged as a single Composer library with strong internal boundaries.
This fits the project because the package needs one installable distribution, but it also needs clear separation between stable public contracts, admin runtime behavior, Inertia/Vue/Nuxt UI payload generation, and Laravel-specific integration points.

The concept already points toward layered internals: `Admin Core`, `Admin UI`, and `Application Layer`.
Using a modular monolith lets us preserve that layering without introducing deployment or operational complexity that would be inappropriate for a reusable package.

## Decision Rationale
- **Project type:** Reusable Laravel admin panel package
- **Tech stack:** PHP 8.4, Composer library, Laravel host integration
- **Key factor:** The product needs one package with strict module boundaries, stable public contracts, and extensibility for both CRUD resources and custom operator workflows

## Folder Structure
```text
.
├── config/
│   └── flashboard.php           # Publishable package configuration and panel defaults
├── resources/
│   ├── css/                     # Package-owned styling primitives and tokens
│   ├── js/                      # Frontend bootstrap entries plus package-owned form wrappers, renderer maps, and layout shells
│   └── views/                   # Inertia root view and auth blade views
├── routes/
│   └── flashboard.php           # Package route definitions mounted by the service provider
├── src/
│   ├── Contracts/              # Stable public contracts and builder interfaces
│   │   ├── Actions/
│   │   ├── Forms/
│   │   ├── Pages/
│   │   ├── Schema/
│   │   ├── Resources/
│   │   └── Tables/
│   ├── Core/                   # Package runtime and business-neutral admin orchestration
│   │   ├── Actions/
│   │   ├── Authorization/
│   │   ├── Detail/
│   │   ├── Extensions/
│   │   ├── Forms/
│   │   ├── Hooks/
│   │   ├── Navigation/
│   │   ├── Panel/
│   │   ├── Pages/
│   │   ├── Relations/
│   │   ├── Registry/
│   │   ├── Resources/
│   │   ├── Runtime/
│   │   ├── Routing/
│   │   └── Tables/
│   ├── UI/                     # Normalized view payloads and rendering-oriented structures
│   │   ├── Contracts/
│   │   ├── Detail/
│   │   ├── Forms/
│   │   ├── Layout/
│   │   ├── Notifications/
│   │   ├── Overlays/
│   │   ├── Pages/
│   │   │   └── Workspaces/
│   │   ├── Renderers/
│   │   ├── States/
│   │   └── Tables/
│   ├── Integration/            # Laravel adapters, service provider, middleware, HTTP bridge
│   │   └── Laravel/
│   │       ├── Auth/
│   │       ├── DataSources/
│   │       ├── Discovery/
│   │       ├── Http/
│   │       ├── Persistence/
│   │       └── Routing/
│   └── Support/                # Shared utility code with no framework or product leakage
│       └── Schema/
├── package.json                # Frontend tooling entrypoint for package assets
├── phpstan.neon.dist           # Static analysis baseline for package code
├── phpunit.xml                 # Package test suite definition
├── playground/                 # Local manual validation guidance
├── examples/                   # Demo resource and workspace examples
├── stubs/                      # Generator stubs for demo resources and future scaffolds
├── tests/                      # Testbench-based feature and package tests
├── tsconfig.json               # TypeScript rules for package frontend sources
├── vite.config.ts              # Asset build pipeline for package-distributed UI files
├── .ai-factory/
│   ├── DESCRIPTION.md
│   └── ARCHITECTURE.md
├── ADMIN_PANEL_LIBRARY_CONCEPT.md
└── composer.json
```

## Dependency Rules
- `Contracts` may be referenced by every other package module.
- `Core` may depend on `Contracts` and `Support`, but not on Laravel HTTP adapters or UI implementation details.
- `UI` may depend on `Contracts` and `Core` output models, but must not own domain or routing decisions.
- `Integration/Laravel` may depend on `Contracts`, `Core`, `UI`, and Laravel framework APIs.
- `Support/Schema` may provide framework-light schema utilities shared by `Core`, but must not depend on Laravel HTTP or panel runtime adapters.
- Host applications should plug in model classes, policies, queries, and workflow logic through contracts and configuration, not through direct edits of package internals.

- ✅ `Integration/Laravel -> Core -> Contracts`
- ✅ `UI -> Contracts`
- ✅ `Core -> Contracts`
- ❌ `Contracts -> Core`
- ❌ `Core -> Integration/Laravel`
- ❌ `Core -> host-application business logic`
- ❌ `UI -> direct Eloquent querying`

## Layer/Module Communication
- Host applications register panels, resources, pages, and policies through package-facing contracts.
- `Core` turns resource definitions into normalized runtime state and payload-ready structures, including schema-node normalization and resource-surface resolution.
- `UI` consumes normalized contracts and payload objects to render consistent admin screens through Inertia + Vue + Nuxt UI.
- `Integration/Laravel` adapts framework routing, middleware, auth, requests, and responses to the package runtime.
- Extension points should use interfaces, explicit hooks, and configuration objects instead of deep inheritance chains.
- The separate docs site (`flashboard-docs`) is part of the external developer surface; changes to docs IA, headings, or example formats may require coordinated updates to its MCP tools and raw markdown endpoints so AI consumers receive consistent structured output.

## Key Principles
1. Keep the public API surface declarative and split by concern: `table()`, `form()`, `detail()`, `actions()`, `pages()`.
2. Keep typed schema nodes and schema normalization package-owned, with legacy arrays treated as a compatibility input rather than the canonical runtime contract.
3. Prefer `$form->schema([...])` as the canonical form root; treat `Section`, `Tabs`, and `Tab` as schema nodes, while `sections()` and `tabs()` remain compatibility helpers only.
4. Design contracts for the 80 percent path, then provide escape hatches for the remaining 20 percent.
5. Keep admin runtime logic package-owned and business logic host-owned.
6. Make payloads backend-driven and deterministic so the UI layer stays consistent across resources.
7. Prefer explicit contracts and composition over magic configuration and overloaded DSLs.

## Code Examples

### Resource Definition With Dedicated Surfaces
```php
<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Demo;

use App\Models\User;
use Pepperfm\Flashboard\Core\Forms\Fields\Select;
use Pepperfm\Flashboard\Core\Forms\Fields\TextInput;
use Pepperfm\Flashboard\Core\Forms\Fields\Toggle;
use Pepperfm\Flashboard\Core\Forms\Layout\Section;
use Pepperfm\Flashboard\Core\Tables\Columns\BadgeColumn;
use Pepperfm\Flashboard\Core\Tables\Columns\TextColumn;
use Pepperfm\Flashboard\Core\Tables\Filters\SelectFilter;
use Pepperfm\Flashboard\Contracts\Forms\FormContract;
use Pepperfm\Flashboard\Contracts\Resources\Resource;
use Pepperfm\Flashboard\Contracts\Tables\TableContract;

final class UserResource extends Resource
{
    public static function model(): string
    {
        return User::class;
    }

    public static function table(TableContract $table): TableContract
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('ID')->sortable(),
                TextColumn::make('email')->label('Email')->searchable(),
                BadgeColumn::make('status')->label('Status'),
            ])
            ->filters([
                SelectFilter::make('status')->label('Status'),
            ]);
    }

    public static function form(FormContract $form): FormContract
    {
        return $form
            ->schema([
                TextInput::make('email')->label('Email')->email()->required(),
                Select::make('status')->label('Status'),
                Toggle::make('is_active')->label('Is active'),
            ]);
    }
}
```

### Registry Depends On Contracts, Not Adapters
```php
<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Core\Registry;

use Pepperfm\Flashboard\Contracts\Resources\Resource;

final class ResourceRegistry
{
    /** @var array<class-string<Resource>> */
    private array $resources = [];

    public function register(string $resourceClass): void
    {
        $this->resources[] = $resourceClass;
    }

    /** @return array<class-string<Resource>> */
    public function all(): array
    {
        return $this->resources;
    }
}
```

## Anti-Patterns
- ❌ Building the package around one giant universal field DSL that mixes table, form, detail, and page concerns.
- ❌ Letting Laravel HTTP or frontend rendering details leak into public contracts.
- ❌ Encoding host-application business workflows directly inside reusable package modules.
- ❌ Querying models directly from UI components instead of going through normalized runtime contracts.
- ❌ Solving every advanced use case with more magic instead of explicit extension points.
