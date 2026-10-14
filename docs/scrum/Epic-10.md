# Epic-10: Early Warning & Alert Follow-Up (Tindak Lanjut)

**Epic ID:** E-10  
**Priority:** P1 – Must Have (client request: Karlina Ibrahim, PCD Department)  
**Estimated Total:** 37 Story Points  
**Target Sprints:** Sprint 11 (1 sprint, 2-week cycle)  
**Dependencies:** Epic-02 (departments & sections), Epic-05 (budgets & Burn Index), Epic-09 (Executive Dashboard, Early Warning chart, 3-zone tolerance)  
**Lead Area:** Backend (`EarlyWarningService`, `AlertFollowUpService`) + Vue 3 Inertia pages under `resources/js/pages/early-warning/`

---

## Business Context

The client wants overtime consumption problems to be **caught early and acted on**, not just displayed on a chart. Today the dashboard shows a daily "Early Warning" chart with Hijau / Kuning / Merah zones, but nobody is prompted to _do_ anything when a section turns Kuning or Merah.

This epic turns the chart into a complete **alert workflow** with its own sidebar menu:

1. The system **detects** alerts automatically from the daily index (section + day, with a consecutive-day streak).
2. A user opens an alert, sees **why it is happening** (chart + production volume / manpower vitals), and fills a **5W2H follow-up** (What, Why, When, Where, Who, How, How much).
3. The follow-up is **saved as a draft** or **sent by email** to the PIC department ("Who"). A sent follow-up becomes read-only.

The client's HTML draft (`references/Tindak Lanjut Alert — 5W1H (Smartime) (5).html`) is a **content and flow reference only**. It is a single mobile card with static values. The real feature must be a complete, data-driven module in the app's own design system (ISUZU, see `isuzu-frontend-development` skill), not a copy of that HTML.

---

## Already Delivered (reuse — do NOT rebuild)

These exist from the previous session and are the foundation of this epic.

| Asset                             | Location                                                                                                                                                                                                                                                                            | Notes                                                                                                                                           |
| --------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------- |
| 3-zone tolerance                  | `app/Enums/BurnZone.php`, `PolicyThresholdService::classify / classifyWith / boundariesFor`, `resources/js/lib/burnZone.ts`, `useBurnZone()`, shared Inertia prop `zoneBoundaries`                                                                                                  | Hijau ≤ `green_max_pct` (70), Kuning ≤ `yellow_max_pct` (90), Merah above. Configurable per department in `policy_thresholds` (Administration). |
| Daily index data                  | `DashboardKpiService::getDailyIndexTrend()` returns `daily_zones`, `average_index`, `average_zone`, `green_max_pct`, `yellow_max_pct`, `threshold_pct`                                                                                                                              | Single-scope only (one department/section). Needs a batch variant (E10-01).                                                                     |
| Scope resolution                  | `DashboardKpiService::resolveScoping()` (public)                                                                                                                                                                                                                                    | Admin = all, others = own department.                                                                                                           |
| Early Warning chart               | `resources/js/components/dashboard/DailyIndexTrendChart.vue` (Dashboard → tab "Distribution & Trends")                                                                                                                                                                              | **Stays on the Dashboard.**                                                                                                                     |
| Follow-up table / model           | `alert_follow_ups` migration, `app/Models/AlertFollowUp.php`, factory, `app/Enums/AlertFollowUpStatus.php` (`draft`, `sent`)                                                                                                                                                        | Month-level scope today; needs alert-day link (E10-06).                                                                                         |
| Follow-up service / policy / mail | `app/Services/AlertFollowUpService.php` (`listForScope`, `save`, `recipientsFor`, `toPayload`), `AlertFollowUpPolicy`, `StoreAlertFollowUpRequest`, `UpdateAlertFollowUpRequest`, `AlertFollowUpMail` + `resources/views/mail/alert-follow-up.blade.php`, `AlertFollowUpController` | Queued mail, recipients = active admin / manager / team_leader of the "Who" department. Sent = read-only.                                       |
| Follow-up form UI                 | `resources/js/components/dashboard/FiveW2HFollowUpPanel.vue`                                                                                                                                                                                                                        | **Moves** to the new module (E10-07). Do not keep two copies.                                                                                   |
| Tests                             | `tests/Feature/AlertFollowUpTest.php` (9 tests), `tests/Browser/Dashboard/AlertFollowUpBrowserTest.php` (2 tests)                                                                                                                                                                   | Must be updated for the new routes (E10-07).                                                                                                    |

---

## Decisions Already Made (with the client / product owner)

| #   | Decision                                                                                                                                                                                                                              |
| --- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| D1  | A new **sidebar menu "Early Warning"** with an alert inbox + a detail page per alert.                                                                                                                                                 |
| D2  | Alerts are **detected automatically** from the daily index. Users do not create alerts manually.                                                                                                                                      |
| D3  | Visible to **Admin, Manager, Team Leader**. Non-admins only see their own department. Operators (`user`) do not see the menu.                                                                                                         |
| D4  | Vitals (production volume change, manpower change) use **real data**; show `N/A` where no data exists.                                                                                                                                |
| D5  | The follow-up is an **alert, not a plan**: "Simpan Draft" saves, "Kirim via Email" sends a queued email only. Recipients = active admin/manager/team_leader of the **Who** department. Status is `draft` or `sent`; sent = read-only. |
| D6  | "How much" (Rp and hours) is added to the client's 5W1H, making it **5W2H**.                                                                                                                                                          |
| D7  | The 3 zones are **Hijau / Kuning / Merah** with configurable limits (defaults 70% / 90%).                                                                                                                                             |
| D8  | The **Early Warning chart stays on the Dashboard**. The **Tindak Lanjut (5W2H) panel is removed from the Dashboard** and lives only in the new menu.                                                                                  |

### Open items to confirm with the client (do not block the sprint)

| #   | Question                                                                                                                                                                 | Default used until answered                         |
| --- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | --------------------------------------------------- |
| Q1  | Her example leaves 1.080–1.200 undefined and says "Merah > 1.200". Does Merah start above 90% or above 100%?                                                             | Merah above `yellow_max_pct` (90%), admin-editable. |
| Q2  | Her mockup says "Kirim ke Strategic Direction" and shows status "Menunggu Approval". We agreed on email to the "Who" department. Is an approval step still wanted later? | No approval step. Status = Draft / Terkirim.        |
| Q3  | Her mockup shows "Batas 60%" as a critical line. Is that a fixed value or the department's limit?                                                                        | Use the department's configured zone limits.        |
| Q4  | Which days break a streak? Weekends/holidays (HLR)?                                                                                                                      | HLR days are skipped and do not break a streak.     |
| Q5  | SMTP sender and queue worker for real email delivery (`.env` has `MAIL_MAILER=log`, `QUEUE_CONNECTION=database`).                                                        | Dev logs mail; production needs SMTP + worker.      |

---

## Navigation Footprint (hard constraint for implementation)

| Item            | Decision                                                                                                                                      |
| --------------- | --------------------------------------------------------------------------------------------------------------------------------------------- |
| Sidebar items   | **1** — "Early Warning" in group **Operations & Overtime**, after "Employee Reports". `testId: nav-early-warning`. Icon: `BellRing` (lucide). |
| Routes / pages  | **2** — `GET /early-warning` (inbox) and `GET /early-warning/{section}/{date}` (alert detail). No other pages.                                |
| Not allowed     | No separate "history", "settings", or "report" pages. History is a filter on the inbox. Zone limits stay in Administration.                   |
| Backend-only    | Alert detection, streak calculation, vitals, recipients resolution — no dedicated UI.                                                         |
| Roles           | `role:admin,manager,team_leader`. Non-admins scoped to their own department (server-side, never trusted from the client).                     |
| Max click depth | Sidebar → inbox (1) → alert detail (2) → Save Draft / Send (3).                                                                               |

---

## User Stories

---

### Story E10-01: Early Warning Alert Detection Service

**As the** system,  
**I want** to derive alerts automatically from the daily index of every section,  
**So that** users never have to hunt for problems manually.

**Story Points:** 5  
**Priority:** Must Have  
**Status:** 🔴 Not Started

#### Definition of an alert

- One alert = **one section on one date** whose daily index is in zone **Kuning** or **Merah** (not Hijau).
- Daily index = the same formula as the Dashboard's Early Warning chart (daily consumption vs. daily pacing target, `CALC-02` applied per day) — **must not diverge** from `getDailyIndexTrend`.
- Zone = `PolicyThresholdService::classifyWith()` using the **section's department** boundaries (department override, else plant default).
- **Severity** = zone of the alert date (Merah > Kuning).
- **Streak (hari berturut-turut)** = number of consecutive **working days** (HKN, from `operational_calendar`) ending on the alert date whose zone is Kuning or Merah. HLR days are skipped and do not break the streak (Q4).
- A section with no configured budget produces **no alert** (not a false Hijau/Merah).

#### Acceptance Criteria

- [ ] Service returns alerts for a date range, optionally filtered by department, section, and zone
- [ ] Each alert exposes: `section_id`, `section_name`, `department_id`, `department_name`, `alert_date`, `index_pct`, `zone`, `streak_days`, `green_max_pct`, `yellow_max_pct`, `follow_up_status` (`none | draft | sent`), `follow_up_id` (latest)
- [ ] Non-admin users only receive alerts of their own department (server-side)
- [ ] Hijau days never appear as alerts
- [ ] A section's streak resets on a Hijau working day and is unaffected by HLR days
- [ ] Sections without a budget are excluded
- [ ] Alerts are sorted: Merah first, then Kuning; then by `streak_days` desc, then date desc
- [ ] Computing alerts for the whole plant for a 31-day range runs in a **constant number of queries** (no per-section × per-day queries)

#### Technical Tasks

- [ ] `php artisan make:class Services/EarlyWarningService` (pragmatic Service pattern, controllers ≤ 30 lines)
- [ ] `EarlyWarningService::alertsFor(User $user, Carbon $from, Carbon $to, ?int $departmentId, ?int $sectionId, ?BurnZone $zone): Collection`
- [ ] `EarlyWarningService::alertDetail(User $user, Section $section, Carbon $date): array` (single alert + 7-working-day series; reuse `getDailyIndexTrend` semantics)
- [ ] Reuse `DashboardKpiService::resolveScoping()` and `PolicyThresholdService`; extract the shared "daily index per section per day" calculation into one method used by **both** the Dashboard chart and this service
- [ ] Group by section/day in SQL with `SUM` + `GROUP BY` (MySQL/MariaDB-safe, `whereDate` on date columns; **no SQLite-only syntax**)
- [ ] Carbon dates use `Asia/Jakarta`
- [ ] Feature tests: zone classification with department override, streak across a weekend, Hijau resets the streak, no-budget section, scope isolation between departments, query count assertion

---

### Story E10-02: Sidebar Menu, Routes & Authorization

**As a** Manager, Team Leader, or Admin,  
**I want** an "Early Warning" item in the sidebar,  
**So that** I can reach alerts in one click.

**Story Points:** 3  
**Priority:** Must Have  
**Status:** 🔴 Not Started

#### Acceptance Criteria

- [ ] Sidebar shows **Early Warning** under "Operations & Overtime" for `admin`, `manager`, `team_leader` only
- [ ] The item is highlighted when on `/early-warning` or `/early-warning/*`
- [ ] Operators (`user`) get **403** on both routes, and do not see the menu
- [ ] A non-admin requesting a section of another department gets **403**
- [ ] Optional badge: count of **open Merah alerts** (alert date in the current month, no `sent` follow-up) visible on the menu, hidden when 0 and in collapsed (icon-only) sidebar mode shows a dot
- [ ] Indonesian and English labels via `__()`, keys added to both `lang/id.json` and `lang/en.json` (sorted, kept in sync)

#### Technical Tasks

- [ ] Routes in `routes/web.php`, group `middleware(['auth', 'role:admin,manager,team_leader'])`:
    - `GET /early-warning` → `EarlyWarningController@index` → name `early-warning.index`
    - `GET /early-warning/{section}/{date}` → `EarlyWarningController@show` → name `early-warning.show` (`date` format `Y-m-d`, validated)
- [ ] `php artisan make:controller EarlyWarningController` (thin; logic in `EarlyWarningService`)
- [ ] Add nav item in `resources/js/components/AppSidebar.vue` using the **Wayfinder** route function (never Ziggy `route()`), `testId: 'nav-early-warning'`
- [ ] `php artisan wayfinder:generate --with-form` (without `--with-form` `types:check` breaks)
- [ ] Share the open-alert count via `HandleInertiaRequests` as a lazy/cheap prop (single aggregate query, cached per request)
- [ ] Authorization via a policy/gate method reusing the department-scope rule from `AlertFollowUpPolicy`

---

### Story E10-03: Early Warning Inbox Page

**As a** Manager or Team Leader,  
**I want** a list of current alerts with their zone, streak, and follow-up status,  
**So that** I can see which sections need action and which are already handled.

**Story Points:** 8  
**Priority:** Must Have  
**Status:** 🔴 Not Started

Page: `resources/js/pages/early-warning/Index.vue` (route `early-warning.index`). Single root element.

#### Acceptance Criteria

- [ ] Filter bar: **Department** (admin: "Semua Department" + list; others: locked to own department), **Section** (depends on department), **Date range** (default: last 7 days up to today, `Asia/Jakarta`), **Zone** (Semua / Kuning / Merah), **Follow-up status** (Semua / Belum ditindaklanjuti / Draft / Terkirim)
- [ ] Filters are kept in the URL query string (shareable/reloadable) and applied server-side
- [ ] Summary strip (ISUZU KPI style, `font-mono tabular-nums`): total alerts, Merah count, Kuning count, **without follow-up** count
- [ ] Alert list/table: Section, Department, Date, **zone badge** (Hijau/Kuning/Merah styles from `BURN_ZONE_STYLES`), index % , **streak** ("3 hari berturut-turut"), follow-up status pill (Belum / Draft / Terkirim), and a "Tindak Lanjut" action
- [ ] Clicking a row (or the action) opens the **alert detail page** (E10-04)
- [ ] Default sort: Merah first, longest streak first
- [ ] Empty state when no alerts: positive message ("Tidak ada alert pada periode ini") and a hint to widen the date range
- [ ] Loading uses deferred props with a pulsing skeleton (Inertia v3 guideline)
- [ ] Pagination (or "load more") when more than 25 alerts
- [ ] Responsive: table on desktop, stacked cards on mobile (client mockup is mobile-first)
- [ ] ISUZU design: `#cc0000` for primary actions, tabular numbers, segregated CapEx/OpEx not applicable here

#### Technical Tasks

- [ ] `EarlyWarningController@index` passes `alerts` (deferred), `filters`, `summary`, `departments`, `sections`
- [ ] Create `resources/js/pages/early-warning/Index.vue`
- [ ] Create `resources/js/components/early-warning/AlertFilterBar.vue`
- [ ] Create `resources/js/components/early-warning/AlertSummaryStrip.vue`
- [ ] Create `resources/js/components/early-warning/AlertList.vue` (table + card layout)
- [ ] Create `resources/js/components/early-warning/AlertZoneBadge.vue` and `FollowUpStatusPill.vue` (shared with detail page)
- [ ] Use `useBurnZone()` / `BURN_ZONE_STYLES` — do not hardcode zone colors
- [ ] Browser test: admin sees alerts of two departments, a manager sees only their own, filtering by zone and status works, empty state renders

---

### Story E10-04: Alert Detail Page (Chart, Vitals & Status)

**As a** Manager or Team Leader,  
**I want** to open one alert and understand what is happening,  
**So that** I can decide on the right follow-up.

**Story Points:** 8  
**Priority:** Must Have  
**Status:** 🔴 Not Started

Page: `resources/js/pages/early-warning/Show.vue` (route `early-warning.show`). Single root element. Content mirrors the client's draft, adapted to the app's design.

#### Acceptance Criteria

- [ ] Header card: "Index Harian — {Section}", formatted date, **streak** ("3 hari berturut-turut"), zone badge (Merah = "Critical" style, Kuning = "Warning" style, per D7 labels Hijau/Kuning/Merah)
- [ ] **Burn bar**: actual index % filled bar with a marker at the department's limit(s) (green and yellow limits) and the "Target 100%" caption (Q3: limits come from configuration, not hardcoded 60%)
- [ ] **Vitals** (E10-05): "Volume produksi" and "Perubahan MP" with value, trend arrow, and period; `N/A` with tooltip when no data
- [ ] **7 working-day chart** of the daily index with Hijau/Kuning/Merah bands (reuse the band plugin logic of `DailyIndexTrendChart.vue`; extract a shared composable instead of duplicating)
- [ ] **5W2H follow-up form** (E10-06) below the chart, **prefilled** from the alert: What = "Index harian {Section} {index}% ({Zone})…", When = alert date + streak text, Where = section name, Who = department (editable)
- [ ] **Status block**: "Tindak lanjut" with pill Draft / Terkirim and, when sent, sent time + sender
- [ ] **History** of earlier follow-ups for the same alert (chips), switchable; "Follow-up baru" button when the latest is sent
- [ ] Back link to the inbox preserves the inbox filters (use the previous URL / query string)
- [ ] 404 for unknown section, 422 → redirect for an invalid date, 403 for another department
- [ ] If the date is **no longer an alert** (zone Hijau now), the page still opens but shows a neutral "Tidak ada alert pada tanggal ini" banner and disables the form (cannot create a follow-up for a non-alert)

#### Technical Tasks

- [ ] `EarlyWarningController@show` → `Inertia::render('early-warning/Show', [...])` with `alert`, `series`, `vitals`, `followUps`, `departments`
- [ ] Create `resources/js/pages/early-warning/Show.vue`
- [ ] Create `resources/js/components/early-warning/AlertHeaderCard.vue`, `AlertBurnBar.vue`, `AlertVitalsGrid.vue`, `AlertIndexChart.vue`
- [ ] Extract the zone-band chart plugin from `DailyIndexTrendChart.vue` into `resources/js/composables/useZoneBandsPlugin.ts` and use it in both charts
- [ ] Browser test: open an alert from the inbox, see header, bar, vitals, chart, and prefilled What/Where; non-alert date shows the neutral banner

---

### Story E10-05: Alert Vitals — Production Volume & Manpower Change

**As a** Manager,  
**I want** to see whether production volume or manpower changed around the alert,  
**So that** the "Why" of the alert is easier to fill in.

**Story Points:** 5  
**Priority:** Should Have  
**Status:** 🔴 Not Started

#### Data reality (important)

- **Production volume** exists only **per month** (`ml_training_data.production_volume`, unit/bulan; the Dashboard still shows "Integrasi data produksi ERP belum…" for daily data). There is **no daily/weekly volume**, so the client's "+20% in the last 7 days" **cannot be computed exactly**.
- **Manpower** can be derived from active employees per section (`employees`, `is_active`, `section_id`) and ml `man_power` per month.

#### Acceptance Criteria

- [ ] **Volume change %** = alert month vs. previous month (`production_volume`), labelled with its real period ("vs Agustus 2026"), **not** labelled "7 hari terakhir"
- [ ] **MP change %** = change in active employees of the alert's section between the alert date and 7 days earlier (use hire/termination dates if available; otherwise monthly `man_power`), labelled with the real period
- [ ] When a source is missing, show `N/A` and a tooltip explaining why ("Data volume produksi bulan ini belum tersedia")
- [ ] A volume increase with flat/negative MP surfaces a **hint** (not a rule): "Volume naik, MP tidak bertambah — kemungkinan penyebab" and can pre-fill the **Why** field suggestion (user can edit)
- [ ] Division by zero (previous value 0) returns `null`, shown as `N/A`, never `Infinity`/`NaN`
- [ ] No fabricated numbers: never default to `+20%` or any dummy

#### Technical Tasks

- [ ] `EarlyWarningService::vitalsFor(Section $section, Carbon $date): array` returning `volume_change_pct`, `volume_period_label`, `manpower_change_pct`, `manpower_period_label`, `suggested_why`
- [ ] Read `MlTrainingData` by `year`/`month`; read employees via Eloquent (`whereDate` on date columns)
- [ ] Unit tests: both available, volume missing, MP previous = 0, decrease, suggested-why only when volume ↑ and MP ≤ 0
- [ ] Flag in the epic notes to the client: "daily volume needs the ERP integration" (see Risks)

---

### Story E10-06: 5W2H Follow-Up Linked to an Alert

**As a** Manager or Team Leader,  
**I want** to save a draft or send the follow-up of a specific alert by email,  
**So that** the PIC department is informed and the action is on record.

**Story Points:** 5  
**Priority:** Must Have  
**Status:** 🔴 Not Started

The 5W2H behavior is already implemented at the month level (`AlertFollowUpService`). This story **links it to a concrete alert (section + date)** and moves its UI and routes into the new module.

#### Acceptance Criteria

- [ ] Follow-up belongs to an **alert**: `section_id` + `alert_date` (new columns), plus the existing `department_id`, `fiscal_year`, `fiscal_month`
- [ ] **Zone and index are recomputed server-side** from the alert date at save time (client values ignored); the snapshot is stored (`zone`, `index_pct`, new `streak_days`)
- [ ] A follow-up can only be created for a date that **is an alert** (Kuning/Merah); otherwise 422 with a clear message
- [ ] **Simpan Draft**: requires only **What**; can be re-saved/edited any time while `draft`
- [ ] **Kirim via Email**: requires What, Why, When, Who, How; needs at least one active recipient in the Who department, otherwise a validation error and **nothing is saved/sent** (transaction rolls back)
- [ ] On send: status → `sent`, `sent_by`, `sent_at` set, one queued mail per recipient (`Mail::to($r)->queue(...)`), toast "Follow-up sent by email to the PIC department."
- [ ] A **sent follow-up is read-only** (form disabled, update → 403)
- [ ] At most **one draft per alert** at a time; many sent follow-ups allowed (history). "Follow-up baru" creates a new draft
- [ ] The email contains: section, date, zone, index %, streak, the 5W2H content, how much (Rp and hours), sender, and a link to the alert detail page (Wayfinder URL helper server-side `route('early-warning.show', …)` is acceptable in PHP)
- [ ] Authorization: admin all; others own department only; operators none
- [ ] Rupiah formatting in UI and email (`Rp 7.500.000`), timezone WIB

#### Technical Tasks

- [ ] Migration: add `alert_date` (date, nullable for legacy rows), `streak_days` (unsigned small int, default 0); index `(section_id, alert_date)`; backfill legacy rows with `alert_date = created_at date` (migration branches on driver only for DDL if needed)
- [ ] Update `AlertFollowUp` model, factory (`->forAlert($section, $date)` state), `AlertFollowUpService::save()` / `listForScope()` → `listForAlert(Section, date)`
- [ ] Update `StoreAlertFollowUpRequest` / `UpdateAlertFollowUpRequest`: add `section_id` + `alert_date` required, validate alert existence via `EarlyWarningService`
- [ ] Routes: `POST /early-warning/follow-ups` → `early-warning.follow-ups.store`, `PUT /early-warning/follow-ups/{alertFollowUp}` → `early-warning.follow-ups.update` (replace the old `dashboard.alert-follow-ups.*`)
- [ ] Move `FiveW2HFollowUpPanel.vue` → `resources/js/components/early-warning/FollowUpForm.vue`; use Wayfinder actions; keep the existing data-test ids (`followup-*`) so tests keep working
- [ ] Update `AlertFollowUpMail` + `alert-follow-up.blade.php` with streak and the detail link
- [ ] Inertia partial reload `only: ['followUps','flash']`; after **send**, keep the sent item on screen read-only as confirmation (already implemented behavior — preserve it)
- [ ] Re-use existing tests and extend: alert-date validation, one-draft-per-alert, non-alert date rejected, mail contains streak and link

---

### Story E10-07: Dashboard Cleanup & Entry Link

**As a** Dashboard user,  
**I want** the Early Warning chart to stay on the Dashboard and lead me to the new menu,  
**So that** there is a single place to follow up an alert.

**Story Points:** 3  
**Priority:** Must Have  
**Status:** 🔴 Not Started

#### Acceptance Criteria

- [ ] The **Tindak Lanjut / Alert Follow-up (5W2H) panel is removed** from the Dashboard (`Dashboard.vue`)
- [ ] The **Early Warning chart (`DailyIndexTrendChart.vue`) stays** unchanged on the Dashboard ("Distribution & Trends" tab)
- [ ] A **"Buka Early Warning" / "Tindak Lanjut"** button on the chart header opens the new inbox filtered by the dashboard's current department/section and date; visible only for roles that can access the menu
- [ ] When the chart's average zone is Hijau, the button is still shown but the inbox will be empty-state ("Tidak ada alert")
- [ ] No leftover code: `alertFollowUps` prop, the `only: [...]` entry, `followUpScopeLabel`, imports, old routes `dashboard.alert-follow-ups.*`, and `DashboardController` follow-up loading are removed
- [ ] i18n keys used only by the removed panel are moved (not duplicated) to the new module namespace

#### Technical Tasks

- [ ] Edit `resources/js/pages/Dashboard.vue`: remove `<FiveW2HFollowUpPanel>`, prop, partial-reload key, computed, imports
- [ ] Edit `app/Http/Controllers/DashboardController.php`: stop passing `alertFollowUps`; drop unused `AlertFollowUpService` dependency
- [ ] Remove old `dashboard.alert-follow-ups.*` routes (they are replaced in E10-06); regenerate Wayfinder with `--with-form`
- [ ] Add the entry link in `DailyIndexTrendChart.vue` header (Wayfinder `early-warning.index` with query params)
- [ ] Update `tests/Feature/AlertFollowUpTest.php` (dashboard no longer exposes `alertFollowUps`; new route names) and `tests/Browser/Dashboard/AlertFollowUpBrowserTest.php` (move to `tests/Browser/EarlyWarning/`)
- [ ] Do **not** delete tests without approval — update/move them
- [ ] Browser test: the Dashboard no longer shows `followup-panel`, the link navigates to `/early-warning` with filters applied

---

## Sprint 11 Breakdown

| Sprint Day | Focus                                                    | Stories | Status         |
| ---------- | -------------------------------------------------------- | ------- | -------------- |
| Day 1–2    | Alert detection service + shared daily-index calculation | E10-01  | 🔴 Not Started |
| Day 3      | Sidebar menu, routes, authorization                      | E10-02  | 🔴 Not Started |
| Day 3–5    | Inbox page                                               | E10-03  | 🔴 Not Started |
| Day 5–6    | Vitals service                                           | E10-05  | 🔴 Not Started |
| Day 6–8    | Alert detail page                                        | E10-04  | 🔴 Not Started |
| Day 8–9    | Follow-up linked to alert, mail, route move              | E10-06  | 🔴 Not Started |
| Day 9–10   | Dashboard cleanup, test pass, docs, UAT with the client  | E10-07  | 🔴 Not Started |

---

## Backend Endpoints Summary

| Method | Route                                       | Name                              | Controller / Action              | Purpose                            |
| ------ | ------------------------------------------- | --------------------------------- | -------------------------------- | ---------------------------------- |
| GET    | `/early-warning`                            | `early-warning.index`             | `EarlyWarningController@index`   | Alert inbox                        |
| GET    | `/early-warning/{section}/{date}`           | `early-warning.show`              | `EarlyWarningController@show`    | Alert detail                       |
| POST   | `/early-warning/follow-ups`                 | `early-warning.follow-ups.store`  | `AlertFollowUpController@store`  | Save draft / send new follow-up    |
| PUT    | `/early-warning/follow-ups/{alertFollowUp}` | `early-warning.follow-ups.update` | `AlertFollowUpController@update` | Update draft / send existing draft |

---

## Vue Component Inventory

| Component                                           | Used by            | Story  |
| --------------------------------------------------- | ------------------ | ------ |
| `pages/early-warning/Index.vue`                     | Inbox              | E10-03 |
| `pages/early-warning/Show.vue`                      | Alert detail       | E10-04 |
| `components/early-warning/AlertFilterBar.vue`       | Inbox              | E10-03 |
| `components/early-warning/AlertSummaryStrip.vue`    | Inbox              | E10-03 |
| `components/early-warning/AlertList.vue`            | Inbox              | E10-03 |
| `components/early-warning/AlertZoneBadge.vue`       | Inbox, detail      | E10-03 |
| `components/early-warning/FollowUpStatusPill.vue`   | Inbox, detail      | E10-03 |
| `components/early-warning/AlertHeaderCard.vue`      | Detail             | E10-04 |
| `components/early-warning/AlertBurnBar.vue`         | Detail             | E10-04 |
| `components/early-warning/AlertVitalsGrid.vue`      | Detail             | E10-04 |
| `components/early-warning/AlertIndexChart.vue`      | Detail             | E10-04 |
| `components/early-warning/FollowUpForm.vue` (moved) | Detail             | E10-06 |
| `composables/useZoneBandsPlugin.ts` (extracted)     | Detail + Dashboard | E10-04 |

---

## Performance Requirements

| Requirement                      | Target                               |
| -------------------------------- | ------------------------------------ |
| Inbox, whole plant, 31-day range | Constant query count; < 1.5 s server |
| Alert detail                     | < 800 ms server                      |
| Sidebar open-alert badge         | One aggregate query, no N+1          |

---

## Key Business Rules Implemented in This Epic

| Rule                        | Implementation                                                                                                  |
| --------------------------- | --------------------------------------------------------------------------------------------------------------- |
| 3-zone tolerance (D7)       | Hijau ≤ `green_max_pct`, Kuning ≤ `yellow_max_pct`, Merah above — per department, from `PolicyThresholdService` |
| Alert = Kuning or Merah day | `EarlyWarningService::alertsFor()`; Hijau never an alert; sections without budget excluded                      |
| Streak                      | Consecutive HKN working days in Kuning/Merah; HLR skipped                                                       |
| Server-side truth           | Zone/index/streak recomputed on save; client values are display-only                                            |
| Alert, not planning (D5)    | Draft saves; send = queued email only; sent = read-only                                                         |
| Scope isolation             | Non-admins limited to their own department on every route and query                                             |

---

## Risks & Assumptions

| Risk                                                                                                         | Likelihood | Mitigation                                                                                            |
| ------------------------------------------------------------------------------------------------------------ | ---------- | ----------------------------------------------------------------------------------------------------- |
| No daily production volume (ERP integration pending); client expects "+20% in 7 days"                        | High       | Show monthly volume change with its real label, `N/A` otherwise; raise to client as a data dependency |
| Q1: Merah threshold (90% vs 100%) is ambiguous in the client's example                                       | High       | Configurable per department; default 90%; confirm at UAT                                              |
| Per-section × per-day index could be slow if computed naively                                                | Medium     | Single grouped query + batch method; query-count test                                                 |
| Alert-flooding: many Kuning sections each day                                                                | Medium     | Inbox filters + sorting + "without follow-up" counter; no push notifications in this epic             |
| Emails not delivered (`MAIL_MAILER=log`, queue worker not running)                                           | Medium     | Document required SMTP + `queue:work`; mail rendering is tested                                       |
| Moving routes/components breaks existing tests                                                               | Medium     | E10-07 updates and moves tests (never delete without approval); full suite run in the quality gate    |
| Pre-existing seed/environment test failures (4 in `Admin/DashboardBurnIndexDepartmentSummary`, locale tests) | Known      | Not caused by this epic; report them separately                                                       |

---

## Out of Scope

- Approval workflow ("Menunggu Approval", "Strategic Direction") — pending Q2
- In-app notifications, push, or WhatsApp delivery
- Editing/deleting a sent follow-up
- Alert rules beyond the daily index (welfare/fatigue alerts keep their own `alert_level`)
- Daily production volume integration (ERP)
- Exporting alerts to Excel/PDF

---

## Definition of Done — Epic-10

- [ ] Sidebar shows "Early Warning" for admin / manager / team_leader only; operators get 403
- [ ] Inbox lists alerts with correct zone, streak, and follow-up status; filters work and persist in the URL
- [ ] Alert detail shows header, burn bar, vitals (or `N/A`), 7-day chart, and the prefilled 5W2H form
- [ ] Draft saves; send queues one mail per recipient; sent follow-up is read-only; no recipients = nothing saved
- [ ] Zone, index, and streak are recomputed server-side; scope isolation verified between departments
- [ ] The Dashboard no longer contains the Tindak Lanjut panel; the Early Warning chart stays and links to the new menu
- [ ] No Ziggy `route()` in Vue code; Wayfinder generated with `--with-form`
- [ ] All new strings in `lang/id.json` and `lang/en.json`; no hardcoded timezone/currency
- [ ] MySQL/MariaDB-safe SQL only; `whereDate` on date columns
- [ ] Feature + unit tests for service, vitals, follow-up rules; browser tests under `tests/Browser/EarlyWarning/` (Pest + Playwright)
- [ ] `vendor/bin/pint --dirty --format agent`, `pnpm lint`, `pnpm types:check`, `pnpm build` pass
- [ ] Full suite run; any failures classified as pre-existing vs. regression
- [ ] Client UAT: Q1–Q5 answered or defaults accepted
