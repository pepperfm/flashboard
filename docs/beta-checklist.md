# Beta Launch Checklist

## Product

- [ ] Core contracts documented
- [ ] Screen payload has a schema version
- [ ] Auth flow is documented
- [ ] Resource list/create/edit/detail routes are documented
- [ ] Workspace/custom page flow is documented

## Quality

- [ ] `php -l` passes
- [ ] PHPUnit smoke checks pass
- [ ] PHPStan baseline runs
- [ ] Host app validation notes reviewed

## Release

- [ ] `CHANGELOG.md` updated
- [ ] `docs/upgrading.md` updated
- [ ] `docs/contracts.md` updated
- [ ] beta feedback template present
- [ ] release tag plan prepared


## Runtime maintainability candidate (2026-11-26)

- [x] Screen and data-source responsibility extraction, public constructor contracts retained.
- [x] Local full PHP suite with SQLite and disposable PostgreSQL: 339 tests / 1804 assertions, zero skipped or failed; 14 existing deprecations and 4 notices.
- [x] PHPStan, Composer audit, asset manifest and source diff checks. Frontend 27 tests / 121 expectations, TypeScript, deterministic rebuild; docs site lint and typecheck.
- [x] Regression budgets for representative list, relation and metrics fixtures; authorization and scoped metadata isolation tests.
- [x] Independent Laravel 13.44.1 server-side smoke: install archive of tag 0.4.0; upgrade to current local candidate; fresh candidate install with separate Inertia root view. Composer mirrored copies, published manifest matched, referenced assets exist; provider registration, login root and guest redirect verified. PHP 8.5.13, Inertia Laravel 3.8.0, MCP 1.3.0.
- [ ] Final frontend race/retry evidence and manual production browser/theme/mobile/SSR checks (deferred by maintainer).
- [ ] Docs site build and deployed raw/MCP content verification.
- [ ] Green CI on final candidate commit and tag; not yet pushed.
- [ ] Final release tag/version authorization and actual consumer upgrade.
- [ ] Full stable-beta acceptance review. MySQL/MariaDB and a broader dependency version matrix are not verified; no 1.0 claim.
