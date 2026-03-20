# Project Rules

> Short, actionable rules and conventions for this project. Loaded automatically by $aif-implement.

## Rules

- Every PHP file must use `declare(strict_types=1);`, keep one public class per file, and declare explicit public return types.
- Prefer constants and enums over magic strings, magic numbers, and ad-hoc array keys in package code.
- Name interfaces with the `Contract` suffix.
- Follow the code style from [`$laravel-php-style`](/Users/dmitry/.agents/skills/laravel-php-style/SKILL.md): helpers over facades, `Arr::get()` for optional array access, inline FQCN for vendor types in signatures, and the documented import order.
- Prefer Laravel 13 attribute-first APIs for scopes, bindings, model metadata, and other framework features wherever attributes are supported.
- Use `\Illuminate\Database\Eloquent\Casts\Attribute::make()` for Eloquent attribute accessors and mutators instead of legacy getter and setter patterns.
- During Nuxt UI component development, call the Nuxt UI MCP on every component interaction or API decision instead of relying on memory or guessing component behavior.
