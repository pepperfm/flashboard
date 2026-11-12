# Workspaces

Workspaces cover non-CRUD operator flows like queues, review consoles, or processing pages.

## Custom Page Base

Extend `Pepperfm\Flashboard\Core\Pages\CustomPage`.

## Example

```php
final class ReviewQueuePage extends \Pepperfm\Flashboard\Core\Pages\CustomPage
{
    public static function title(): string
    {
        return 'Review Queue';
    }

    public static function type(): \Pepperfm\Flashboard\Contracts\Pages\PageType
    {
        return \Pepperfm\Flashboard\Contracts\Pages\PageType::Custom;
    }

    public static function uri(): string
    {
        return 'queues/review';
    }
}
```

## Workspace-Only Pages

When `component()` is not configured, the page is rendered by the built-in workspace fallback.
Use this for simple operator landing pages, lightweight dashboards, or flows that only need title, description, actions, and payload data.

## Workspace Payload

Custom pages can define:

- `workspaceKey()`
- `workspaceDescription()`
- `workspaceActions()`
- `workspace()`

These values are assembled by `WorkspacePayloadAssembler` and included in the screen payload.

## Component-Backed Pages

For Nova-like custom screens, a page can render a host-owned Vue component inside the normal Flashboard screen shell.
Extend `CustomPage` and return a browser registry name from `component()`.

```php
final class ReviewQueuePage extends \Pepperfm\Flashboard\Core\Pages\CustomPage
{
    public static function title(): string
    {
        return 'Review Queue';
    }

    public static function type(): \Pepperfm\Flashboard\Contracts\Pages\PageType
    {
        return \Pepperfm\Flashboard\Contracts\Pages\PageType::Custom;
    }

    public static function uri(): string
    {
        return 'queues/review/{record}';
    }

    public static function component(): ?string
    {
        return 'review-queue';
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public static function componentProps(array $context = []): array
    {
        return [
            'initialFilters' => $context['query'] ?? [],
            'record' => $context['route_parameters']['record'] ?? null,
        ];
    }

    /** @return array{scripts: list<array{url: string, type: string}>, styles: list<array{url: string}>} */
    public static function componentAssets(): array
    {
        return [
            'scripts' => [
                [
                    'url' => app(\Illuminate\Foundation\Vite::class)->asset('resources/js/flashboard/pages.ts'),
                    'type' => 'module',
                ],
            ],
            'styles' => [],
        ];
    }
}
```

Register the Vue component from a host asset entry that is loaded by `componentAssets()`:

```ts
import ReviewQueuePage from './pages/ReviewQueuePage.vue'

window.Flashboard?.registerPageComponent?.('review-queue', ReviewQueuePage)
```

`componentAssets()` accepts URL strings or descriptor arrays.
Scripts are loaded in order and default to `type=module`; pass `module => false` or `type => 'text/javascript'` for a classic script.
Styles are inserted before scripts.

Flashboard does not compile host page components in the package build.
Add the host entry, such as `resources/js/flashboard/pages.ts`, to the host application's Vite inputs.
When the entry is in the host Vite manifest, `app(\Illuminate\Foundation\Vite::class)->asset('resources/js/flashboard/pages.ts')` returns the correct development or hashed production URL.

Keep `componentProps()` small and safe.
The context includes sanitized `metadata`, `query`, `route_parameters`, `page_class`, `page_key`, and `user_id`; it does not include headers, cookies, session contents, or request bodies.

## Recommended Use Cases

- moderation queue
- finance approvals
- support review board
- reconciliation console
