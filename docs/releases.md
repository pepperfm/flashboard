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
