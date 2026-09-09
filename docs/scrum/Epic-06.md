# Epic-06: Individual Employee Reporting & Welfare Tracking

**Epic ID:** E-06  
**Priority:** P2 – Should Have  
**Estimated Total:** 30 Story Points  
**Target Sprints:** Sprint 6 (Weeks 11–12, parallel with Epic-07)  
**Status:** 🟢 Completed (30/30 SP completed)  
**Dependencies:** Epic-01, Epic-02 (Employee master data), Epic-03 (Submissions exist), Epic-04 (Approved items exist)  
**Lead Area:** Backend (`EmployeeReportController`, `BurnIndexCalculatorService` extension) + Vue 3 Report Pages

---

## Business Context

In a manufacturing plant, it is easy for workload to become unevenly distributed — some employees get called in every weekend while others rarely work overtime. This creates both fairness issues and safety risks (fatigue, industrial accidents). The Individual Employee Report module (based on the `ReportIndividu` legacy screen) gives supervisors and employees themselves visibility into their own overtime patterns, benchmarked against their section peers.

This module serves four audiences:

1. **The Employee** — sees their own hours, understands their workload.
2. **The Team Leader** — spots overloaded workers before they become a safety concern.
3. **The Manager** — identifies section-level workload distribution imbalances.
4. **HR / Safety** — uses the high-workload alerts to comply with company welfare policies.

---

## User Stories

---

### Story E06-01: Individual Employee Dossier Lookup

**As a** Team Leader or Manager,  
**I want** to look up any employee in my section/department by NPK or name and view their complete overtime profile,  
**So that** I can make informed scheduling decisions and flag welfare concerns.

**Story Points:** 5  
**Priority:** Must Have  
**Status:** 🟢 Completed (Sprint 6)

#### Acceptance Criteria

- [x] Search input supports: NPK (exact or partial), full name (partial, case-insensitive)
- [x] Search results appear as a dropdown after 3+ characters (debounced, 300ms)
- [x] Manager sees employees from their department; Team Leader sees employees from their section only; Admin sees all
- [x] Clicking a result navigates to the employee's dossier page
- [x] Dossier page header shows: full name, NPK, department, section, job position, `is_active` badge
- [x] "Recent lookups" (last 5 employees the user viewed) shown below the search for quick return access — stored in localStorage, not DB

#### Technical Tasks

- [x] `EmployeeReportController@search` — `GET /reports/employees/search?q=...` — scoped by auth user's department/section
- [x] `EmployeeReportController@show` — `GET /reports/employees/{npk}` — returns full dossier data
- [x] Route authorization: Manager or Team Leader can only query their own department/section employees
- [x] Create `resources/js/pages/reports/EmployeeDossier.vue` — main employee report page (unified dossier hub per UX Plan)
- [x] Create `resources/js/components/reports/EmployeeSearch.vue` — search-as-you-type input with dropdown
- [x] `useRecentLookups()` composable — reads/writes `localStorage.getItem('recentLookups')` array

---

### Story E06-02: Employee Personal Overtime Dashboard

**As an** Employee (User role) or Team Leader,  
**I want** to view my own overtime hours summary for the current month and year-to-date,  
**So that** I can track my own workload and plan my personal schedule around upcoming shifts.

**Story Points:** 8  
**Priority:** Must Have  
**Status:** 🟢 Completed (Sprint 6)

#### Acceptance Criteria

- [x] KPI cards displayed at the top of the dossier:
    - `Current Month Hours` (total approved hours this month)
    - `Year-to-Date Hours` (total approved hours this calendar year)
    - `Individual Burn Index %` (individual actual hours / individual planned hours from section budget allocation — or null if not individually budgeted)
    - `Departmental Ranking` (e.g., "Ranked 7th out of 28 employees in section by hours this month")
- [x] Hours breakdown donut chart: Production | TPM | Project (CapEx) | Others
- [x] Day-type breakdown: HKN hours vs HLR hours (bar chart or horizontal split)
- [x] All monetary amounts shown in Rupiah: `Total Estimated Cost: Rp 1.234.567` (sum of `total_cost_snapshot` for approved items)
- [x] "Employee" (User role) only sees their own dossier — they cannot look up other employees' data
- [x] Data computed from `overtime_items` with `status = 'APPROVED'` joined to `overtime_submissions`

#### Technical Tasks

- [x] `EmployeeReportService::getSummary(int $employeeId, int $year, int $month): array`
- [x] Returns: `current_month_hours`, `ytd_hours`, `burn_index`, `dept_rank`, `category_breakdown`, `day_type_breakdown`, `total_cost_idr`
- [x] Departmental ranking query: `SELECT COUNT(*) + 1 FROM (subquery with all employee monthly totals for section where total > target_employee_total)`
- [x] User role gate: `if (auth()->user()->isEmployee() && $employee->id !== auth()->user()->employee_id) abort(403)`
- [x] Create `resources/js/Components/Reports/KpiSummaryCards.vue` — 4 KPI cards component
- [x] Create `resources/js/Components/Reports/CategoryDonutChart.vue` — Production/TPM/Project/Others
- [x] Create `resources/js/Components/Reports/DayTypeBreakdownBar.vue` — HKN vs HLR

---

### Story E06-03: Peer Benchmarking (Workload Distribution Analysis)

**As a** Team Leader or Manager,  
**I want** to see how an individual employee's overtime hours compare to the section average,  
**So that** I can identify if the workload is concentrated on a few people or distributed fairly.

**Story Points:** 5  
**Priority:** Should Have  
**Status:** 🟢 Completed (Sprint 6)

#### Acceptance Criteria

- [x] Below the KPI cards, a "Peer Comparison" panel shows:
    - Section average hours (current month) vs individual hours — shown as a horizontal bar or gauge
    - Variance (CALC-06): `Individual Hours − Department Average Hours per Employee`
    - Variance badge: `+8.5 hrs above average` (positive = overloaded, negative = underloaded)
    - Distribution histogram: shows all employees' monthly hours in the section as a bar chart with the current employee highlighted
- [x] "Top 5 Most Hours" and "Bottom 5 Least Hours" quick lists for the section this month
- [x] All peer data is **anonymized for User (Employee) role** — they see only their own bar and the section average, not other employees' names
- [x] For Manager/Team Leader: full names are shown in the distribution histogram

#### Technical Tasks

- [x] `EmployeeReportService::getPeerComparison(int $employeeId, int $sectionId, int $year, int $month): array`
- [x] Query: `SELECT employee_id, SUM(total_hours) FROM overtime_items JOIN overtime_submissions ... GROUP BY employee_id ORDER BY SUM(total_hours) DESC` for the section
- [x] CALC-06: `$variance = $individualHours - $sectionAverageHours`
- [x] Create `resources/js/Components/Reports/PeerComparisonPanel.vue`
- [x] Create `resources/js/Components/Reports/SectionDistributionChart.vue` — bar chart with highlight
- [x] Anonymization logic: if `auth()->user()->role === 'user'`, remove `employee_name` from peer data, keep only values

---

### Story E06-04: Safety & Fatigue Soft Indicators

**As a** Team Leader or HR,  
**I want** to see visual indicators when an employee is approaching high-workload thresholds,  
**So that** I can proactively schedule rest and reduce industrial accident risk.

**Story Points:** 5  
**Priority:** Should Have  
**Status:** 🟢 Completed (Sprint 6)

#### Acceptance Criteria

- [x] Fatigue indicator panel on dossier shows rolling 4-week workload trend: bar chart showing each week's hours
- [x] If the employee has exceeded `weekly_soft_limit_hours` in the current week: yellow badge `⚠️ Weekly Limit Approaching (22/20 hrs)`
- [x] If the employee has exceeded the weekly limit for `consecutive_weeks_alert` (default 3) consecutive weeks: red badge `🔴 Fatigue Risk: 3 consecutive weeks over limit`
- [x] "Safety Score" calculated: `100% − (overloaded weeks / 4 weeks × 100%)` — shown as a simple gauge (100% = perfect rest, 0% = 4 weeks overloaded)
- [x] All fatigue indicators are **advisory only** — no operational blocks
- [x] Team Leader receives an in-app notification when a direct report hits the consecutive-week alert for the first time in a month

#### Technical Tasks

- [x] `OvertimePolicyEvaluator::getEmployeeWelfareStatus(int $employeeId): WelfareStatus` — returns rolling 4-week assessment
- [x] Query: weekly sums for last 4 weeks from approved items
- [x] `WelfareStatus` DTO: `{ current_week_hours, limit, exceeded_weeks_count, safety_score_pct, badges[] }`
- [x] `php artisan make:notification FatigueAlertNotification` (database channel)
- [x] Dispatch fatigue notification from `RecalculateMonthlyBurnSnapshotJob` after approval (check per-employee thresholds)
- [x] Create `resources/js/Components/Reports/FatigueRollingChart.vue` — 4-week bar chart
- [x] Create `resources/js/Components/Reports/SafetyScoreGauge.vue` — simple arc gauge

---

### Story E06-05: Chronological Audit Timesheet (Employee View)

**As an** Employee or Team Leader,  
**I want** to view a chronological history of all my overtime entries with their approval status and details,  
**So that** I can verify my records are correct and track which ones have been approved or rejected.

**Story Points:** 5  
**Priority:** Must Have  
**Status:** 🟢 Completed (Sprint 6)

#### Acceptance Criteria

- [x] Timesheet table shows one row per overtime item (not per submission): date, day type, total hours, category breakdown, RCA tag, task notes, approval status badge, rejection reason (if rejected)
- [x] Columns: `Date`, `Day`, `HKN/HLR`, `Production`, `TPM`, `CapEx`, `Others`, `Total`, `Status`, `Notes`
- [x] Sortable by date (default: newest first); filterable by: status, date range, category
- [x] Pagination: 25 rows per page, server-side
- [x] Rejected items show rejection reason in an expandable detail row or tooltip
- [x] User (Employee role) can only see their own timesheet — scoped at controller level
- [x] Team Leader can view timesheet for any employee in their section
- [x] Export to CSV: personal timesheet for a selected date range

#### Technical Tasks

- [x] `EmployeeReportController@timesheet` — `GET /reports/employees/{npk}/timesheet`
- [x] Query: `OvertimeItem::with(['overtimeSubmission:id,operational_date,day_type', 'capexProject:id,name'])` filtered by `employee_id`, paginated
- [x] Route guard: Employee can only access `/reports/employees/{own_npk}/timesheet`
- [x] Create `resources/js/Components/Reports/PersonalTimesheetTable.vue` — paginated, filterable
- [x] CSV export: `GET /reports/employees/{npk}/timesheet/export`
- [x] `EmployeeTimesheetExport` using streamed response cursor (`fputcsv` + `response()->streamDownload()`) with UTF-8 BOM

---

### Story E06-06: Employee Self-Service Personal Dashboard (User Role)

**As an** Employee (User role),  
**I want** a simplified personal dashboard on login,  
**So that** I can quickly see my current overtime status without navigating complex admin screens.

**Story Points:** 2  
**Priority:** Nice to Have  
**Status:** 🟢 Completed (Sprint 6)

#### Acceptance Criteria

- [x] When a User role logs in, they land on a personal summary page (not the full manager dashboard)
- [x] Shows: this month's total hours, year-to-date hours, latest 5 timesheet entries with status badges
- [x] Quick navigation links: "View Full Timesheet", "View Peer Comparison"
- [x] No access to other employees' data, approval queues, budgets, or admin screens

#### Technical Tasks

- [x] `DashboardController` — check auth user role and redirect accordingly on login
- [x] If `role === 'user'`: redirect to `GET /my/dashboard` → `EmployeeSelfServiceController@index`
- [x] Create `resources/js/Pages/Dashboard/EmployeeSelfService.vue` — simple 3-card layout
- [x] Update `HandleInertiaRequests` to include `auth.user.employee_id` in shared props

---

## Sprint 6 Schedule (Parallel with Epic-07)

| Sprint Day | Focus                                            | Stories |
| ---------- | ------------------------------------------------ | ------- |
| Day 1–2    | Employee search + dossier lookup                 | E06-01  |
| Day 3–5    | Personal overtime dashboard + KPI cards + charts | E06-02  |
| Day 5–6    | Peer benchmarking + anonymization logic          | E06-03  |
| Day 7–8    | Fatigue indicators + safety gauge + notification | E06-04  |
| Day 9      | Chronological timesheet table + CSV export       | E06-05  |
| Day 10     | Employee self-service dashboard                  | E06-06  |

---

## Risks & Assumptions

| Risk                                                                | Likelihood | Mitigation                                                                                            |
| ------------------------------------------------------------------- | ---------- | ----------------------------------------------------------------------------------------------------- |
| Department ranking query slow at scale (1,500 employees)            | Low        | Index on `(employee_id, status)` in `overtime_items`; use window functions or a subquery with a count |
| User role accidentally accessing another employee's dossier via URL | Medium     | Strict controller-level authorization check on every `show()` method                                  |
| Fatigue notifications flooding on large teams                       | Low        | Per-employee, per-month deduplication: only notify once per month per employee per threshold          |

---

## Definition of Done — Epic-06

- [x] Employee search works across NPK and name with 300ms debounce
- [x] KPI cards show correct current month and YTD hours from approved items only
- [x] Peer variance (CALC-06) calculates correctly
- [x] Fatigue consecutive-week alert correctly detects 3+ weeks over limit
- [x] Timesheet table is paginated, filterable, and sortable
- [x] User (Employee) role is strictly scoped to their own data
- [x] `pnpm lint` passes
- [x] `pnpm build` succeeds
