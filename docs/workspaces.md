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

## Workspace Payload

Custom pages can define:

- `workspaceKey()`
- `workspaceDescription()`
- `workspaceActions()`
- `workspace()`

These values are assembled by `WorkspacePayloadAssembler` and included in the screen payload.

## Recommended Use Cases

- moderation queue
- finance approvals
- support review board
- reconciliation console
