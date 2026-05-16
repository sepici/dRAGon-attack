# dRAGonattack Tracker — design doc

Single source of truth for what we're building. Update this when decisions change.

## Why this shape

The data model and behaviour come from two places:

1. A working RAG spreadsheet (`RAG_Tracker.xlsx`) used for weekly client status reporting — projects, deliverables, MoSCoW priority, RAG status, days of effort, weekly + monthly + quarterly plans, ad-hoc backlog.

2. A May 2026 review with Andrew, which flagged structural issues with the spreadsheet:
   - RAG without target dates is meaningless.
   - "Working on it" ≠ delivered. Status must reflect the *outcome* (signed off + tested), not the task.
   - Most rows should default to **R**, not A/G — leadership needs to see what won't deliver, early.
   - "Blocked" needs to be visible as a distinct state — items waiting on client input get lost otherwise.
   - Scope vs. capacity must be explicit. If planned days > available days, that's a red flag, not a footnote.
   - Each deliverable must be tagged with its client.
   - "What we are NOT doing" (backlog) needs to be explicit.

Those points shaped the schema and the workflow below.

## Stack

- PHP 8.1+, Laravel 10
- MySQL 8 (Cloudways managed)
- Blade templates + Tailwind CSS + Alpine.js
- DomPDF for PDF reports (synchronous; one report < 2s)
- Laravel Breeze 1.x for auth scaffolding

## Roles (strict separation)

One role per user, enforced at the middleware layer. Each role lands on a different post-login route.

| Role | Lands on | Can do |
|---|---|---|
| admin | `/admin/users` | Create/edit/delete users. Nothing else. |
| user | `/dashboard` | Full CRUD on their own clients/projects/deliverables/plans/reports. Sees only their own data (`owner_id = auth()->id()`). |
| viewer | `/viewer/dashboard` | Read-only access to ALL users' tracker data. No edit affordances rendered. |

Admins do **not** also have user privileges — if the admin wants to use the tracker, they create a separate user account for that. This was an explicit decision (see chat log, May 2026): different post-login experiences should not be mixed.

## Database schema

9 tables. All FKs use `ON DELETE RESTRICT` unless noted.

### `users`
```
id              bigint PK
name            varchar(120)
email           varchar(180) unique
password        varchar(255)
role            enum('admin','user','viewer') NOT NULL  default 'user'
weekly_capacity_hours  decimal(5,2)  default 40.00
monthly_capacity_hours decimal(6,2)  default 160.00
email_verified_at      timestamp NULL
remember_token         varchar(100) NULL
created_at, updated_at
```

> Viewers currently see all users' data — no per-target scoping. If we later
> need restriction, add a `viewer_assignments` pivot (viewer_user_id ↔
> target_user_id) and filter queries on it.

### `clients`
```
id              bigint PK
owner_id        bigint FK -> users(id)
legal_name      varchar(200)
email           varchar(180) NULL
phone           varchar(40)  NULL
notes           text NULL
created_at, updated_at
INDEX (owner_id)
```

### `contact_persons`
```
id              bigint PK
client_id       bigint FK -> clients(id)  ON DELETE CASCADE
first_name      varchar(80)
last_name       varchar(80)
email           varchar(180) NULL
role_title      varchar(120) NULL
created_at, updated_at
INDEX (client_id)
```

### `projects`
```
id              bigint PK
owner_id        bigint FK -> users(id)
client_id       bigint FK -> clients(id)
name            varchar(180)
description     text NULL
deadline        date NULL
responsible_contact_id  bigint FK -> contact_persons(id) NULL  ON DELETE SET NULL
status          enum('R','A','G','B') default 'R'
moscow          enum('M','S','C','W') NULL
created_at, updated_at
INDEX (owner_id), INDEX (client_id)
```

### `deliverables`
```
id              bigint PK
project_id      bigint FK -> projects(id)  ON DELETE CASCADE
name            varchar(200)
description     text NULL
target_hours    decimal(6,2)  CHECK (target_hours % 0.5 = 0)
hours_spent     decimal(6,2)  default 0   CHECK (hours_spent % 0.5 = 0)
deadline        date NULL
status          enum('R','A','G','B') default 'R'
moscow          enum('M','S','C','W') NULL
completed_at    timestamp NULL
created_at, updated_at
INDEX (project_id), INDEX (deadline), INDEX (status)
```

### `deliverable_contacts`
Many-to-many: a deliverable inherits one responsible from its project, but the user can attach more contacts from the same client.
```
id              bigint PK
deliverable_id  bigint FK -> deliverables(id)  ON DELETE CASCADE
contact_person_id  bigint FK -> contact_persons(id)  ON DELETE CASCADE
created_at
UNIQUE (deliverable_id, contact_person_id)
```

### `plan_periods`
One row per actual time window the user runs (this week, this month, this quarter). Auto-created on first visit if missing.
```
id              bigint PK
owner_id        bigint FK -> users(id)
kind            enum('weekly','monthly','quarterly')
starts_on       date NOT NULL
ends_on         date NOT NULL
created_at, updated_at
UNIQUE (owner_id, kind, starts_on)
INDEX (owner_id, kind, starts_on)
```

### `plan_items`
A deliverable allocated to a plan period, OR an ad-hoc review item (when `deliverable_id IS NULL`).
```
id              bigint PK
plan_period_id  bigint FK -> plan_periods(id)  ON DELETE CASCADE
deliverable_id  bigint FK -> deliverables(id)  ON DELETE CASCADE  NULL
ad_hoc_name     varchar(200) NULL    -- only set when deliverable_id IS NULL
ad_hoc_notes    text NULL
allocated_hours decimal(6,2)  default 0  CHECK (allocated_hours % 0.5 = 0)
hours_spent     decimal(6,2)  default 0  CHECK (hours_spent % 0.5 = 0)
status          enum('R','A','G','B') default 'R'
completed_at    timestamp NULL
sort_order      smallint default 0
created_at, updated_at
INDEX (plan_period_id)
CHECK (deliverable_id IS NOT NULL OR ad_hoc_name IS NOT NULL)
UNIQUE (plan_period_id, deliverable_id) WHERE deliverable_id IS NOT NULL
```

### `reports`
```
id              bigint PK
owner_id        bigint FK -> users(id)
week_starts_on  date NOT NULL
generated_at    timestamp NOT NULL
file_path       varchar(255)   -- relative to storage/app/reports/
created_at, updated_at
INDEX (owner_id, week_starts_on)
```

## Calendar-aligned periods

- **Weekly:** Mon → Sun. `starts_on = Carbon::now()->startOfWeek(Carbon::MONDAY)`.
- **Monthly:** First → last day of the calendar month.
- **Quarterly:** Today's month start → end of (today's month + 2). So if today is 14 May, the quarter is 1 May → 31 July. The window rolls each calendar month.

## Page structure

```
GUEST
  /login, /forgot-password, /reset-password, /reset-password/{token}

ADMIN (role=admin)
  /admin/users                       list, add, edit, delete users

USER (role=user)
  /dashboard                          capacity vs scope summary + counts by status
  /clients                            list
  /clients/create, /clients/{id}/edit
  /clients/{id}                       show + nested contact persons + projects
  /projects                           list grouped by client
  /projects/create, /projects/{id}/edit, /projects/{id}
  /deliverables                       master table (same component as plans)
  /deliverables/create, /deliverables/{id}/edit
  /plans/weekly                       current week + picker
  /plans/monthly                      current month
  /plans/quarterly                    current rolling quarter
  /review                              end-of-week review form
  /reports                             list
  /reports/generate                    POST → creates this week's PDF
  /reports/{id}/download               GET PDF
  /settings                            change own password, capacity defaults

VIEWER (role=viewer)
  /viewer/dashboard                    read-only dashboard across all users
  /viewer/users/{id}                   drill into a specific user's tracker
  /viewer/users/{id}/plans/{kind}
  /viewer/users/{id}/reports
```

## Shared table component

`<x-plan-table :rows="$rows" :show-allocation="true" />` is the single Blade component used by Deliverables, Weekly Plan, Monthly Plan, Quarterly Plan, and the PDF report.

Columns (in order):
1. ID
2. Deliverable
3. Client
4. Owner
5. Target days
6. Allocated days (hidden on Deliverables; shown on plans)
7. Days spent
8. Deadline
9. MoSCoW chip
10. Status chip (R/A/G/B with auto-colour)
11. Notes

Sort and filter by Client, Status, MoSCoW.

## Weekly review flow (the most interesting bit)

`/review` lists every `plan_item` in the current weekly period.

For each row, the user can:
- ✓ Mark complete
- Enter actual `hours_spent` (0.5 increments; 8h = 1 day)
- Add a per-item note

Plus: a "+ Add ad-hoc item" button at the bottom for unplanned work (server emergencies, etc.). Ad-hoc items have name, notes, and hours_spent. They don't link to a deliverable.

On **Submit**, inside a single DB transaction (`WeeklyReviewService::process()`):

1. For each row marked complete:
   - Set `plan_items.completed_at = now()`, `plan_items.status = 'G'`.
   - If `deliverable_id` is set, add `hours_spent` to `deliverables.hours_spent`.

2. For each row not marked complete:
   - Update `plan_items.hours_spent`.
   - Recolour: `R` if past deadline, else `A` if any hours were spent (still in flight), else leave as set.

3. For each ad-hoc item: insert a `plan_items` row with `deliverable_id = NULL`, `ad_hoc_name`, `hours_spent`, `completed_at = now()`, `status = 'G'`.

4. Recompute monthly + quarterly `plan_items.hours_spent` for the affected deliverables. This is *derived*, not stored on those plan_items as a hand-tracked counter — it's `SUM(weekly plan_items.hours_spent WHERE deliverable_id = X AND week falls inside the monthly/quarterly period)`. Implemented via a single query in `PlanRollupService`.

5. Optionally (button: "Roll incomplete forward"): copy remaining (not-completed) plan_items from this week into next week's `plan_period`, preserving `allocated_hours` and `notes`, resetting `hours_spent` and `completed_at`.

> **Hours as source of truth:** all duration columns store hours. The UI renders both via `App\Support\TimeUnits::formatHoursWithDays($h)`, which prints like `12.5h (1.6d)`. 8h ≡ 1 day; weekly default capacity 40h, monthly 160h, quarterly = 3× monthly.

## PDF report

`POST /reports/generate` creates:

1. Header: user, week-ending date, signature line.
2. **Review of completed week** — all `plan_items` with `completed_at` falling in the week, plus all ad-hoc items. Total days spent / weekly capacity.
3. **Plan for the new week** — all `plan_items` in next week's `plan_period` (the user must have populated it).
4. **Updated 1-month plan** — current state of the monthly `plan_period` items.
5. **Updated 3-month plan** — current state of the quarterly `plan_period` items.

DomPDF renders `resources/views/reports/weekly.blade.php` to PDF, stores to `storage/app/reports/{owner_id}/week-{starts_on}.pdf`, records a `reports` row, returns the download URL.

## Build milestones

| M | Scope | Estimate |
|---|---|---|
| M1 | Foundation: Breeze auth, users with role enum, viewer_assignments, role middleware, admin user-management screens, app layout shell | 3–5 days |
| M2 | Core CRUD: Clients, Contact Persons, Projects, Deliverables (with M:N contacts), shared `<x-plan-table>` component | 5–7 days |
| M3 | Plans: PlanPeriod auto-creation, plan_items CRUD, capacity-vs-allocated widget, status auto-recolouring | 4–6 days |
| M4 | Review flow: review screen, ad-hoc items, `WeeklyReviewService` transaction, monthly/quarterly rollup, roll-forward button | 3–5 days |
| M5 | PDF reports: DomPDF, blade template, report list + download | 2–4 days |
| M6 | Polish + feature tests + final Cloudways deploy | 2–3 days |

**Total ~3–4 weeks** focused dev.

## Conventions

- Routes named: `clients.index`, `clients.store`, `plans.weekly.show`, `reports.generate`, etc.
- Controllers: thin. Logic lives in `app/Services/` for review and rollup.
- Validation: dedicated FormRequest classes.
- Authorization: Policies (one per model). Middleware enforces the role landing page.
- Tests: Feature tests in `tests/Feature/` for each major flow. Unit tests for `WeeklyReviewService` and `PlanRollupService`.

## Out of scope (for now)

- Email delivery of PDFs (manual download only).
- Real-time updates / WebSockets.
- File attachments on deliverables.
- API endpoints (the app is server-rendered).
- 2FA.
- Multi-tenant org separation (per-user isolation is enough; add tenant scoping if external orgs ever onboard).
