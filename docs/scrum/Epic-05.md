# Epic-05: Budget Management & Burn Index Dashboard

**Epic ID:** E-05  
**Priority:** P1 – Must Have  
**Estimated Total:** 45 Story Points  
**Target Sprints:** Sprint 5 (Weeks 9–10)  
**Dependencies:** Epic-01, Epic-02 (Budget records exist), Epic-03 (Submissions exist), Epic-04 (Approvals trigger snapshot jobs)  
**Lead Area:** Backend (`BurnIndexCalculatorService`, `MonthlySnapshotService`, `RecalculateMonthlyBurnSnapshotJob`) + Vue 3 Dashboard

---

## Business Context

The Burn Index is the central KPI of this system. Finance controllers, production managers, and plant leadership use it to answer: "Are we consuming our overtime budget at a safe rate, or heading toward an overrun?"

This epic builds the analytical dashboard that answers that question in real time. Key design decisions from the architecture:

- **Denormalized snapshot table** (`monthly_burn_snapshots`) is the source for all dashboard queries — never aggregate raw `overtime_items` on every dashboard load.
- Snapshots are refreshed asynchronously via `RecalculateMonthlyBurnSnapshotJob` after each approval batch.
- **Dashboard reads are OLAP-style** (read-heavy, no write lock contention) while OLTP submissions happen on a separate write path.
- The 4-quadrant **Budget Control Matrix** (Zone 1 Excellent → Zone 4 Poor) maps burn index + cumulative hours into an operational status.
- CapEx vs OpEx splits must be clearly visible for financial controllers to track capitalization compliance.

---

## User Stories

---

### Story E05-01: Section-Level Burn Index Dashboard (Manager/Team Leader)

**As a** Manager or Team Leader,  
**I want** to view the current month's Burn Index for each section I oversee,  
**So that** I can immediately see which sections are on track, which are warning, and which are in deficit — before it's too late to intervene.

**Story Points:** 13  
**Priority:** Must Have  
**Status:** 🟢 Completed (Sprint 5)

#### Acceptance Criteria

- [x] Dashboard shows one card per section within the Manager's department (Team Leader sees their own section only)
- [x] Each section card displays:
    - Section name + department
    - Planned budget hours vs actual hours (e.g., "142 / 200 hrs")
    - Remaining budget hours
    - **Burn Index %** in large text with color-coded status:
        - Green: `< 85%` — Under Budget
        - Blue: `85–100%` — On Track
        - Orange: `101–115%` — Warning
        - Red: `> 115%` — Over Budget / Deficit
    - **Budget Control Matrix Zone badge**: ZONE_1_EXCELLENT / ZONE_2_GOOD / ZONE_3_WARNING / ZONE_4_POOR
    - Burn Velocity (hrs/week)
    - Projected period-end total hours
    - CapEx hours | OpEx hours split bar
- [x] Data is sourced from `monthly_burn_snapshots` — NOT from live aggregation on `overtime_items`
- [x] If no budget is configured for a section: show `"Budget Not Configured"` card state instead of crashing
- [x] Cards are filterable by: fiscal year + month (month picker, defaults to current month)
- [x] Dashboard auto-refreshes if a new approval is processed (via server-sent events or polling every 60s)
- [x] "Last Recalculated" timestamp shown per card

#### Technical Tasks

- [x] `DashboardBurnIndexController@index` — returns `monthly_burn_snapshots` for user's scope
- [x] `MonthlySnapshotService::getOrRecalculate(int $sectionId, int $year, int $month): MonthlyBurnSnapshot`
- [x] Route: `GET /dashboard/burn-index` → `DashboardBurnIndexController@index`
- [x] `BurnIndexCalculatorService::calculateSectionMetrics()` — implement exactly as in `data-architect-analyst.md` §3.3
- [x] `RecalculateMonthlyBurnSnapshotJob` — calls `BurnIndexCalculatorService` and upserts `monthly_burn_snapshots`
- [x] Create `resources/js/Pages/Dashboard/BurnIndex.vue` — grid of section cards
- [x] Create `resources/js/Components/Dashboard/BurnIndexCard.vue` — individual section card with Burn Index ring/dial
- [x] Create `resources/js/Components/Dashboard/CapexOpexSplitBar.vue` — horizontal split bar component
- [x] Month picker: Vue `<MonthPicker>` component that triggers page reload/Inertia visit with new params
- [x] Color logic: Vue `computed(() => burnIndex < 85 ? 'green' : burnIndex <= 100 ? 'blue' : burnIndex <= 115 ? 'orange' : 'red')`
- [x] 60s polling: `setInterval(() => router.reload({ only: ['snapshots'] }), 60000)` — Inertia partial reload

---

### Story E05-02: 5-Week Monthly Burndown Chart (Manager/Admin)

**As a** Manager,  
**I want** to view a weekly burndown chart showing planned vs actual overtime hours across the 5-week monthly cycle,  
**So that** I can spot if a section is burning budget too fast in early weeks and will run out before month end.

**Story Points:** 8  
**Priority:** Must Have

#### Acceptance Criteria

- [ ] Clicking into a section card (from E05-01) opens a section detail page or expanded panel
- [ ] Section detail shows a **line chart**: X-axis = Week 1 through Week 5, Y-axis = cumulative hours
    - Line 1 (dashed): Planned cumulative hours (`week1_planned_hours` + prior weeks)
    - Line 2 (solid): Actual cumulative hours (from approved items per week)
    - Line 3 (dotted, ML): ML-projected trajectory (from `ml_predictions` — optional, shows if ML Epic is complete)
- [ ] Chart includes a **zone shading** area: above 100% budget = warning zone (light red fill), 85-100% = on-track zone (light blue)
- [ ] Below chart: table showing per-week breakdown:
    - Week #, Date range, Planned hrs, Actual hrs, HKN hrs, HLR hrs, Burn %
- [ ] Section detail also shows the 4-quadrant Budget Control Matrix as a scatter plot (Burn % on X, Hours on Y) with the section's current position plotted
- [ ] All charts rendered using a Vue-compatible charting library (recommend: `chart.js` via `vue-chartjs`)

#### Technical Tasks

- [ ] `DashboardBurnIndexController@show` — returns section detail with weekly breakdowns
- [ ] Weekly aggregation query: group approved items by `WEEK(operational_date)` or date bucket, compute cumulative totals per week
- [ ] Store per-week breakdown in response data (not in `monthly_burn_snapshots` — computed on request for detail view)
- [ ] `pnpm add vue-chartjs chart.js`
- [ ] Create `resources/js/Pages/Dashboard/SectionDetail.vue` — section deep-dive page
- [ ] Create `resources/js/Components/Dashboard/BurndownLineChart.vue` — line chart component
- [ ] Create `resources/js/Components/Dashboard/BudgetMatrixScatter.vue` — 4-quadrant scatter plot
- [ ] Create `resources/js/Components/Dashboard/WeeklyBreakdownTable.vue` — tabular weekly data

---

### Story E05-03: CapEx vs OpEx Distribution Panel (Manager/Admin)

**As a** Finance Controller or Manager,  
**I want** a clear visual breakdown of how overtime hours split between Operational Expenditure and Capital Expenditure for the current period,  
**So that** capitalized labor can be tracked for accounting compliance and CapEx project progress can be verified.

**Story Points:** 8  
**Priority:** Must Have

#### Acceptance Criteria

- [ ] Department-level panel shows:
    - Total overtime hours: OpEx | CapEx split (pie or donut chart + summary table)
    - CapEx Labor Ratio % (CALC-07): `(Total Project Hours / Total Hours) × 100%`
    - OpEx Labor Ratio %
- [ ] Drill-down by section: bar chart showing each section's CapEx vs OpEx hours side by side
- [ ] Drill-down by CapEx project: table showing each CapEx project's total hours logged, allocated budget hours, variance
- [ ] Date range filter: current month (default), custom range, year-to-date
- [ ] Formula display: hovering over "CapEx Ratio" shows tooltip with formula: `Project Hours / Total Hours × 100%`
- [ ] If no CapEx hours in period: show `"No CapEx labor recorded this period"` state

#### Technical Tasks

- [ ] `CapexOpexReportController@index` — aggregates `hours_project` vs total from approved items
- [ ] Route: `GET /reports/capex-opex` → `CapexOpexReportController@index`
- [ ] Query: `SUM(hours_project)`, `SUM(hours_production + hours_tpm + hours_others)` per section per month
- [ ] Create `resources/js/Pages/Reports/CapexOpex.vue`
- [ ] Create `resources/js/Components/Reports/CapexOpexDonutChart.vue`
- [ ] Create `resources/js/Components/Reports/CapexProjectTable.vue` — links to Epic-07 CapEx project detail

---

### Story E05-04: Policy Threshold Alert System (Budget Warnings)

**As a** Manager,  
**I want** to receive in-app alerts when my department's Burn Index crosses company policy thresholds,  
**So that** I have enough time to adjust overtime scheduling before the budget is exhausted.

**Story Points:** 5  
**Priority:** Must Have

#### Acceptance Criteria

- [ ] After each `RecalculateMonthlyBurnSnapshotJob` run, the system evaluates whether the Burn Index crossed a threshold
- [ ] Thresholds checked (from `policy_thresholds`):
    - `burn_warning_pct` (default 100%) → Creates a `WARNING` notification: "Section X Burn Index reached 103% — approaching budget ceiling"
    - `burn_danger_pct` (default 115%) → Creates a `DANGER` notification: "Section X is 18% over budget. Immediate review required."
- [ ] Notifications are sent **only once per threshold crossing per section per fiscal month** (not on every job run after crossing)
- [ ] Notification recipients: Manager for that department + Admin
- [ ] Notification respects user preference `notifications.budget_alerts`
- [ ] Alert is visible in the notification bell (from Epic-03 E03-05 infrastructure)
- [ ] Dashboard cards that have crossed warning threshold show a pulsing border animation

#### Technical Tasks

- [ ] `BudgetAlertService::evaluateAndNotify(MonthlyBurnSnapshot $snapshot): void`
- [ ] Track "already notified" state: add `warned_at` and `danger_at` columns to `monthly_burn_snapshots`
- [ ] Condition: `if ($snapshot->burn_index_pct >= $threshold->burn_warning_pct && $snapshot->warned_at === null)`
- [ ] `php artisan make:notification BudgetThresholdAlert` (database channel)
- [ ] Call `BudgetAlertService::evaluateAndNotify()` at end of `RecalculateMonthlyBurnSnapshotJob`
- [ ] CSS: `.burn-card--danger { animation: pulse-border 1.5s infinite; }` in Tailwind or custom CSS

---

### Story E05-05: Department-Level Consolidated Burn Dashboard (Manager/Admin)

**As a** Department Manager,  
**I want** a single consolidated view of all sections in my department with their Burn Index status,  
**So that** I can identify the worst-performing sections at a glance during daily standups without clicking into each one.

**Story Points:** 5  
**Priority:** Must Have

#### Acceptance Criteria

- [ ] Top-level department dashboard shows a ranked list/table of all sections by Burn Index (highest first)
- [ ] Columns: Section name, Planned Hours, Actual Hours, Remaining Hours, Burn Index %, Zone, Velocity, Projected Total
- [ ] Color-coded rows matching Burn Index thresholds
- [ ] Summary header: "Department Total: 1,245 / 2,000 hrs (62.3%) — On Track" using department-level aggregation
- [ ] One-click navigation from section row to section detail (E05-02)
- [ ] Admin sees a cross-department aggregated view: all departments side-by-side with drill-down
- [ ] "Export to PDF" option for weekly management report (basic HTML-to-PDF)

#### Technical Tasks

- [ ] `DashboardBurnIndexController@departmentSummary` — aggregates from `monthly_burn_snapshots` grouped by department
- [ ] Route: `GET /dashboard/burn-index/department/{id}` → `departmentSummary()`
- [ ] Create `resources/js/Pages/Dashboard/DepartmentBurnSummary.vue`
- [ ] Create `resources/js/Components/Dashboard/SectionBurnTable.vue` — sortable, color-coded rows
- [ ] "Export to PDF": use `barryvdh/laravel-dompdf` or server-side PDF generation
- [ ] Admin multi-department view: Inertia page with all departments as tabs or accordion

---

### Story E05-06: Burn Velocity & Projected Period-End Calculation

**As a** Manager,  
**I want** the system to automatically calculate the current burn velocity and project whether the section will overrun its monthly budget,  
**So that** I can take corrective action (redistribute work, defer overtime) before the overrun actually occurs.

**Story Points:** 6  
**Priority:** Must Have

#### Acceptance Criteria

- [ ] Burn Velocity (CALC-04) is calculated: `Cumulative Approved Hours ÷ Elapsed Weeks in Period`
- [ ] Projected Period-End Total (CALC-05) = `Velocity × Total Weeks in Period (4.3 default)`
- [ ] A "trajectory indicator" is shown below the Burn Index:
    - `→ On Pace` (projected ≤ 100%)
    - `↗ Trending Over` (projected 100–120%)
    - `↑ Will Overrun` (projected > 120%)
- [ ] Elapsed weeks uses `Asia/Jakarta` timezone: `days_elapsed_in_month / 7.0`, minimum 1.0
- [ ] For months that have already ended (historical view): elapsed weeks = 4.3 (full month)
- [ ] Projected total shown alongside ML forecast (when Epic-08 is live): "Heuristic: 182 hrs | ML Forecast: 175 hrs ±12 hrs"
- [ ] Velocity and projection are recomputed in `BurnIndexCalculatorService` and stored in `monthly_burn_snapshots`

#### Technical Tasks

- [ ] Implement `CALC-04` and `CALC-05` in `BurnIndexCalculatorService::calculateSectionMetrics()` — already stubbed in architecture doc §3.3
- [ ] Store `burn_velocity` in `monthly_burn_snapshots` (column already in schema)
- [ ] Add `projected_total_hours` computed field in service return array (not stored — derived from velocity × 4.3)
- [ ] `BurnIndexCard.vue`: add trajectory indicator icon and label based on `projected_total_hours` vs `planned_budget_hours`
- [ ] Unit test: `BurnIndexCalculatorServiceTest::test_velocity_uses_jakarta_timezone()`

---

## Sprint 5 Breakdown

| Sprint Day | Focus                                                                              | Stories        |
| ---------- | ---------------------------------------------------------------------------------- | -------------- |
| Day 1–3    | `BurnIndexCalculatorService` + `RecalculateMonthlyBurnSnapshotJob` + section cards | E05-01, E05-06 |
| Day 4–5    | 5-week burndown chart + scatter plot (vue-chartjs)                                 | E05-02         |
| Day 6–7    | CapEx vs OpEx distribution panel + project table                                   | E05-03         |
| Day 7–8    | Policy threshold alert notifications                                               | E05-04         |
| Day 9–10   | Department consolidated view + PDF export                                          | E05-05         |

---

## Calculations Reference (All Implemented in `BurnIndexCalculatorService`)

| Formula                     | Description                                               |
| --------------------------- | --------------------------------------------------------- |
| `CALC-02` Burn Index        | `(Cumulative Actual Hours / Planned Budget Hours) × 100%` |
| `CALC-03` Remaining Budget  | `Planned Budget Hours − Cumulative Actual Hours`          |
| `CALC-04` Burn Velocity     | `Cumulative Actual Hours / Elapsed Weeks`                 |
| `CALC-05` Projected Total   | `Velocity × 4.3` (total weeks in period)                  |
| `CALC-07` CapEx Labor Ratio | `(Total Project Hours / Total Hours) × 100%`              |

---

## Risks & Assumptions

| Risk                                                      | Likelihood | Mitigation                                                                                  |
| --------------------------------------------------------- | ---------- | ------------------------------------------------------------------------------------------- |
| Dashboard slow if snapshot job hasn't run yet             | Medium     | `MonthlySnapshotService::getOrRecalculate()` — live-calculates if snapshot is stale/missing |
| `elapsed_weeks` calculation wrong for timezone edge cases | Low        | Always compute using `now('Asia/Jakarta')`, add unit test                                   |
| Alert notification sent multiple times for same threshold | Medium     | `warned_at` / `danger_at` null-check before creating notification                           |
| vue-chartjs version compatibility issues                  | Low        | Lock version in `package.json`, test on dev env early                                       |

---

## Definition of Done — Epic-05

- [ ] Burn Index dashboard loads in < 2 seconds for a section with 12 months of historical data
- [ ] Section card shows correct color/zone based on current Burn Index
- [ ] 5-week burndown chart renders correctly with planned vs actual lines
- [ ] CapEx vs OpEx split panel shows accurate ratio from approved items
- [ ] Budget threshold alert notification created once when threshold is crossed
- [ ] `BurnIndexCalculatorService` unit tests pass for all 4 zone classifications
- [ ] `pnpm lint` passes
- [ ] `pnpm build` succeeds
