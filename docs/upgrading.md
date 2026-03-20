# Upgrading

## Before Upgrading

- read `CHANGELOG.md`
- compare `docs/contracts.md`
- confirm whether `schema_version` changed

## Upgrade Checklist

1. Republish config if release notes require it
2. Review resource/page registration changes
3. Re-run host-app validation from `examples/host-app/README.md`
4. Verify custom extensions against new contracts
5. Re-test protected panel routes and JSON payload consumers

## Breaking-Change Classes

- contract rename
- route naming change
- payload shape change
- policy mapping behavior change
- builder API signature change
