# AI Harness (alpha)

Flashboard's AI Harness lets a coding agent inspect a **host Laravel application's Eloquent schema** and generate basic Flashboard Resources using nine **local MCP tools**. It generates ordinary PHP for the existing Flashboard UI; it is **not** a built-in AI chatbot, a REST service connector, or a way to query or mutate application records via MCP.

The current project is a demo/alpha-MVP. Test generated Resources in a host application before treating them as ready for use.

## 1. Setup in a host Laravel application

Prerequisites: **PHP 8.5+**, **Laravel 13**, an available database/schema, and Flashboard installed following [installation](installation.md). A panel provider must enable discovery with `$this->panelConfig()->discover()` (or discover the `app/Flashboard` resource directory). Opening the UI also requires the host's `accessFlashboard` Gate.

**Do not install `laravel/mcp` a second time.** On current `master`, `flashboard/composer.json` already includes `laravel/mcp:^1.2` in `require`. The dependency is installed with Flashboard; **running the server remains opt-in**.

In the **host app's** `.env`:

```dotenv
FLASHBOARD_AI_MCP=true
```

If configuration is cached:

```sh
php artisan config:clear
```

The MCP client starts the server in the **host Laravel app** using:

```sh
php artisan mcp:start flashboard
```

This is a long-running stdio process, not a command expected to print a success message and exit. Example Codex CLI registration (replace the absolute path with your actual **host app**, not the Flashboard package checkout):

```sh
codex mcp add flashboard -- php /absolute/path/to/host-app/artisan mcp:start flashboard
```

A generic MCP-client entry is:

```json
{
  "command": "php",
  "args": ["/absolute/path/to/host-app/artisan", "mcp:start", "flashboard"]
}
```

Use a PHP 8.5+ executable with the host application's database access. For a Sail/Docker host, launch `artisan` **inside the app's container environment**. Restart/reconnect the agent after configuring the server.

**Security:** Flashboard registers no MCP HTTP endpoint. However, the local process has the host application's environment permissions: use it with trusted agents and preferably a development database.

## 2. Available MCP tools

| Tool | Purpose | Changes files? |
| --- | --- | --- |
| `list_models` | Discover concrete Eloquent model classes under `App\Models` | No |
| `inspect_model` | Inspect table columns, types, primary key, `fillable` / `hidden` flags, and explicitly typed relation methods | No |
| `list_resources` | List registered Resource classes, route keys and backing models | No |
| `preview_resource` | Validate spec; return PHP source diff, `operation`, `writable`, `reason`, `proposal_hash` and `expected_sha` | No |
| `create_resource` | Create an AI-generated Resource with the exact `proposal_hash` | **Yes** |
| `update_resource` | Update an untouched, AI-generated Resource with matching `expected_sha` and `proposal_hash` | **Yes** |
| `get_resource_spec` | Read the embedded specification of an unchanged AI Resource | No |
| `add_field` | Preview or append a new scalar field with hashes and `apply=true` | Only when applied |
| `add_relation` | Preview or append a new Eloquent relation with hashes and `apply=true` | Only when applied |

These are **developer tools**. They do not read model rows, run custom SQL, migrate schemas or perform business-record CRUD through MCP.

## 3. First run: explore → preview → approve → generate → verify

Start with a read-only prompt:

> Inspect the Eloquent models in this Laravel app, list existing Flashboard Resources and suggest the smallest useful admin interface. Check real database columns and fillable/hidden status. Do not write files or query application records.

The agent should call `list_models`, `inspect_model` and `list_resources`, then explain what exists and what it proposes. Next ask for a specific preview:

> Preview an Orders Resource. Show ID, total and status in the table; make status a Select with a filter. Only use actual columns. Show the diff. Do not create anything yet.

The agent calls `preview_resource`, shows the proposed class/diff and identifies unsupported features. **A preview is not permission to write.** After you approve, say:

> I approve this Orders Resource. Create it exactly as previewed, leave deletion disabled and check that it appears in the discovered Resources.

Only now should the agent call `create_resource` using **the same full specification** and the returned `proposal_hash`. Later updates require **a fresh preview** and both `expected_sha` and `proposal_hash`. Check the generated PHP, discovery, authorization and actual UI behavior in the host application.

## 4. Example resource specification

This is an illustrative argument object for `preview_resource`. The model and columns **must first be confirmed with `inspect_model`**.

```json
{
  "resource": "Orders",
  "model": "App\\Models\\Order",
  "fields": [
    {
      "name": "id",
      "type": "number",
      "table": true,
      "form": false,
      "sortable": true
    },
    {
      "name": "status",
      "type": "select",
      "label": "Статус",
      "options": {
        "draft": "Черновик",
        "paid": "Оплачен",
        "cancelled": "Отменён"
      },
      "table": true,
      "form": true,
      "filter": true,
      "required": true
    },
    {
      "name": "total",
      "type": "number",
      "label": "Сумма",
      "table": true,
      "form": true,
      "sortable": true
    }
  ],
  "allow_delete": false
}
```

The same spec is sent to `create_resource` or `update_resource`, plus the hashes returned from preview. The generator creates `app/Flashboard/Ai/OrdersResource.php` in the **host** application.

| Field option | Behavior |
| --- | --- |
| `name` | Required existing database column; duplicates are refused |
| `type` | `text` (default), `textarea`, `number`, `date`, `select`, `checkbox`, `toggle` |
| `label` | Optional human-readable label |
| `table` / `detail` | Show in list/detail, both default to `true` |
| `form` | Default: editable/fillable non-PK and non-timestamp fields; explicit `true` requires fillability |
| `searchable` / `sortable` | Optional column behavior, default `false` |
| `filter` | Simple SelectFilter for select, InputFilter otherwise; default `false` |
| `required` | Form requirement; default `false` |
| `options` | Required static value-to-label map for select fields |
| `allow_delete` | Top-level setting; **false by default** |

Constraints: **1–30 fields**, at least one table column and one editable form field, and **1–50 static Select options** for a Select. Hidden fields, suspicious secret-like names and non-fillable form fields are rejected.

The generator now supports basic scalar fields, static Select options, and four existing typed Eloquent relation types: `belongs_to`, `belongs_to_many`, `has_one`, `has_many`. It does not create Eloquent methods, tables, FKs or pivot migrations. Custom fields, uploads, arbitrary PHP, remote APIs, charts and nested layouts remain outside the MCP generator.

## 5. Safe write/update contract

Every preview returns:

- `operation`: `create` or `update`
- `writable` and optional `reason`: whether the target may be written
- `diff`: proposed PHP source changes
- `proposal_hash`: SHA-256 binding a write to the generated proposal
- `expected_sha`: SHA-256 of the existing file, **required for updates** and `null` for creates

`create_resource` refuses to overwrite an existing path. `update_resource` accepts only unmodified, AI-managed files whose embedded fingerprint is intact and whose current SHA matches the preview. Manual edits intentionally disable AI-managed updates. If `writable=false`, hashes are stale, the schema changed, or a route key conflicts: **stop and re-inspect/re-preview**. Do not strip the fingerprint or force an overwrite.

**Hashes are consistency checks, not user approval.** An agent should show the proposal and only call write tools after explicit user write intent.

Do not enable delete actions without a request. These tools do not configure authorization: the host must still define the `accessFlashboard` Gate, register applicable model policies and scope `Resource::query()` for multitenant data access. Model fillability alone is **not** sufficient authorization.

## 6. Prompts to give your coding agent

**Only inspect**

> What Eloquent models can Flashboard manage? Inspect their fields and existing Resources. Identify fields that are hidden or not fillable. Report findings without writing files.

**Plan an admin panel**

> Propose minimal CRUD Resources for Orders and Products using Flashboard MCP. Keep table columns useful, avoid unnecessary filters and leave delete disabled. Preview each Resource and show diffs. Do not write yet.

**Create after approval**

> I approve the previewed Orders Resource. Create it using the exact preview hash. Report the generated path and class, then check discovery.

**Modify AI-generated Resource**

> Preview changing OrdersResource: hide created_at from its table, keep it on detail. If the file has manual edits, stop without overwriting it.

**Ask for a feature outside MCP**

> I need a BelongsTo customer field. Inspect the Flashboard typed Resource DSL and propose a minimal manual PHP change. Do not claim the MCP generator supports relation generation.

## 7. Troubleshooting

| Symptom | What to check |
| --- | --- |
| Agent cannot see the Flashboard tools | Host app is using PHP 8.5+, correct `artisan` path, `FLASHBOARD_AI_MCP=true`, cleared cached config, MCP client reconnected |
| `list_models` empty | Models should be under `App\Models` and autoloadable. The current scanner does not discover arbitrary custom namespaces |
| `inspect_model` error | Concrete model class, existing table, valid host DB connection; no record query should be needed |
| Preview rejects a field | Check name, real schema, fillable/hidden flags, selected type, Select options and reserved sensitive fields |
| `writable=false` | The file is handwritten or changed since AI generation. It cannot be updated through MCP |
| Hash mismatch | Re-run `preview_resource` for the **exact** intended spec and use the fresh hashes |
| File exists but doesn't appear in panel | Provider calls `discover()` or equivalent, class is autoloadable, policies/gate permit access and route key is unique |

Validate the generated file with PHP tooling and by opening the host panel. In the Flashboard package checkout, `composer test` and `composer analyse` are available. Never claim a test passed unless it was actually run.

## 8. When MCP isn't needed

If the task needs one custom PHP Resource or unsupported UI functionality, direct Codex/Claude source editing may be simpler and more powerful. MCP is useful for a repeatable **inspect → preview → generate** workflow, not for replacing the developer or replicating MoonShine V's REST/hosted capabilities.

## Relationship generation and incremental MCP tools

Use `relations` in the same specification as `fields` when calling `preview_resource`, `create_resource` or `update_resource`. These examples **must match real Eloquent model methods** (the generator doesn't create missing methods):

```json
{
  "relations": [
    {"name": "customer", "type": "belongs_to", "title_attribute": "name"},
    {"name": "tags", "type": "belongs_to_many", "title_attribute": "name"},
    {"name": "items", "type": "has_many", "show_on_edit": true, "read_only": true},
    {"name": "featuredItem", "type": "has_one"}
  ]
}
```

- `belongs_to` creates a Flashboard lazy form selector for the real Eloquent foreign key, such as `customer_id`. That column must be fillable; duplicate scalar FK inputs are suppressed.
- `belongs_to_many` creates a multi-select for an existing Eloquent pivot relationship.
- `has_one` and `has_many` create native Flashboard relation managers. These are **read-only by default**; use `read_only=false` only by explicit request and after checking authorization.
- Methods must be public, zero-argument and declare an Eloquent relation return type. The related model must have exactly one registered Flashboard Resource, or specify its exact registered class in the optional `resource` field. The generator does not silently fall back to querying unscoped Eloquent models.
- Optional `title_attribute` and `search_columns` must name existing, visible columns on the related table.

For changing an **existing AI-generated** Resource, the new tools avoid resubmitting the whole configuration:

1. `get_resource_spec(resource: "Orders")` reads the current embedded, fingerprint-verified definition.
2. `add_relation` with `{"resource":"Orders","relation":{"name":"customer","type":"belongs_to"}}` is **preview-only** by default. It returns a diff, `proposal_hash` and `expected_sha` without writing.
3. Once the user approves, call `add_relation` again with the **same arguments**, `apply=true` and both exact preview hashes.
4. `add_field` does the same for a scalar column via a `field` object, such as `{"name":"priority","type":"number","table":true,"form":true}`.

Incremental tools **append only**; renames/removals still require a full-spec `preview_resource` and `update_resource`. Legacy AI-generated Resources created before the embedded `@flashboard-ai-spec` manifest cannot use incremental tools until regenerated using full-spec preview/update. A handwritten Resource or manually edited generated file is never an eligible target. Do not modify fingerprint markers to bypass the restriction.

### Prompt examples

> Check Order and Customer models and current Resources. Preview adding a BelongsTo customer selector to OrdersResource; do not apply yet.

> Approved: apply the previewed customer relation with the exact hashes and verify discovery.

> Preview adding a numeric priority field to OrdersResource without changing its other fields. Do not apply yet.

**Scope:** this generates **Flashboard PHP definitions**, not database relations, migrations or authorization policies. Host policies, resource query scoping, permissions and the underlying schema remain the host application's responsibility.
