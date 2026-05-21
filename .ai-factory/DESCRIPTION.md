# Project: Flashboard

## Overview
Flashboard is a PHP 8.4 package for Laravel 13 applications that provides a backend-driven admin runtime for internal panels.
The package is intended to turn declarative resource definitions into a ready-to-use `/admin` experience with consistent UX, predictable contracts, and strong escape hatches for custom workflows.

## Current Project State
- Early-stage package skeleton with concept-first documentation
- Composer package metadata and PSR-4 autoloading are present
- Laravel package boot files, service provider, config, routes, and install command are now scaffolded
- Public contracts for panel, page, resource, table, form, detail, action, and navigation surfaces are now defined
- Fluent builder primitives, typed schema node foundations, and registry discovery scaffolding are in place for panels, resources, and pages
- Runtime metadata, screen resolution, lifecycle handling, and payload assembly scaffolding are now wired through the `/admin` bootstrap
- Package-level auth flow, route registration, layout shell, permission-aware navigation, and Eloquent-backed resource list data source are now scaffolded
- Form persistence, detail data hydration, relation payloads, action execution, and custom workspace page runtime are now scaffolded in the package core
- Versioned UI contracts, renderers, policy bridge, UX state payloads, extension hooks, playground tooling, and PHPUnit/PHPStan scaffolding are now present
- The panel shell now renders through Inertia + Vue with a Vite-powered frontend entry, Nuxt UI integration, and package-owned page components
- Resource configuration now has a typed schema-node layer with normalizers for table, form, and detail payloads, plus a package-owned resource surface resolver for unified surface metadata
- Product direction is defined in `ADMIN_PANEL_LIBRARY_CONCEPT.md`

## Core Features
- Declarative resource API with dedicated `table()`, `form()`, `detail()` or `infolist()`, `actions()`, and `pages()` surfaces
- Typed resource config nodes for columns, fields, sections, tabs, filters, scopes, and detail entries, with legacy array definitions still supported as a compatibility bridge
- Admin runtime covering auth shell, layout, navigation, tables, forms, detail pages, actions, notifications, and permissions
- Backend-driven payload contracts so the UI layer can render resources consistently
- Escape hatches for custom queries, save pipelines, actions, page components, relation screens, toolbars, and policies
- Support for both classic CRUD resources and custom operator workspaces such as queues, review screens, and processing flows

## Tech Stack
- **Language:** PHP 8.4
- **Framework:** Laravel package targeting Laravel 13 host applications
- **Database:** Host application database; the package should avoid owning a mandatory database schema by default
- **ORM:** Eloquent in the consuming Laravel application
- **Frontend/UI:** Backend-driven admin UI runtime rendered through Inertia + Vue with Nuxt UI, VueUse interaction helpers, and a Vite asset pipeline
- **Integrations:** Panel auth, permissions, routing, notifications, browser automation tooling via MCP
- **Dev Tooling:** PHPUnit, PHPStan, demo resource stub, and playground guidance

## Product Goals
- Ship a package that can be installed and expose a working `/admin` panel quickly
- Standardize repeated admin patterns instead of forcing every project to rebuild them
- Keep the public API predictable and declarative for common cases
- Preserve extensibility for complex business workflows and non-CRUD operator screens
- Decouple domain-specific business logic from the package runtime

## Architecture Notes
- This repository is a reusable library, not an end-user application
- The main product layers are `Admin Core`, `Admin UI`, and `Host Application Layer`
- The public API should favor dedicated declarative surfaces over a single overloaded field DSL
- Package internals should be backend-driven and contract-first
- The architecture should optimize for a stable 80/20 model: common admin work declarative, complex workflows customizable

## Non-Functional Requirements
- Logging should defer to the host Laravel application's configuration
- Public contracts should remain stable and versionable as the package matures
- Navigation, actions, and visibility rules must be permission-aware
- UX should stay consistent across tables, forms, detail screens, and workflow pages
- Package internals should avoid project-specific business logic and stay framework-aligned

## Architecture
See `.ai-factory/ARCHITECTURE.md` for detailed architecture guidelines.
Pattern: Modular Monolith with layered internals.
