# Epic-07: CapEx Project Labor Management

**Epic ID:** E-07  
**Priority:** P2 – Should Have  
**Estimated Total:** 35 Story Points  
**Target Sprints:** Sprint 6–7 (Weeks 11–14, partially parallel with Epic-06)  
**Dependencies:** Epic-01, Epic-02 (Departments exist), Epic-03 (Project hours logged), Epic-04 (Approved items exist), Epic-05 (Burn Index infrastructure)  
**Lead Area:** Backend (`CapexProjectController`, `CapExAccountingService`) + Vue 3 CapEx Project Pages

---

## Business Context

CapEx labor is a distinct financial category. When engineers spend overtime building a new assembly line, installing machinery, or fabricating tooling, those hours don't go to operational cost (P&L expense) — they are **capitalized** onto a fixed asset's cost basis. This has direct implications for:

- **Tax depreciation schedules** — an asset's capitalizable cost must include direct labor
- **Financial audit compliance** — external auditors will verify that capitalized hours are traceable to formal project codes
- **Project manager oversight** — capital projects routinely overrun their labor budgets when physical progress lags behind burn velocity

This epic builds the CapEx project master data management and monitoring system. The `capex_project_id` link on `overtime_items` (enforced by the DB constraint in Epic-01) provides the raw data; this epic surfaces it as actionable project management intelligence.

Business Rule **BR-08** is the foundation: any overtime hour categorized as "Project" MUST reference a valid, active CapEx project code. This traceability is non-negotiable for financial audit.

---

## User Stories

---

### Story E07-01: CapEx Project Master Data Management (Admin/Manager)

**As an** Admin or Manager,  
**I want** to create and manage CapEx project records with budget allocations and milestone tracking,  
**So that** Team Leaders can attribute "Project" overtime hours to the correct capital project and the system can track labor burn against each project's allocated budget.

**Story Points:** 8  
**Priority:** Must Have

#### Acceptance Criteria

- [ ] Admin and Manager can create a CapEx project with:
    - `project_code` (unique, e.g., `CPX-2026-ASSY-001`)
    - `asset_code` (nullable, fixed asset tag reference for accounting)
    - `name` (descriptive project name)
    - `department_id` (which department owns this project)
    - `allocated_labor_hours` (planned capitalized labor hours)
    - `allocated_labor_budget_idr` (Rp value of the labor allocation)
    - `start_date` and `target_end_date`
    - `status`: `PLANNING | ACTIVE | ON_HOLD | COMPLETED | CLOSED`
- [ ] `project_code` is immutable after creation (same invariant as NPK)
- [ ] Only `ACTIVE` projects appear in the Team Leader's "Project" dropdown on the overtime form
- [ ] Admin can transition project status: `PLANNING → ACTIVE`, `ACTIVE → ON_HOLD / COMPLETED`, `COMPLETED → CLOSED`
- [ ] Once `CLOSED`, no new overtime items can be attributed to this project (enforced at `StoreOvertimeSubmissionRequest` level)
- [ ] Project list page: searchable, filterable by status and department
- [ ] Project detail page: shows basic info + labor burn summary (from E07-02)

#### Technical Tasks

- [ ] `php artisan make:controller Admin/CapexProjectController --resource`
- [ ] `php artisan make:request StoreCapexProjectRequest` + `UpdateCapexProjectRequest`
- [ ] `StoreOvertimeSubmissionRequest`: when `hours_project > 0`, validate `capex_project_id` exists AND `capex_projects.status = 'ACTIVE'`
- [ ] Route: `resources/admin/capex-projects` registered in `routes/web.php` with `->middleware(['auth', 'role:admin,manager'])`
- [ ] `project_code` immutability: exclude from `UpdateCapexProjectRequest` fillable, return 422 if client sends it
- [ ] Create `resources/js/Pages/Admin/CapexProjects/Index.vue` — searchable table with status filter chips
- [ ] Create `resources/js/Pages/Admin/CapexProjects/Form.vue` — create/edit modal
- [ ] Create `resources/js/Pages/Admin/CapexProjects/Show.vue` — project detail page (skeleton, E07-02 fills the content)
- [ ] Status transition: PATCH `/admin/capex-projects/{id}/status` → `CapexProjectController@updateStatus`

---

### Story E07-02: CapEx Project Labor Burn Tracking Dashboard

**As a** Manager or CapEx Project Manager,  
**I want** to see how much overtime labor has been consumed against each CapEx project's allocated budget,  
**So that** I can identify projects that are at risk of exceeding their capitalized labor allowance before it happens.

**Story Points:** 10  
**Priority:** Must Have

#### Acceptance Criteria

- [ ] CapEx project detail page shows:
    - `Allocated Labor Hours` vs `Consumed Labor Hours` (from approved "Project" items)
    - `Allocated Budget (Rp)` vs `Consumed Labor Cost (Rp)` (sum of `total_cost_snapshot` where `capex_project_id = X`)
    - **CapEx Burn Index** = `(Consumed Hours / Allocated Hours) × 100%` — same formula as section Burn Index, applied to project
    - **Physical Progress %** (`capex_projects.physical_progress_pct`) — editable by Manager
    - **Milestone Burn Ratio** = `CapEx Burn Index / Physical Progress %` — if > 1.0, labor is burning faster than physical progress (warning signal)
    - Labor hours timeline chart: cumulative hours by week
    - Team composition: list of employees who contributed hours, sorted by hours descending
- [ ] Manager can update `physical_progress_pct` (0–100) directly from the project detail page
- [ ] Warning badge shown if `Milestone Burn Ratio > 1.2`: `⚠️ Labor consuming faster than project progress`
- [ ] Alert notification sent to Project Manager when CapEx Burn Index > 80% (configurable)
- [ ] Zero hours case: if no hours logged yet, show "No labor recorded — project is in allocation phase"

#### Technical Tasks

- [ ] `CapExAccountingService::getProjectLaborMetrics(int $projectId): array`
- [ ] Returns: `allocated_hours`, `consumed_hours`, `remaining_hours`, `burn_index_pct`, `physical_progress_pct`, `milestone_burn_ratio`, `consumed_cost_idr`, `top_contributors`
- [ ] Consumed hours query: `SUM(overtime_items.total_hours)` + `SUM(overtime_items.hours_project)` where `capex_project_id = X` and `status = 'APPROVED'`
- [ ] Team composition query: group by `employee_id`, sum hours, join employees for name
- [ ] PATCH `/admin/capex-projects/{id}/progress` → `CapexProjectController@updateProgress`
- [ ] `php artisan make:notification CapexBurnAlertNotification`
- [ ] Create `resources/js/Pages/Admin/CapexProjects/Show.vue` (content layer from E07-01 skeleton)
- [ ] Create `resources/js/Components/CapEx/CapexBurnIndexPanel.vue` — summary cards
- [ ] Create `resources/js/Components/CapEx/CapexLaborTimelineChart.vue` — cumulative hours by week (vue-chartjs)
- [ ] Create `resources/js/Components/CapEx/CapexTeamContributionTable.vue`

---

### Story E07-03: Multi-Project Portfolio Overview (Manager/Admin)

**As a** Department Manager,  
**I want** a consolidated view of all CapEx projects in my department with their labor burn status,  
**So that** I can prioritize which projects need attention during my weekly management review.

**Story Points:** 5  
**Priority:** Should Have

#### Acceptance Criteria

- [ ] Portfolio page lists all CapEx projects for the Manager's department
- [ ] Table columns: `Project Code`, `Name`, `Status`, `Allocated Hours`, `Consumed Hours`, `Burn Index %`, `Physical Progress %`, `Milestone Burn Ratio`, `Target End Date`, `Days Remaining`
- [ ] Color-coded rows by CapEx Burn Index: same threshold scheme as section Burn Index
- [ ] Flagged column: `⚠️` if `Milestone Burn Ratio > 1.2` or `CapEx Burn Index > 90%`
- [ ] Summary header: "Dept Total: 8 Active Projects | 1,245 / 2,800 hrs consumed (44.5%)"
- [ ] Filter by: status, date range
- [ ] Admin sees cross-department portfolio with department column

#### Technical Tasks

- [ ] `CapexProjectController@portfolio` — `GET /reports/capex-projects/portfolio`
- [ ] Query: joins `capex_projects` with aggregated approved item hours per project
- [ ] Create `resources/js/Pages/Reports/CapexPortfolio.vue`
- [ ] Create `resources/js/Components/CapEx/CapexPortfolioTable.vue` — sortable, color-coded

---

### Story E07-04: CapEx Project Labor Attribution Report (Finance)

**As an** Admin or Finance Controller,  
**I want** a detailed labor attribution report showing which employees worked on which CapEx project, when, and at what cost,  
**So that** I can produce the capitalization schedule required by the accounting/tax team.

**Story Points:** 8  
**Priority:** Should Have

#### Acceptance Criteria

- [ ] Detailed report: one row per overtime item with `capex_project_id` not null
- [ ] Report columns: `Project Code`, `Project Name`, `Asset Code`, `Date`, `Employee NPK`, `Employee Name`, `Hours`, `Hourly Rate Snapshot (Rp)`, `Cost (Rp)`, `Submission Code`, `Approval Date`, `Approved By`
- [ ] Filterable by: project code, department, date range
- [ ] Totals row: total hours + total cost per project at bottom of each project group
- [ ] Grand total row at report bottom
- [ ] Export to Excel (`.xlsx`) with the above format — suitable for direct submission to accounting
- [ ] Report only includes `APPROVED` items (status = 'APPROVED')
- [ ] Rate snapshot and cost are the **immutable values** stored at submission time — no recalculation

#### Technical Tasks

- [ ] `CapexLaborReportController@index` — `GET /reports/capex-labor`
- [ ] Query: `overtime_items` joined to `overtime_submissions`, `employees`, `capex_projects`, `users (reviewer)` where `capex_project_id IS NOT NULL AND status = 'APPROVED'`
- [ ] Create `resources/js/Pages/Reports/CapexLaborReport.vue` — filterable report table
- [ ] `CapexLaborExport` implements `FromQuery`, `WithHeadings`, `WithGrouping` from `maatwebsite/excel`
- [ ] Export route: `GET /reports/capex-labor/export`

---

### Story E07-05: CapEx Project Physical Progress Update (Manager)

**As a** Project Manager,  
**I want** to update the physical completion percentage of a CapEx project,  
**So that** the system can calculate the Milestone Burn Ratio and alert me if labor is running ahead of physical work.

**Story Points:** 2  
**Priority:** Must Have

#### Acceptance Criteria

- [ ] Manager can update `physical_progress_pct` (0.0 to 100.0) directly from the project detail page
- [ ] Updating `physical_progress_pct` **does not require a full page reload** — in-place edit with instant save
- [ ] Each update to `physical_progress_pct` is logged in `overtime_item_audits` (or a `capex_project_audits` table): `{ action: 'PROGRESS_UPDATE', previous_pct: X, new_pct: Y, actor_user_id: Z }`
- [ ] When `physical_progress_pct` reaches 100%, a confirmation prompt: "Mark this project as COMPLETED?" — Manager can choose Yes or Later
- [ ] Milestone Burn Ratio re-evaluates immediately after update

#### Technical Tasks

- [ ] `PATCH /admin/capex-projects/{id}/progress` → `CapexProjectController@updateProgress`
- [ ] Request: `{ physical_progress_pct: 0-100 }` with validation `between:0,100`
- [ ] Inline edit: Vue `<InlineEditableField>` component — shows current value as text, click to edit in-place
- [ ] Audit log: write to `overtime_item_audits` or dedicated `capex_project_logs` table with action `PROGRESS_UPDATE`
- [ ] Reactive milestone ratio update: Inertia `router.patch(...)` + partial reload of project data

---

## Sprint 6–7 Schedule (Partial Sprint 6, Full Sprint 7)

| Sprint Day         | Focus                                              | Stories        |
| ------------------ | -------------------------------------------------- | -------------- |
| Sprint 6, Day 6–7  | CapEx project master data management               | E07-01         |
| Sprint 6, Day 8–10 | CapEx project detail dashboard + physical progress | E07-02, E07-05 |
| Sprint 7, Day 1–3  | Multi-project portfolio overview                   | E07-03         |
| Sprint 7, Day 4–6  | CapEx labor attribution report + Excel export      | E07-04         |

---

## Key Business Rules Implemented in This Epic

| Rule                             | Implementation                                                                                                                        |
| -------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------- |
| BR-08: CapEx Project Attribution | `StoreOvertimeSubmissionRequest`: validates `capex_project_id` not null when `hours_project > 0`, and project status must be `ACTIVE` |
| CapEx Burn Index                 | Same formula as section Burn Index applied per project: `(Consumed Hours / Allocated Hours) × 100%`                                   |
| Immutable cost snapshots         | `CapexLaborAttributionReport` always uses `total_cost_snapshot` from DB, never recalculates                                           |

---

## Risks & Assumptions

| Risk                                                                | Likelihood | Mitigation                                                                                                                |
| ------------------------------------------------------------------- | ---------- | ------------------------------------------------------------------------------------------------------------------------- |
| Project code naming convention not standardized by client           | High       | Enforce format with regex validation in `StoreCapexProjectRequest` (e.g., `CPX-YYYY-*`) — discuss with client in Sprint 2 |
| CapEx Burn Alert notification flooding on multi-project departments | Low        | Per-project, per-month deduplication — store `burn_alerted_at` on `capex_projects`                                        |
| `physical_progress_pct` manually entered incorrectly                | Medium     | Input capped 0–100, previous value shown in audit, Manager can correct it                                                 |

---

## Definition of Done — Epic-07

- [ ] CapEx projects can be created, managed, and status-transitioned
- [ ] Only ACTIVE projects appear in the overtime form's project dropdown
- [ ] Project detail shows correct burn index and milestone burn ratio
- [ ] Portfolio table shows all active CapEx projects with burn status
- [ ] Labor attribution report exports correct Excel with immutable cost snapshots
- [ ] Physical progress update is logged in audit trail
- [ ] `pnpm lint` passes
- [ ] `pnpm build` succeeds
