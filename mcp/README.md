# dragonattack-mcp

Reference [Model Context Protocol](https://modelcontextprotocol.io) server
for the **dRAGonattack Tracker** REST API. Two transports:

- **stdio** (default) — Claude Desktop launches the server as a local
  subprocess. One install per machine.
- **HTTP / SSE** — server runs once on a public host; any device's Claude
  Desktop (or other MCP client) connects via URL with a bearer token.

Tool catalog and behaviour are identical between the two modes — only the
wire transport differs. The catalog is hand-curated in `src/tools.ts` so
Claude sees friendly descriptions tuned for agent use.

## What it gives Claude

28 tools across:

- **Account** — `whoami`
- **Clients** — `list_clients`, `get_client`, `create_client`, `update_client`
- **Projects** — `list_projects`, `get_project`, `create_project`, `update_project`
- **Milestones** — `list_milestones`, `get_milestone`, `create_milestone`, `update_milestone`, **`mark_scope_complete`** (one-shot wrapper for the "scope is locked, you can now flip the gate to Green" moment)
- **Deliverables** — `list_deliverables` (with fuzzy `name_like`), `get_deliverable`, `create_deliverable`, `update_deliverable` — both write tools accept an optional `milestone_id` (must belong to the same project)
- **Plans** — `get_weekly_plan`, `get_monthly_plan`, `get_quarterly_plan`
- **Plan items** — `add_to_plan` (allocate to EITHER a deliverable OR a milestone envelope — exactly one), `update_plan_item`, `remove_from_plan`
- **Time logs** — `list_time_logs`, **`log_time`** (the agent's bread and butter), `update_time_log`, `delete_time_log`

Delete operations on clients, projects, deliverables, and milestones aren't
exposed — those cascade in surprising ways and have no agent-side confirm
dialog, so they stay web-only on purpose.

`log_time` accepts a fuzzy `deliverable_name` substring and a relative
`date` (`"today"`, `"yesterday"`, natural language, or ISO), so a prompt
like *"Log 2 hours on Clonallon Proposal today"* lands as one
properly-resolved API call.

Milestones are an *optional* grouping layer — small projects skip them.
When used, `add_to_plan` with `milestone_id` lets you forward-plan in
coarse chunks ("5 days on Phase 1 next month") before the deliverables
under that phase are scoped; `mark_scope_complete` is the explicit
"I've added every deliverable that belongs here" gate that lets the
derived milestone status reach Green.

---

## Get a token

Whichever transport you use, you need a Sanctum personal-access token.

1. Log into the tracker → top-right user menu → **Connect AI** (or go straight to `/profile`).
2. Create a token. For "easy mode," tick `Read everything` and `Write everything`.
3. Copy the plaintext token (shown once). Format: `<id>|<hash>`.

---

## Option A: local stdio mode

Install once per machine; Claude Desktop launches the server as a subprocess.

### Install

```bash
cd mcp
npm install
npm run build
```

### Configure Claude Desktop

Edit `~/Library/Application Support/Claude/claude_desktop_config.json` (macOS)
or `%APPDATA%\Claude\claude_desktop_config.json` (Windows). Add:

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

Quit and relaunch Claude. The hammer icon should list 21 `dragonattack` tools.

### Skip-the-build dev variant

For iterative development without rebuilding every change, point Claude at the
local `tsx` binary on the TypeScript source directly:

```json
"command": "/ABSOLUTE/PATH/TO/rag-tracker/mcp/node_modules/.bin/tsx",
"args": ["/ABSOLUTE/PATH/TO/rag-tracker/mcp/src/index.ts"]
```

`tsx` compiles on the fly; ~300ms slower Claude startup, no `dist/` to keep fresh.

---

## Option B: remote HTTP mode (device-independent)

Run the server once on a public host. Configure each device's Claude Desktop
with the URL + your token. No `node`, no `npm install`, no per-machine build.

### How it works

- The server listens on an HTTP port (default `3001` on `127.0.0.1`).
- nginx (or any reverse proxy) terminates TLS and routes `/mcp` to it.
- Claude Desktop opens an SSE session to the URL, sending its Bearer token
  on every request.
- The server forwards each tool call to the Laravel API with that token.
  The API enforces validity + ability scope. Revoking a token at `/profile`
  takes effect on the very next tool call.

Sessions are isolated per token. Multiple clients can connect simultaneously
without leaking each other's data.

### Deploy on Cloudways

1. **SSH into the droplet**:

   ```bash
   ssh -p 22 master_user@your-droplet.cloudwaysapps.com
   cd ~/applications/<app-name>/public_html
   ```

2. **Install Node.js** (if not already there). The Cloudways base image
   doesn't ship a modern Node, so use `nvm`:

   ```bash
   curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.39.7/install.sh | bash
   exec $SHELL -l
   nvm install --lts
   nvm alias default lts/*
   node --version    # expect v20+
   ```

3. **Build the MCP server**:

   ```bash
   cd ~/applications/<app-name>/public_html/mcp
   npm install
   npm run build
   ```

4. **Install PM2** (process supervisor — restarts on crash, on reboot):

   ```bash
   npm install -g pm2
   ```

5. **Start the service**:

   ```bash
   DRAGONATTACK_API_URL="https://dragonattack.tr/api/v1" \
     pm2 start ecosystem.config.cjs
   pm2 save
   pm2 startup     # follow the printed `sudo` command to make pm2 survive reboots
   ```

   Confirm with `pm2 status` and `pm2 logs dragonattack-mcp`. Hit the liveness
   probe to be sure:

   ```bash
   curl http://127.0.0.1:3001/healthz
   # {"ok":true,"sessions":0}
   ```

6. **Add an nginx route**. In Cloudways → Application Settings →
   Application Settings (Advanced) → "Nginx Settings" (or edit
   `/etc/nginx/sites-available/<app>` manually), drop in a location block:

   ```nginx
   location /mcp {
       proxy_pass http://127.0.0.1:3001;
       proxy_http_version 1.1;
       proxy_set_header Host $host;
       proxy_set_header X-Real-IP $remote_addr;
       proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
       proxy_set_header X-Forwarded-Proto $scheme;
       proxy_set_header Upgrade $http_upgrade;
       proxy_set_header Connection "upgrade";

       # SSE requires these:
       proxy_buffering off;
       proxy_cache off;
       proxy_read_timeout 24h;
       chunked_transfer_encoding off;
   }
   ```

   Reload nginx (Cloudways does this for you when you save in the panel).

7. **Test from the public URL**:

   ```bash
   curl https://dragonattack.tr/mcp/healthz
   # {"ok":true,"sessions":0}
   ```

### Configure Claude Desktop (remote mode)

Once the public URL works, point Claude Desktop at it. Two ways:

**A. Via the in-app Connectors UI** (newer Claude Desktop versions):

1. Settings → Connectors → Add custom connector.
2. URL: `https://dragonattack.tr/mcp`
3. Auth: Bearer / API key → paste your token.

**B. Via the config file** (works on every version):

```json
{
  "mcpServers": {
    "dragonattack": {
      "type": "http",
      "url": "https://dragonattack.tr/mcp",
      "headers": {
        "Authorization": "Bearer 1|paste-your-token-here"
      }
    }
  }
}
```

(`"type": "http"` is the key field — that's what tells Claude Desktop to
connect remotely instead of spawning a subprocess.)

Quit and relaunch. Now any device with that config — your Mac, your
laptop, your Windows PC at work — reaches the same MCP without any per-
machine install. Only the token differs (and you can use the same token
across devices, or create one per device if you want to track usage).

### Pushing updates

Update the code in the repo locally, push to GitHub, then on the server:

```bash
cd ~/applications/<app-name>/public_html
git pull
cd mcp
npm install            # picks up new deps if package.json changed
npm run build
pm2 restart dragonattack-mcp
```

Connected Claude Desktops re-handshake on their next call. No client-side
config change needed.

---

## Try it

In Claude, after wiring up either mode:

> Log 1.5 hours on the Clonallon Proposal today.

> What's on my weekly plan?

> What did I work on yesterday?

> Create a new client called Acme Industries, then create a project under it called "Q3 audit" with deadline 2026-08-31.

---

## Configuration reference

| Env var                   | Required        | Used by | Default       | Purpose                                                  |
|---------------------------|-----------------|---------|---------------|----------------------------------------------------------|
| `MCP_TRANSPORT`           | no              | both    | `stdio`       | `stdio` or `http`                                        |
| `DRAGONATTACK_API_URL`    | yes (both)      | both    | —             | Base URL of the API, e.g. `https://dragonattack.tr/api/v1` |
| `DRAGONATTACK_API_TOKEN`  | yes (stdio)     | stdio   | —             | Sanctum PAT used for every tool call                    |
| `HOST`                    | no (http)       | http    | `127.0.0.1`   | Address to bind. Keep on loopback behind a reverse proxy. |
| `PORT`                    | no (http)       | http    | `3001`        | TCP port to listen on.                                  |

In `http` mode, the token comes from each client's `Authorization: Bearer`
header — there is no shared `DRAGONATTACK_API_TOKEN` env var.

---

## Local dev

```bash
# Stdio mode (default) — needs an MCP client connected via stdio to exercise.
npm run dev

# HTTP mode — curl-friendly:
DRAGONATTACK_API_URL=https://dragonattack.tr/api/v1 npm run dev:http

# Smoke-test the HTTP server (in another terminal):
curl http://127.0.0.1:3001/healthz
# {"ok":true,"sessions":0}
```

## How it fits

- `src/tools.ts` — the 21 tool definitions Claude sees.
- `src/api.ts` — thin fetch wrapper, one method per endpoint group.
- `src/server-factory.ts` — builds an MCP Server bound to one user's token.
- `src/stdio.ts` — stdio transport. Token from env.
- `src/http.ts` — HTTP transport. Token per request, in `Authorization`.
- `src/index.ts` — dispatcher; picks stdio or http via `MCP_TRANSPORT`.

If you'd rather auto-generate the catalog from the OpenAPI spec at
`/api/v1/openapi.json`, the spec is public and a generator drop-in is
straightforward — but the curated descriptions are what makes the agent
reliable.

## License

MIT.
