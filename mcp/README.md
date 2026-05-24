# dragonattack-mcp

Reference [Model Context Protocol](https://modelcontextprotocol.io) server
for the **dRAGonattack Tracker** REST API. Wires the `/api/v1` surface
into Claude Desktop (or any MCP client) as a set of named tools.

## What it gives Claude

21 tools across:

- **Account** — `whoami`
- **Clients** — `list_clients`, `get_client`, `create_client`, `update_client`
- **Projects** — `list_projects`, `get_project`, `create_project`, `update_project`
- **Deliverables** — `list_deliverables` (with fuzzy `name_like`), `get_deliverable`, `create_deliverable`, `update_deliverable`
- **Plans** — `get_weekly_plan`, `get_monthly_plan`, `get_quarterly_plan`
- **Plan items** — `add_to_plan`, `update_plan_item`, `remove_from_plan`
- **Time logs** — `list_time_logs`, **`log_time`** (the agent's bread and butter), `update_time_log`, `delete_time_log`

Delete operations on clients, projects, and deliverables aren't exposed — those cascade in surprising ways (a deleted client wipes its projects and their deliverables) and have no agent-side confirm dialog, so they stay web-only on purpose.

`log_time` accepts a fuzzy `deliverable_name` substring and a relative
`date` (`"today"`, `"yesterday"`, natural language, or ISO), so a prompt
like *"Log 2 hours on Clonallon Proposal today"* lands as a single
properly-resolved API call.

## Install

```bash
cd mcp
npm install
npm run build
```

Builds to `dist/index.js`. From now on you can launch it with
`node /absolute/path/to/mcp/dist/index.js`.

## Get a token

Log into the tracker → top-right user menu → **Connect AI** → follow the
instructions, or go straight to your profile and create a token with at
least `time-logs:write` and `tracker:read` ticked (or use the `read:all`
+ `write:all` wildcards for the easy mode).

## Wire up Claude Desktop

Open Claude's config file:

- **macOS:** `~/Library/Application Support/Claude/claude_desktop_config.json`
- **Windows:** `%APPDATA%\Claude\claude_desktop_config.json`

Add the server:

```json
{
  "mcpServers": {
    "dragonattack": {
      "command": "node",
      "args": ["/ABSOLUTE/PATH/TO/rag-tracker/mcp/dist/index.js"],
      "env": {
        "DRAGONATTACK_API_URL": "https://dragonattack.tr/api/v1",
        "DRAGONATTACK_API_TOKEN": "1|paste-your-token-here"
      }
    }
  }
}
```

Quit and relaunch Claude Desktop. You should see a hammer icon in the
chat input; clicking it lists the 17 `dragonattack` tools.

## Try it

In Claude:

> Log 1.5 hours on the Clonallon Proposal today.

Claude calls `log_time` with `{hours: 1.5, deliverable_name: "Clonallon Proposal"}`.
The API resolves the fuzzy name to deliverable id 8, creates the log,
returns the updated derived `hours_spent`. Claude reports back with the
new total.

> What's on my weekly plan?

Claude calls `get_weekly_plan`, summarises the items + capacity.

> What did I work on yesterday?

Claude calls `list_time_logs` with `date: "yesterday"`.

## Configuration

| Env var                   | Required | Purpose                                          |
|---------------------------|----------|--------------------------------------------------|
| `DRAGONATTACK_API_URL`    | yes      | Base URL of the API, e.g. `https://dragonattack.tr/api/v1` |
| `DRAGONATTACK_API_TOKEN`  | yes      | Sanctum personal-access token (format `id\|hash`)         |

## Local dev

```bash
DRAGONATTACK_API_URL=https://dragonattack.tr/api/v1 \
DRAGONATTACK_API_TOKEN=1|... \
  npm run dev
```

`npm run dev` uses `tsx` to run the TypeScript directly. Stdio-based, so
to actually exercise tools you need an MCP client connected (Claude
Desktop, or the [`mcp` inspector](https://github.com/modelcontextprotocol/inspector)).

## How it fits

- The OpenAPI spec at `/api/v1/openapi.json` is the *contract* — every
  consumer reads from there.
- This server hand-curates a tool catalog (`src/tools.ts`) so Claude
  sees friendly descriptions instead of raw OpenAPI summaries.
- `src/api.ts` is a thin fetch wrapper — no codegen, no extra deps.

If you'd rather auto-generate the catalog from OpenAPI, the spec is
public and a generator drop-in is straightforward — but the curated
descriptions are what makes the agent reliable.

## License

MIT. Use it, fork it, ship it.
