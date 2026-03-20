# Flashboard Playground

Use this playground guide when validating package slices locally.

## Flow

1. Install the package in a Laravel host app.
2. Run `php artisan flashboard:make-demo-resource`.
3. Add the generated class to `config/flashboard.php` under `discovery.resources`.
4. Visit `/admin/login`, sign in, then open the generated resource routes.

## Suggested Checks

- `/admin`
- `/admin/resources/<resource-key>`
- `/admin/resources/<resource-key>/create`
- `/admin/resources/<resource-key>/{record}`
- `/admin/resources/<resource-key>/{record}/edit`

## Notes

- The package currently ships runtime scaffolding, not a polished production UI.
- Use the JSON responses on panel routes to inspect payload evolution while building features.
