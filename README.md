# dRAGonattack Tracker

> Repo: `dRAGon-attack` — lives at `github.com/sepici/dRAGon-attack`.

A weekly / monthly / quarterly RAG (Red / Amber / Green) status tracker
for client deliverables. Replaces a PowerPoint + spreadsheet workflow
with a web app you can update in 10 minutes and export as a PDF for
leadership — and that any AI agent (Claude, ChatGPT, n8n, your own
script) can drive on your behalf.

## Stack

- **Backend:** PHP 8.1+, Laravel 10
- **Database:** MySQL 8 (prod), SQLite in-memory (tests)
- **Frontend:** Blade templates, Tailwind CSS, Alpine.js for small interactive bits
- **PDF generation:** DomPDF (synchronous; fast enough for the weekly report + monthly timesheet)
- **Auth (web):** Laravel Breeze (email + password, password reset)
- **Auth (API):** Laravel Sanctum personal-access tokens with scoped abilities
- **Hosting:** Cloudways droplet, GitHub-synced deploy via `deploy.sh`

## What it does

| Page | Purpose |
|---|---|
| Dashboard | Capacity vs. scope for week / month / quarter, deliverable counts by RAG, upcoming deadlines, recent completions |
| Tracker → Clients | CRUD on clients + their contact persons |
| Tracker → Projects | CRUD on projects (one client per project) |
| Tracker → Deliverables | Master list of every deliverable. Target in days, MoSCoW, RAG, deadline |
| Plans → Weekly / Monthly / Quarterly | Allocate deliverables to a period. Half-day increments, capacity check, period-scoped Spent |
| Journal | Day-by-day log of hours per (date, deliverable) or ad-hoc work. The source of truth for "what did I spend time on?" |
| Review | End-of-week retrospective. Tick what's done, add notes, roll incomplete items into next week. Hours come from the journal — no double entry |
| Exports → Reports | Generate the weekly PDF (last week's review + next week's plan + month + quarter) |
| Exports → Timesheets | Monthly grid PDF (rows = projects, columns = days 1..N, cells = hours) for client invoicing or HR |
| Connect AI | Copy-paste guides for plugging Claude / ChatGPT / curl / n8n into the API |

## Using the app

A typical workflow once you're set up:

1. **First login (admin).** After `php artisan db:seed` (see [`SETUP.md`](SETUP.md)) you have one admin account. The admin only manages users — it doesn't touch tracker data. Create at least one **user** account from `/admin/users`.

2. **Sign in as a user.** All your tracker data lives under your own user. Other users on the same install never see your clients, projects, or hours.

3. **Add your clients.** *Tracker → Clients → New client.* Optionally add contact persons for each client (they show up later as "responsible contact" pickers on deliverables).

4. **Add projects.** *Tracker → Projects → New project.* One client per project. Set an optional deadline.

5. **(Optional) Add milestones.** Useful for projects with phased work, or when you want to forward-plan in chunks before you've scoped the specific deliverables. *Tracker → Milestones → New milestone.* Small projects skip this layer entirely.

6. **Add deliverables.** *Tracker → Deliverables → New deliverable.* Pick the project, optionally pick a milestone in that project, set a target in days, MoSCoW priority, deadline, and starting RAG status (defaults to **R**). The status reflects the *outcome* — flip to **G** only when the deliverable is delivered, tested, and signed off.

7. **Plan the week.** *Plans → Weekly.* Allocate deliverables (or milestone envelopes) to this week in half-day increments. The capacity widget tells you if you're over-committed.

8. **Plan the month and quarter the same way.** *Plans → Monthly / Quarterly.* Same controls; bigger windows. These feed into the weekly PDF report.

9. **Log time daily.** *Journal → today.* Add rows for each deliverable you worked on, plus any ad-hoc rows for unplanned work (meetings, support, admin). Half-hour increments. This is the single source of truth for "what did I do" — every other "spent" number on every other page is derived from it.

10. **Friday: weekly review.** *Review.* Tick deliverables you've actually finished (delivered + signed off), add a one-line outcome / blocker note, then **Roll incomplete forward** copies anything still open into next week's plan as Red.

11. **End of week: generate the report.** *Exports → Reports → Generate.* Produces a PDF with last week's review, next week's plan, and the current monthly / quarterly views — ready to attach to an email to leadership.

12. **End of month: generate the timesheet.** *Exports → Timesheets → Generate.* Landscape A4 PDF with project rows × day columns — the standard format for client invoicing or HR submission.

13. **(Optional) Connect an AI agent.** *Connect AI* in the user dropdown. Issue a Sanctum token, then point Claude / ChatGPT / curl / n8n / the included MCP server at the OpenAPI spec. Prompts like *"log 2 hours on Acme today"* land as a single fuzzy-matched API call.

You can drive everything through the web UI, or through the API once a token is issued — they edit the same database.

## How time gets tracked

Two layers, one source of truth:

1. **Plan in days.** When you scope a deliverable or allocate it to a
   week / month / quarter, you input days (half-day increments).
2. **Log hours per day.** In the journal, you record actual hours
   against deliverables (or as ad-hoc rows for unplanned work).
3. **Everything else is derived.** `hours_spent`, `remaining`, period
   totals, the timesheet grid, the weekly review's Spent column — all
   sums of `time_logs`. One workday = 8 hours, configurable in
   `App\Support\TimeUnits::HOURS_PER_DAY`.

Storage is always hours. Display surfaces both: targets/capacity/
allocation read days-leading (`2d (16h)`); spent/logged read hours-
leading (`12h (1.5d)`).

## Roles

Strict separation — one role per user, no mixing:

- **admin** — manages users only. Doesn't access tracker data; doesn't get API tokens.
- **user** — full CRUD on their own clients / projects / deliverables / plans / journal / reports / timesheets. Can issue API tokens.
- **viewer** — read-only across all users' tracker data (for leadership). No API tokens.

## RAG status codes

- **R (Red)** — won't deliver on the current plan. Not signed off / no target date / scope over capacity. Default for anything not demonstrably done.
- **A (Amber)** — on the plan, at risk, needs attention this week.
- **G (Green)** — outcome delivered, tested, signed off. Not "I'm working on it" — done.
- **B (Blocked)** — stalled waiting on someone else's input.

## REST API & agent integrations

The whole user-scoped surface is exposed under `/api/v1/*` with
Sanctum bearer tokens.

- **OpenAPI 3.1 spec** at `/api/v1/openapi.json` (public; no token needed).
  Drop the URL into a ChatGPT Custom GPT and it'll generate the tool
  calls for you.
- **Personal-access tokens** are created from `/profile`, with per-token
  abilities (`time-logs:read`, `time-logs:write`, `tracker:read`,
  `tracker:write`, plus `read:all` / `write:all` wildcards). Show-once
  plaintext, revocable anytime.
- **Reference MCP server** in [`mcp/`](mcp/README.md) — TypeScript,
  32 tools, hand-curated descriptions tuned for Claude Desktop.
- **In-app connection guide** at `/agent` with copy-paste snippets for
  Claude Desktop, ChatGPT Custom GPTs, and raw curl.

The headline agent ergonomics:

- `POST /api/v1/time-logs` takes `deliverable_name` as a fuzzy
  substring — "acme oauth" resolves to the right deliverable id.
  A miss returns a 422 listing candidate names so the agent can
  self-correct on retry.
- `date` fields accept `"today"`, `"yesterday"`, ISO, or natural-
  language ("last monday", "3 days ago").
- Every duration in responses ships both `*_hours` and `*_days` so the
  agent doesn't have to convert.

## Setup

See [`SETUP.md`](SETUP.md) for the one-time install + push-to-GitHub +
Cloudways deploy steps.

## Design doc

See [`docs/design.md`](docs/design.md) for the database schema, page
structure, and milestone history.

## Source

The data model started with a mixture of a RAG sheet with MoSCoW with
features that make life easier for developers and project managers.
Notes in [`docs/design.md`](docs/design.md#why-this-shape).

## License

MIT — see [`LICENSE`](LICENSE).
