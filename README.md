# rag-tracker

A small weekly/monthly/quarterly RAG (Red / Amber / Green) status tracker for client deliverables. Replaces a PowerPoint + spreadsheet workflow with a web app you can update in 10 minutes and export as a PDF for leadership.

Built for personal use first; user authentication and roles are scaffolded so the rest of the org can use it later if useful.

## Stack

- **Backend:** PHP 8.2+, Laravel 11
- **Database:** MySQL 8
- **Frontend:** Blade templates, Tailwind CSS, Alpine.js for the small interactive bits
- **PDF generation:** DomPDF (synchronous, fast enough for one weekly report)
- **Auth:** Laravel Breeze (email + password, password reset)
- **Hosting:** Cloudways droplet, GitHub-synced auto-deploy

## What it does

| Tab | Purpose |
|---|---|
| Dashboard | Capacity vs. scope for the current week / month / quarter, count of blocked + red items |
| Clients | CRUD on clients + their contact persons |
| Projects | CRUD on projects, each linked to a client + optional responsible contact |
| Deliverables | Master list of every deliverable. Days target, MoSCoW, RAG status, deadline |
| Weekly Plan | This week's allocations (0.5-day increments). Capacity check |
| Monthly Plan | Current calendar-month allocations. Capacity check |
| Quarterly Plan | Rolling 3-month allocations |
| Review | End-of-week: tick what's done, log days spent, add ad-hoc work, roll incomplete to next week |
| Reports | Generate the weekly PDF (last week's review + new week's plan + monthly + quarterly state) |

## Roles

Strict separation — one role per user, no mixing:

- **admin** — manages users and viewer assignments. Does not access tracker data.
- **user** — full CRUD on their own clients / projects / deliverables / plans / reports.
- **viewer** — read-only access to a specific user's tracker data (for leadership).

## Status codes (carried over from the spreadsheet)

- **R (Red)** — won't deliver on the current plan. Not signed off / no target date / scope over capacity. Default for anything not demonstrably done.
- **A (Amber)** — on the plan, at risk, needs attention this week.
- **G (Green)** — outcome delivered, tested, signed off. Not "I'm working on it" — done.
- **B (Blocked)** — stalled waiting on someone else's input.

## Setup

See [`SETUP.md`](SETUP.md) for the one-time install + push-to-GitHub + Cloudways deploy steps.

## Design doc

See [`docs/design.md`](docs/design.md) for the full database schema, page structure, and build milestones.

## Source

The thinking behind the data model came from a real-world RAG sheet review with Andrew (May 2026). Notes in [`docs/design.md`](docs/design.md#why-this-shape).

## License

Private / internal. No license granted.
