# Resource metrics (beta)

Flashboard offers **read-only, opt-in analytics** for existing Eloquent Resources.
No tracking service, event table, scheduled collector, or analytics database is required.

## Supported metrics

- `Metric::count()` — count records returned by the Resource query.
- `Metric::trend()` — daily record counts, including zero-filled days for the chosen period.
- `Metric::breakdown()` — top 10 groups by a configured database column.

The runtime currently supports 7/30/90-day **UTC** trend windows. The `metric_period`
query parameter is allowlisted and defaults to 30. Counts and breakdowns are all-time
aggregates over the Resource query (only trends change when the period changes).

## Example

```php
<?php

namespace App\Flashboard;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Pepperfm\Flashboard\Contracts\Resources\Resource;
use Pepperfm\Flashboard\Core\Metrics\Metric;

final class UsersResource extends Resource
{
    public static function model(): string
    {
        return User::class;
    }

    public static function query(): Builder
    {
        // Apply tenant/account scoping here, or in queryExtensions().
        return User::query();
    }

    public static function metrics(): array
    {
        return [
            Metric::count('total')->label('All users'),
            Metric::trend('registrations')->label('Daily registrations')->dateColumn('created_at')->chart('area')->span(8),
            Metric::breakdown('by_status')->label('By status')->column('status')->chart('bar'),
        ];
    }
}
```

The metrics appear on the Resource's index screen and are automatically included on
the main Dashboard if the current user has `viewAny` access to that Resource.

## Grid layout

Metrics are placed in a responsive **12-column grid** on large screens; on
smaller screens each card uses the available width. The default widths are
3 columns for count cards (four per row) and 6 columns for charts (two per row).

Override any metric with `->span($columns)` where `$columns` is an integer
from **1 to 12**. For example:

```php
Metric::trend('registrations')->chart('area')->span(8), // 2/3 of a row
Metric::trend('verified')->dateColumn('email_verified_at')->span(4), // 1/3
Metric::breakdown('statuses')->column('status')->chart('donut')->span(12), // full row
```

Count cards and charts occupy separate grids, preserving their existing visual
grouping. Width settings are applied on desktop only; metric definitions do not
support row placement, chart height, or per-breakpoint positioning.

### Output

Metrics are resolved server-side into a `metrics` payload:

```json
{
  "period_days": 30,
  "items": [
    { "key": "users.total", "resource": "Users", "kind": "count", "label": "All users", "chart": "line", "span": 3, "value": 42 },
    { "key": "users.registrations", "resource": "Users", "kind": "trend", "label": "Daily registrations", "chart": "area", "span": 8, "points": [{ "label": "2026-11-15", "value": 2 }] }
  ]
}
```

Flashboard renders this via Nuxt UI `UCard` and `UChart`; the project depends on
`@tanstack/charts` (an optional Nuxt UI peer) to enable charts.

## Security and performance

- **Opt in.** Nothing is published unless a Resource declares `metrics()`.
- **Resource authorization.** Access requires `canAccess()` + `viewAny`.
  Per-metric abilities can be declared using `metricAbilityMap()`, e.g.
  `['by_status' => 'viewAnalytics']`, using the Resource's registered Laravel Policy.
- **Field authorization.** Breakdown and trend columns respect `fieldAbilityMap()`.
- **Query scope.** Aggregations start from `Resource::query()` and go through
  `queryExtensions()`. Tenant / record scoping **must** be enforced in these queries.
  A `view()` check applied to individual records does NOT scope aggregate queries.
- **No dynamic SQL.** Column names are developer-defined, restricted to single
  safe identifiers, and checked against actual model columns. Request inputs cannot
  choose a column or SQL expression.
- **Bounded work.** Trend periods are 7/30/90 days; breakdowns return max 10 groups.
  Aggregates execute SQL on each screen load. Add appropriate indexes on time or
  grouping columns, and avoid very expensive Resource queries.
- **No sensitive events.** This feature does not record page views, clicks, payments,
  or user activity. Those require explicit data collection outside Flashboard.

## Deliberate non-goals

Custom SQL, query builders in the UI, warehouse connectors, ETL, arbitrary charts,
cross-database joins, scheduled snapshots, per-user funnel analysis, and events are
not part of Resource metrics.
