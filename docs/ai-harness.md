# AI Harness (alpha)

Flashboard supports an **opt-in local MCP server** for coding agents. It works with an existing Laravel application and its Eloquent models; it does not include an LLM, run queries against user records, or serve a public MCP endpoint.

## Enable in the host Laravel app

Install the optional Laravel MCP package in the **host application**, not in the Flashboard package checkout:

    composer require laravel/mcp:^1.2

Set in the host application's .env:

    FLASHBOARD_AI_MCP=true

After configuration reload, register the local stdio server in Codex or another MCP client. For Codex CLI:

    codex mcp add flashboard -- php /absolute/path/to/your-laravel-app/artisan mcp:start flashboard

Run the command in the host Laravel application's project environment, with access to its .env and database. Alternatively, configure your editor's MCP server using command php and args ["/absolute/path/to/artisan", "mcp:start", "flashboard"]. No GitHub Actions or server-side AI provider is needed.

The MCP server is registered by the Flashboard service provider **only** for console requests, and only when laravel/mcp is installed and FLASHBOARD_AI_MCP is enabled. There is no HTTP MCP route. Treat the local MCP process as a trusted developer: it has access to your application's environment.

## MCP tools

| Tool | Action |
| --- | --- |
| list_models | Discover concrete App\Models Eloquent classes |
| inspect_model | Read table columns, fillable/hidden flags and typed relationship names (schema only; no user records) |
| list_resources | List registered Flashboard resources |
| preview_resource | Validate a proposed resource, return a source diff, proposal_hash and expected_sha without writing |
| create_resource | Create a new AI-managed class using the preview's proposal_hash |
| update_resource | Update only an untouched AI-managed class using both preview hashes |

The resource specification contains resource, model and a fields array. Every field has a required name and optional type (text, textarea, number, date, select, checkbox, toggle), label, table, form, detail, searchable, sortable, required, filter, options (for select fields). A top-level allow_delete flag is opt-in.

For example, ask your coding agent to list models, inspect App\Models\Order, and preview an Orders resource with id, status (Select options draft/published), and total. Review the returned diff before asking it to create the resource using the exact proposal_hash.

## Guardrails and alpha limitations

- Generated classes live in app/Flashboard/Ai and are picked up by normal discovery when the panel provider calls discover().
- Existing handwritten Resources are never overwritten. The generator embeds a content fingerprint. Changing a generated class manually disables automatic updates.
- Updating also requires the expected_sha from preview. The agent must re-preview after any changes.
- Only Eloquent models under App\Models, existing database columns and a narrow set of field types are accepted. Hidden and secret-like fields are rejected.
- Automatic create/update supports scalar fields and static Select options. Relationship names can be inspected but relation field generation, remote REST APIs, custom PHP, and complex nested forms are intentionally out of scope.
- Generated delete actions default to **off**. The host application's accessFlashboard Gate and resource policies still govern the resulting UI.
- No record CRUD/SQL through MCP: the tools only inspect metadata and write generated PHP resource definitions.
- If your app's model policy or mass-assignment rules are stricter than the proposed form, adapt the spec accordingly.

For a single one-off resource, using Codex directly on your existing PHP Resource files is still a valid, simpler alternative.
