# Releases

## Release Track

- `0.x` — beta and pre-stable iterations
- `1.0` — first stable contract release

## Beta Release Process

1. Run package checks
2. Verify docs and changelog
3. Validate host-app example
4. Confirm schema version expectations
5. Publish tagged release

## Release Diagnostics

During release prep verify:

- provider-based panel config resolves before route/runtime boot
- `flashboard:make-provider` generates a working host provider
- inline `Flashboard::configure()` still works as a compatibility layer
- route registration still resolves
- JSON payload contains `schema_version`
- login flow and protected panel routes still behave correctly

## Reproducible verification

The tracked CI workflow runs the PHP suite against SQLite and a disposable PostgreSQL 17 database, PHPStan (including MCP), the frozen Bun lockfile, frontend tests, TypeScript checks and Vite build. It checks the bundled asset manifest graph before and after the build without pushing generated artifacts.

Locally, create a disposable database whose name begins with `flashboard_test_`, then run:

```bash
FLASHBOARD_PG_TEST_DB=flashboard_test_local composer test -- --fail-on-skipped
composer analyse -- --memory-limit=512M --no-progress
composer check-assets
bun install --frozen-lockfile
bun run dist:prepare
composer check-assets
```

PostgreSQL tests accept optional `FLASHBOARD_PG_TEST_HOST`, `FLASHBOARD_PG_TEST_PORT`, `FLASHBOARD_PG_TEST_USER`, and `FLASHBOARD_PG_TEST_PASSWORD` overrides. Defaults match a local Unix socket at /tmp, port 5432 and the current OS user. Never target a real application database. The test database must be created and removed explicitly.

Before tagging, verify the actual built asset files are included in Git and run the independent host installation, upgrade and browser checklist. CI is not a substitute for that host acceptance.
