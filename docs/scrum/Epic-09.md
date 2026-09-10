# Epic-09: Executive Dashboard & Analytics Decision Intelligence

**Epic ID:** E-09  
**Priority:** P0 – CRITICAL (This is the most visible screen in the entire application)  
**Estimated Total:** 63 Story Points  
**Target Sprints:** Sprint 9–10 (Weeks 17–20)  
**Dependencies:** Epic-01 through Epic-05 (approved overtime data must exist), Epic-07 (CapEx data), Epic-08 (ML predictions, optional but enhances charts)  
**Prototype Source:** `Dashboard/dashboard.html` + `AnalyticDecision/analytic-and-decision.html`  
**Lead Area:** Vue 3 Chart Components (Chart.js via `vue-chartjs`) + Backend Analytics API Controllers

---

## Business Context

> "You built me a system to track overtime — but where are the charts?"

This is the most important module in the application. Every Manager, every Finance Controller, and every Plant Head opens this screen first thing in the morning. The existing `Dashboard/dashboard.html` and `AnalyticDecision/analytic-and-decision.html` prototypes define the full visual scope — and **none of it is covered in any prior Epic**.

The two prototype pages map to two distinct modules:

### Module A — Executive Operational Dashboard (`Dashboard/dashboard.html`)

The **live operational monitoring screen**: KPI cards with mini-charts, daily burn chart, section/department burn comparison, employee overtime leaderboard, category distribution, day type breakdown, and a searchable employee summary table. This is what Team Leaders and Managers check daily.

### Module B — Analytics & Decision Intelligence (`AnalyticDecision/analytic-and-decision.html`)

The **deep analytical and strategic planning screen**: 6 interactive tabs covering predictive analytics, cost analysis, production-overtime correlation, what-if scenario simulation, key risk insights, and period-over-period comparative benchmarking. This is what Plant Heads and Finance Controllers use weekly.

---

## ⚠️ Prototype Pruning Rules (MUST READ before building)

The BA spec (`ba-analyst-reqs-draft.md` §4) has pruned several prototype elements. Do **NOT** port these:

| Prototype Element                                                             | Status                      | Replacement                                                 |
| ----------------------------------------------------------------------------- | --------------------------- | ----------------------------------------------------------- |
| **ROI Projection Card** (Scenario tab: `ROI = 156% + 1.2 * OT%`)              | ❌ REMOVED                  | Use real budget variance analysis                           |
| **Employee Well-being Score** (Scenario tab: `Well-being = 7.5 - 0.05 * OT%`) | ❌ REMOVED                  | Use fatigue consecutive-week safety indicators from Epic-06 |
| **Difficulty Index Tiers** (Index I–IV: Rp 50k–150k)                          | ❌ REMOVED                  | Use real `hourly_rate_snapshot` from DB                     |
| **Consistency Star Rating** (1–5 stars)                                       | ❌ REMOVED                  | Use Burn Index zone classification                          |
| **Hard PP 35/2021 statutory blocks**                                          | ✅ MODIFIED → Soft warnings | Keep HKN/HLR distinction, soft policy indicators only       |

Everything else in the prototypes is in-scope.

---

## Shared Chart Infrastructure (Pre-requisite for All Stories)

Before any chart story begins, the chart infrastructure must be in place.

### Story E09-00: Vue Chart.js Component Library Setup

**As a** developer,  
**I want** a shared, reusable chart component library installed and configured,  
**So that** all chart stories can be built consistently without duplicating canvas setup code.

**Story Points:** 5  
**Priority:** Must Have (blocks all other E09 stories)

#### Acceptance Criteria

- [x] `pnpm add vue-chartjs chart.js` installed and committed
- [x] `Chart.js` global defaults configured once: font family `Instrument Sans` / `Inter`, responsive `true`, `maintainAspectRatio: false`, `Asia/Jakarta` timezone-aware x-axis ticks
- [x] Base chart wrapper components created in `resources/js/components/charts/`:
    - `BaseLineChart.vue` — accepts `{ labels, datasets, options }` props
    - `BaseBarChart.vue` — horizontal and vertical bar support
    - `BaseDonutChart.vue` — donut/pie variant
    - `BaseScatterChart.vue` — scatter plot
    - `BaseMiniSparkline.vue` — compact sparkline for KPI cards (no legend, no axes labels)
- [x] Each component accepts a `loading` prop — shows skeleton pulse when `true`
- [x] Each component accepts a `empty` prop — shows "Belum ada data" placeholder when `true`
- [x] All chart colors use a consistent plant theme palette defined in `useChartTheme.ts`:
    - Primary: `#2563eb` (blue) — Planned / Budget
    - Success: `#16a34a` (green) — Under Budget / Approved
    - Warning: `#d97706` (amber) — Threshold Warning
    - Danger: `#dc2626` (red) — Over Budget / Deficit
    - CapEx: `#7c3aed` (violet)
    - OpEx: `#0891b2` (cyan)
    - HKN: `#3b82f6` (blue)
    - HLR: `#f59e0b` (amber)
- [x] `pnpm lint && pnpm build` passes with chart components registered globally in `app.ts`

#### Technical Tasks

- [x] `pnpm add vue-chartjs chart.js`
- [x] Create `resources/js/plugins/chartjs.ts` — register all used Chart.js components explicitly (tree-shakeable)
- [x] Create `resources/js/composables/useChartTheme.ts` — exports the color palette and font defaults
- [x] Create all 5 base chart component files
- [x] Register components globally in `app.ts`
- [x] Create `resources/js/components/charts/ChartSkeleton.vue` — animated loading placeholder matching chart height

---

## Module A: Executive Operational Dashboard

---

### Story E09-01: Dashboard Header KPI Cards with Mini Sparklines

**As a** Manager or Team Leader,  
**I want** to see four KPI summary cards at the top of the dashboard with live mini-charts,  
**So that** I can instantly understand today's production context alongside the overtime burn status.

**Story Points:** 8  
**Priority:** Must Have

#### Acceptance Criteria (maps to `dashboard.html` top cards)

- [x] **Card 1 — Production Volume**: Mini sparkline chart (line) showing daily production unit count for the last 14 days. Value shows today's target volume. Date picker to switch view date.
- [x] **Card 2 — Working Days**: Mini bar chart showing working days in the current month (HKN count vs total days). Shows remaining working days in month.
- [x] **Card 3 — Man Power**: Mini bar chart showing active headcount per section. Shows total active employees today.
- [x] **Card 4 — Burn Chart Index Plan vs Actual**: Progress bar visual showing:
    - `Plan` bar = 100% (always, represents budget)
    - `Actual` bar = current month's Burn Index % (color-coded: green/amber/red)
    - Numbers displayed on the bars
- [x] Each card has a date selector that updates the displayed data via Inertia partial reload
- [x] All data sourced from `monthly_burn_snapshots`, `employees`, and `operational_calendars`
- [x] `BaseMiniSparkline.vue` used for Cards 1–3
- [x] Cards load independently — individual loading skeletons per card while data fetches

#### Technical Tasks

- [x] `DashboardController@kpiCards` — `GET /dashboard/kpi-cards` returns `{ production_volume, working_days, man_power, burn_index }`
- [x] Production volume: if ERP data not available, this card shows "N/A — ERP feed not connected" gracefully
- [x] Working days count: query `operational_calendars` for current month, count `day_type = 'HKN'`
- [x] Man power: `Employee::where('is_active', true)->count()` + per-section breakdown
- [x] Burn Index card: read from `monthly_burn_snapshots` for current month, current department scope
- [x] Create `resources/js/components/dashboard/KpiCardProduction.vue`
- [x] Create `resources/js/components/dashboard/KpiCardWorkingDays.vue`
- [x] Create `resources/js/components/dashboard/KpiCardManPower.vue`
- [x] Create `resources/js/components/dashboard/KpiCardBurnIndex.vue`
- [x] Route: `GET /dashboard` → `DashboardController@index` — passes all KPI data as Inertia props

---

### Story E09-02: Daily Burn Chart Index (Main Line Chart)

**As a** Manager,  
**I want** a full-width line chart showing daily cumulative overtime burn against the planned budget curve for the current month,  
**So that** I can visually trace whether the section is running ahead or behind the planned consumption pace day by day.

**Story Points:** 8  
**Priority:** Must Have

#### Acceptance Criteria (maps to `dashboard.html` "Daily Burn Chart Index Overtime")

- [x] Full-width chart area (~384px height) with two data series:
    - **Line 1 (solid blue, dashed)** — `Plan`: evenly distributed planned hours across the month (straight diagonal from 0 to `planned_hours`)
    - **Line 2 (solid, color by zone)** — `Actual`: cumulative approved hours day by day
    - **Line 3 (dotted, violet)** — `ML Projected`: projected end-of-month trajectory (from `ml_predictions`, shown only if Epic-08 is live)
- [x] X-axis: each day of the month (1–31)
- [x] Y-axis: overtime hours (0 to `planned_hours × 1.3` to leave headroom for overruns)
- [x] A horizontal threshold line at `planned_hours` (100% budget line) with label "Budget Ceiling"
- [x] A background shading: above budget ceiling = light red fill zone
- [x] Month selector: prev/next month navigation arrows
- [x] Department/Section filter: multi-select dropdown (Manager sees their sections, Admin sees all)
- [x] Hovering a data point shows tooltip: `Day X | Actual: Y hrs | Plan: Z hrs | Variance: ±W hrs`
- [x] Chart renders in < 1 second (data pre-aggregated by day in query)

#### Technical Tasks

- [x] `DashboardController@dailyBurnChart` — `GET /dashboard/charts/daily-burn`
- [x] Query: group approved `overtime_items` by `overtime_submissions.operational_date`, cumulative SUM per day
- [x] Returns: `{ labels: ['1', '2', ...], actual_cumulative: [...], plan_cumulative: [...], ml_projected: [...] }`
- [x] Plan cumulative: `planned_hours / days_in_month * day_index` for each day
- [x] Create `resources/js/components/dashboard/DailyBurnLineChart.vue` — extends `BaseLineChart.vue`
- [x] Threshold annotation: native Chart.js canvas `beforeDraw` plugin for budget ceiling and red fill zone
- [x] Month navigation: reactive `selectedMonth` ref → triggers Inertia partial reload with `only: ['dailyBurnChart', ...]`
- [x] Background zone: native Chart.js canvas `beforeDraw` plugin

---

### Story E09-03: Section/Department Burn Comparison Chart

**As a** Manager,  
**I want** a grouped bar chart comparing burn index across all sections in my department,  
**So that** I can immediately see which sections are behind or ahead of their budget pace in a single glance.

**Story Points:** 5  
**Priority:** Must Have

#### Acceptance Criteria (maps to `dashboard.html` "Daily Burn Chart Section/Department")

- [x] Grouped bar chart — one bar group per day of the month, each bar group has N bars (one per active section)
- [x] OR: a simpler and more readable **horizontal bar chart** showing each section's current cumulative Burn Index % side by side
- [x] Bars color-coded by zone: green (< 85%), blue (85–100%), amber (101–115%), red (> 115%)
- [x] Each bar labeled with section code and current Burn Index %
- [x] Clicking a bar navigates to that section's detail page (Epic-05, E05-02)
- [x] Date filter: same month selector as E09-02

#### Technical Tasks

- [x] `DashboardController@sectionBurnComparison` — `GET /dashboard/charts/section-burn`
- [x] Query: `monthly_burn_snapshots` for the selected month, all sections in manager's dept
- [x] Returns: `{ sections: [{ code, name, burn_index_pct, zone }] }` ordered by burn_index_pct desc
- [x] Create `resources/js/components/dashboard/SectionBurnComparisonChart.vue` — horizontal bar chart
- [x] Click handler: `router.visit(route('dashboard.section-detail', { id: section.id }))` (navigates to `/dashboard/burn-index?section={id}`)

---

### Story E09-04: Overtime Leaderboard, Category Distribution & Day Type Charts

**As a** Manager or Team Leader,  
**I want** to see who has the most overtime hours, how hours are split by category, and the HKN vs HLR breakdown, all in the bottom row of the dashboard,  
**So that** I have a complete 360° view of the current month's overtime landscape in one screen.

**Story Points:** 8  
**Priority:** Must Have

#### Acceptance Criteria (maps to `dashboard.html` bottom 3-column and 2-column sections)

**Leaderboard Chart (top-left of bottom section):**

- [x] Horizontal bar chart: top 10 employees by approved overtime hours this month
- [x] Bars show employee name (or NPK if role is User) + total hours
- [x] Color: gradient from green (low) to red (high), crossing the soft limit threshold
- [x] Date filter: same month selector

**Category Overtime Chart (center of bottom section):**

- [x] Donut chart: Production | TPM | Project (CapEx) | Others — proportional to total hours
- [x] Legend shows absolute hours + percentage for each category
- [x] Clicking a segment filters the summary table below to show only employees with that category

**Trend Working Time Chart (right of bottom section):**

- [x] Line chart: 12-month rolling total overtime hours trend (one point per month)
- [x] Shows two lines: HKN total and HLR total — compares normal vs holiday overtime trends

**Daily Index Trend Chart (bottom-left):**

- [x] Line chart: daily Burn Index value (not cumulative — just that day's contribution index)
- [x] Overlays the policy soft threshold as a horizontal line

**Overtime Day Type Chart (bottom-right):**

- [x] Grouped bar chart: for each week of the month, shows HKN hours vs HLR hours side by side
- [x] Shows whether holiday overtime is increasing or decreasing

#### Technical Tasks

- [x] `DashboardController@leaderboard` — top 10 employees by approved hours, scoped to dept
- [x] `DashboardController@categoryDistribution` — SUM hours by category for current month
- [x] `DashboardController@trendWorkingTime` — 12-month rolling monthly totals, split HKN/HLR
- [x] `DashboardController@dailyIndexTrend` — per-day Burn Index contribution
- [x] `DashboardController@dayTypeBreakdown` — weekly HKN vs HLR split
- [x] Create `resources/js/Components/Dashboard/OvertimeLeaderboardChart.vue`
- [x] Create `resources/js/Components/Dashboard/CategoryDistributionDonut.vue`
- [x] Create `resources/js/Components/Dashboard/TrendWorkingTimeChart.vue`
- [x] Create `resources/js/Components/Dashboard/DailyIndexTrendChart.vue`
- [x] Create `resources/js/Components/Dashboard/DayTypeBreakdownChart.vue`

---

### Story E09-05: Summary Employee Overtime Table (Dashboard)

**As a** Manager,  
**I want** a searchable, scrollable summary table at the bottom of the dashboard showing every employee's individual Burn Index, total hours, and category breakdown,  
**So that** I can quickly identify specific individuals for follow-up without navigating to the employee report module.

**Story Points:** 5  
**Priority:** Must Have

#### Acceptance Criteria (maps to `dashboard.html` "Summary Overtime Data" table)

- [x] Table columns: `Employee Name`, `Burn Index` (mini progress bar), `Total Hours`, `Category` (mini horizontal stacked bar), `Status` (SPKL badge + approval status)
- [x] Client-side search: typing in the search box filters visible rows in real-time (no server round-trip)
- [x] Clicking an employee row navigates to their full dossier (Epic-06, E06-01)
- [x] Table shows all employees in the Manager's department for the selected month
- [x] Sortable columns: Total Hours (default: desc), Name (alphabetical), Burn Index
- [x] Burn Index mini progress bar: same 4-color scheme (green/blue/amber/red)
- [x] Category mini bar: tiny 4-color stacked bar (no labels, just proportional colored segments)
- [x] Pagination: first 50 rows shown, "Load More" button for the rest

#### Technical Tasks

- [x] `DashboardController@employeeSummaryTable` — returns all employees in dept with their monthly totals
- [x] Vue reactive search: `computed(() => employees.filter(e => e.name.includes(searchQuery)))`
- [x] Create `resources/js/Components/Dashboard/EmployeeSummaryTable.vue`
- [x] Create `resources/js/Components/Dashboard/MiniProgressBar.vue` — reusable colored bar
- [x] Create `resources/js/Components/Dashboard/MiniCategoryBar.vue` — 4-color stacked mini bar (Production/TPM/CapEx/Others)
- [x] Route: `GET /dashboard` → passes all summary data with Inertia (or lazy-load via `GET /dashboard/employee-summary`)

---

## Module B: Analytics & Decision Intelligence

---

### Story E09-06: Analytics Page Shell & Tab Navigation

**As a** Manager or Admin,  
**I want** an Analytics & Decision Intelligence page with 6 navigable tabs,  
**So that** I can switch between different analytical views without leaving the page or losing my filter context.

**Story Points:** 3  
**Priority:** Must Have

#### Acceptance Criteria (maps to `analytic-and-decision.html` tab structure)

- [x] Inertia page: `resources/js/Pages/Analytics/Index.vue`
- [x] 6 tabs: `Prediksi Lembur` | `Analisis Biaya` | `Korelasi & Pola` | `Simulasi Skenario` | `Wawasan Kunci` | `Perbandingan Periode`
- [x] Tab switching is **client-side** (no page reload) — use a `activeTab` ref with `v-show` or `<Transition>`
- [x] Active tab persists in URL query param (`?tab=cost`) so links can be shared directly
- [x] Global filter bar at the top: Department selector + Date Range (applies to all tabs)
- [x] "Export Report" button at top right — exports the current tab's data as PDF or CSV
- [x] Page header: title "Analitik & Keputusan Lembur" with subtitle

#### Technical Tasks

- [x] `AnalyticsController@index` — single page load, passes all filter options as Inertia props
- [x] Route: `GET /analytics` → `AnalyticsController@index` (middleware: `auth`, `role:admin,manager`)
- [x] Create `resources/js/Pages/Analytics/Index.vue` — tab shell with `<component :is="activeTabComponent" />`
- [x] Create tab sub-components as lazy-loaded Vue components: `TabPredictive.vue`, `TabCostAnalysis.vue`, `TabCorrelation.vue`, `TabScenario.vue`, `TabInsights.vue`, `TabComparison.vue`
- [x] URL sync: `watch(activeTab, (tab) => router.replace({ query: { tab } }), { immediate: true })`
- [x] Global filter passed down to all tab components via `provide/inject` or Inertia shared data

---

### Story E09-07: Predictive Analytics Tab (Prediksi Lembur)

**As a** Manager,  
**I want** the Predictive Analytics tab showing next-month forecasts, 6-month trend projections, and seasonal pattern analysis,  
**So that** I can plan staffing resources and budget allocations before the month begins.

**Story Points:** 8  
**Priority:** Must Have

#### Acceptance Criteria (maps to `analytic-and-decision.html` "Prediksi Lembur" tab)

**KPI Cards Row:**

- [x] **Prediksi Bulan Depan**: `ml_predictions` value for `prediction_horizon = 'MONTH_NEXT'` — shows `predicted_value ± confidence_interval` in hrs. Falls back to moving average if ML not available.
- [x] **Tingkat Akurasi (Forecast Accuracy)**: Shows model MAPE from `ml_models.metrics->mape`. If fallback: shows "Moving Average" badge instead.
- [x] **Pola Musiman (Seasonal Pattern)**: Auto-detected: compares current month's historical average to overall average — "Puncak" if above average, "Normal" if within 10%, "Rendah" if below average.
- [x] **Arah Tren (Trend Direction)**: 3-month rolling trend direction: ↑ Meningkat | → Stabil | ↓ Menurun

**Charts:**

- [x] `Prediksi Jam Lembur Bulan Depan` — bar chart: each section's predicted hours for next month, with confidence interval error bars
- [x] `Prediksi Tren (6 Bulan)` — line chart: 3 months historical + 3 months projected. Historical = solid line, Projected = dashed. Optional: 90% confidence band shading.
- [x] `Analisis Pola Musiman` — line chart: all 12 months of average overtime hours (averaged across all years of history), showing the annual cycle. Highlight peak quarter (Q4 Oct–Dec typically).

**Seasonal Analysis Summary:**

- [x] 3 info cards: `Musim Puncak` (highest historical month on average), `Musim Rendah` (lowest), `Siklus Pola` ("12 Bulan" if pattern is annual, else "N/A")

#### Technical Tasks

- [x] `AnalyticsController@predictive` — `GET /analytics/predictive`
- [x] Seasonal pattern: `SELECT MONTH(operational_date) as month, AVG(monthly_total) FROM (monthly aggregation subquery) GROUP BY month`
- [x] Trend direction: `SELECT AVG(total) as avg FROM monthly_totals WHERE month >= now - 3 months`
- [x] Create `resources/js/Pages/Analytics/TabPredictive.vue`
- [x] Create `resources/js/Components/Analytics/ForecastBarChart.vue` — with error bars using Chart.js `errorBars` plugin or custom dataset
- [x] Create `resources/js/Components/Analytics/TrendProjectionChart.vue` — mixed historical + projected line
- [x] Create `resources/js/Components/Analytics/SeasonalPatternChart.vue` — 12-point annual cycle

---

### Story E09-08: Cost Analysis Tab (Analisis Biaya)

**As a** Finance Controller or Manager,  
**I want** a comprehensive cost analysis tab showing overtime spend by department, cost trends over 6 months, and budget vs actual comparisons,  
**So that** I can report on labor cost variances and track financial compliance.

**Story Points:** 8  
**Priority:** Must Have

#### Acceptance Criteria (maps to `analytic-and-decision.html` "Analisis Biaya" tab)

**KPI Cards:**

- [x] **Total Biaya Lembur**: `SUM(total_cost_snapshot)` for current month, approved items. Format: `Rp 125,5 Jt` (abbreviated millions)
- [x] **Sisa Anggaran (Rp)**: `planned_cost_idr - cumulative_cost_idr` from budgets. With mini progress bar (% consumed)
- [x] **Rata-rata Biaya per Karyawan**: `Total Cost / COUNT(DISTINCT employee_id)` — Format `Rp 2,5 Jt`
- [x] ~~**ROI Projection**~~ — **REMOVED per BA spec §4** — replaced with: **CapEx Ratio Cost**: `SUM(hours_project × rate) / SUM(total_cost) × 100%` in IDR terms

**Charts:**

- [x] `Biaya Lembur per Departemen` — horizontal bar chart: each department's total cost this month, sorted desc. Color-coded by budget compliance.
- [x] `Tren Biaya Lembur (6 Bulan)` — stacked area chart: 6 months of monthly cost, stacked by OpEx + CapEx cost. Shows cost trend visually.
- [x] `Anggaran vs Realisasi` — grouped bar chart: for each department, two bars — Planned Cost (Rp) vs Actual Cost (Rp). Red shading if over budget.

**Cost Breakdown Table:**

- [x] Columns: `Department`, `Total Hours`, `Avg Rate (Rp/jam)`, `Total Cost (Rp)`, `% of Budget`, `Tren` (sparkline or ↑↓ arrow)
- [x] All monetary values formatted as Rupiah (Indonesian locale)
- [x] Sortable columns
- [x] Footer row: Grand Total across all departments

#### Technical Tasks

- [x] `AnalyticsController@costAnalysis` — `GET /analytics/cost`
- [x] Aggregate: `SUM(total_cost_snapshot)` grouped by `department_id`, `fiscal_month`
- [x] Average rate: `SUM(total_cost_snapshot) / SUM(total_hours)` per department
- [x] Create `resources/js/Pages/Analytics/TabCostAnalysis.vue`
- [x] Create `resources/js/Components/Analytics/CostByDepartmentChart.vue`
- [x] Create `resources/js/Components/Analytics/CostTrendStackedChart.vue`
- [x] Create `resources/js/Components/Analytics/BudgetVsActualBarChart.vue`
- [x] Create `resources/js/Components/Analytics/CostBreakdownTable.vue`
- [x] Rupiah format: always use `Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', notation: 'compact', maximumFractionDigits: 1 })` for abbreviated values

---

### Story E09-09: Correlation & Pattern Analysis Tab (Korelasi & Pola)

**As a** Manager or Plant Engineer,  
**I want** to see how overtime correlates with production volume and quality metrics, and understand the optimal overtime level,  
**So that** I can make data-backed decisions about when additional overtime is productive vs. counterproductive.

**Story Points:** 8  
**Priority:** Should Have

#### Acceptance Criteria (maps to `analytic-and-decision.html` "Korelasi & Pola" tab)

**Charts:**

- [ ] `Lembur vs Volume Produksi` — scatter chart: each data point = one month for one section (X = production units, Y = overtime hours). Trend line overlay (linear regression). Correlation coefficient displayed below chart.
- [ ] `Lembur vs Metrik Kualitas` — scatter chart: (X = overtime hours per week, Y = quality defect rate or quality score if available from external feed). If quality data not available: show "Quality feed not connected — chart pending ERP integration" placeholder.
- [ ] `Level Lembur Optimal` — area chart with 3 zones: Under-utilized (< 12 hrs/week), Sweet Spot (12–18 hrs/week from BA spec), Over-threshold (> 20 hrs/week). Current section average plotted as a vertical marker.

**Correlation Matrix:**

- [ ] Color-coded table showing correlation coefficients between: Overtime ↔ Production, Overtime ↔ Quality, Overtime ↔ Efficiency, Overtime ↔ Cost
- [ ] Cell background: green (|r| > 0.7 = strong), blue (0.4–0.7 = moderate), gray (< 0.4 = weak)
- [ ] Legend below table
- [ ] Note: Correlation coefficients computed server-side using Pearson formula on historical monthly data

**Optimal Level Summary Cards:**

- [ ] 3 cards: `Sweet Spot Range` (12–18 hrs/week), `Peak Efficiency Point` (computed from data or hardcoded default 15 hrs), `Warning Threshold` (from `policy_thresholds.weekly_soft_limit_hours`)

#### Technical Tasks

- [ ] `AnalyticsController@correlation` — `GET /analytics/correlation`
- [ ] Pearson correlation computation (PHP server-side): `PearsonCorrelationService::compute(array $x, array $y): float`
- [ ] Linear regression for scatter trend line: compute slope/intercept from monthly historical data
- [ ] Create `resources/js/Pages/Analytics/TabCorrelation.vue`
- [ ] Create `resources/js/Components/Analytics/OvertimeProductionScatter.vue`
- [ ] Create `resources/js/Components/Analytics/OptimalLevelZoneChart.vue`
- [ ] Create `resources/js/Components/Analytics/CorrelationMatrixTable.vue`
- [ ] Quality data guard: if `production_quality_data` table doesn't exist, render placeholder card: `"Menunggu integrasi data kualitas dari ERP"`

---

### Story E09-10: What-If Scenario Simulation Tab (Simulasi Skenario)

**As a** Manager,  
**I want** to simulate different overtime scenarios by adjusting production volume and budget allocations,  
**So that** I can plan ahead for peak periods and evaluate the cost/headcount implications before committing to a schedule.

**Story Points:** 8  
**Priority:** Should Have

#### Acceptance Criteria (maps to `analytic-and-decision.html` "Simulasi Skenario" tab)

> ⚠️ **Pruning Note**: The prototype's ROI and Well-being Score calculations are REMOVED. This tab uses real business metrics only.

**Production Volume Planning Calculator:**

- [ ] Inputs: `Target Volume Produksi (unit)`, `Periode Produksi` (weekly/monthly/quarterly), `Section Produksi`
- [ ] On "Hitung": Uses the correlation coefficient from E09-09 to estimate required overtime hours: `estimated_hours = production_volume × labor_factor` where `labor_factor` is derived from historical average `overtime_hours / production_units` for the selected section
- [ ] Results cards: `Total Jam Lembur (hrs)`, `Estimasi Biaya (Rp)`, `Karyawan Dibutuhkan` (hours ÷ policy soft limit per person), `Efisiensi (%)` (estimated production per labor hour vs historical baseline)
- [ ] Results breakdown table: by category (Production/TPM/Project/Others) — proportion of estimated hours per category based on historical section average

**Scenario Builder:**

- [ ] Slider: Overtime Change % (-50% to +50%), updates `overtimeChangeValue` label reactively
- [ ] Input: Budget Allocation (Rp M)
- [ ] Dropdown: Target Department
- [ ] "Jalankan Skenario" button runs calculation
- [ ] Scenario result cards: `Projected Cost Impact (Rp)`, `Production Volume Impact (+/- %)`, `Burn Index Projection (%)`, `Safety Risk Score` (replaces Well-being — uses consecutive-week policy threshold)
- [ ] Scenario Comparison Chart: grouped bar chart comparing baseline vs current scenario vs up to 3 saved scenarios

**Saved Scenarios:**

- [ ] Manager can save a named scenario (name + inputs) — stored in `user_saved_scenarios` table or `users.preferences` JSON
- [ ] "Baseline Scenario" (current month actuals) always shown
- [ ] Saved scenarios listed; click to load back into the builder

#### Technical Tasks

- [ ] `ScenarioCalculatorService::calculate(array $inputs): ScenarioResult` — all calculation logic server-side
- [ ] `labor_factor` computation: `historical_ot_hours / historical_production_units` for section over last 12 months
- [ ] `safety_risk_score`: percentage of employees projected to exceed weekly policy limit based on scenario hours
- [ ] Route: `POST /analytics/scenario/calculate` → `AnalyticsScenarioController@calculate`
- [ ] Route: `POST /analytics/scenario/save` → save scenario to user preferences JSON
- [ ] Create `resources/js/Pages/Analytics/TabScenario.vue`
- [ ] Create `resources/js/Components/Analytics/ProductionCalculatorPanel.vue`
- [ ] Create `resources/js/Components/Analytics/ScenarioBuilderPanel.vue`
- [ ] Create `resources/js/Components/Analytics/ScenarioComparisonChart.vue`
- [ ] Slider reactive update: `watch(overtimeSlider, () => calculateProjectedCost())` — no server call, pure client-side math on already-loaded data

---

### Story E09-11: Key Insights & Management Actions Tab (Wawasan Kunci)

**As a** Manager or Plant Head,  
**I want** a consolidated insights panel that surfaces the most critical operational risks and a prioritized action items list,  
**So that** I can start every management meeting with a clear view of what needs immediate attention.

**Story Points:** 5  
**Priority:** Must Have

#### Acceptance Criteria (maps to `analytic-and-decision.html` "Wawasan Kunci" tab)

**Risk Indicators Panel:**

- [ ] Shows automatically generated risk items from system data — NOT hardcoded:
    - Budget Overrun Risk: auto-generated when any section has `burn_index_pct > burn_warning_pct`
    - Employee Burnout Alert: auto-generated when `consecutive_weeks_alert` threshold is exceeded (from Epic-06 `OvertimePolicyEvaluator`)
    - Efficiency Drop: auto-generated when anomaly detection flags a spike pattern (from Epic-08)
- [ ] Each risk item has: severity badge (Critical/Warning/Info), title, description, "View Details →" link to the relevant module
- [ ] Count badge at panel header: "X Critical, Y Warning"
- [ ] If no risks: "✅ Semua metrik dalam batas normal" green card

**Anomaly Detection Chart:**

- [ ] Line chart: daily overtime hours for the last 30 days with anomaly markers (red dots) on flagged days
- [ ] Horizontal band: mean ± 1 standard deviation shown as shaded band
- [ ] Points above the band = anomaly markers
- [ ] Summary below chart: "X unusual patterns detected in the last 30 days"

**Management Action Items Table:**

- [ ] Auto-generated list from system events (budget alerts, SPKL overdue, anomalies, burnout alerts)
- [ ] Columns: `Priority` (High/Medium/Low), `Action Item`, `Department`, `Impact`, `Deadline`, `Status`
- [ ] Status: `Pending | In Progress | Resolved`
- [ ] Manager can mark items as "In Progress" or "Resolved" — stored per user
- [ ] "Export Action Plan" button → CSV of current action items

#### Technical Tasks

- [ ] `AnalyticsController@insights` — `GET /analytics/insights` — aggregates risks from multiple sources
- [ ] `InsightAggregatorService::generate(int $departmentId, int $year, int $month): array` — queries `monthly_burn_snapshots`, `ml_anomaly_logs`, policy evaluator
- [ ] Route: `POST /analytics/action-items/{id}/status` → update manager's action item status (stored in `user_action_item_states` table or preferences JSON)
- [ ] Anomaly chart data: `ml_anomaly_logs` joined to `overtime_items` and `overtime_submissions`, last 30 days, group by date
- [ ] Create `resources/js/Pages/Analytics/TabInsights.vue`
- [ ] Create `resources/js/Components/Analytics/RiskIndicatorPanel.vue`
- [ ] Create `resources/js/Components/Analytics/AnomalyDetectionChart.vue` — line chart with anomaly scatter overlay (Chart.js multi-dataset)
- [ ] Create `resources/js/Components/Analytics/ManagementActionTable.vue`

---

### Story E09-12: Period Comparison Tab (Perbandingan Periode)

**As a** Plant Head or Finance Controller,  
**I want** to compare overtime metrics between two selected periods (year-over-year, month-over-month, or quarter-over-quarter),  
**So that** I can benchmark current performance against historical baselines and identify whether the business is improving or degrading.

**Story Points:** 8  
**Priority:** Should Have

#### Acceptance Criteria (maps to `analytic-and-decision.html` "Perbandingan Periode" tab)

**Comparison Configuration:**

- [ ] Dropdown: Comparison Type — `Year-over-Year | Month-over-Month | Quarter-over-Quarter | Department Benchmarking`
- [ ] Two date pickers: `Base Period` (month) and `Compare With` (month)
- [ ] On change: the 4 KPI cards and all charts below update automatically

**Comparison KPI Cards:**

- [ ] Total Hours Change: `(base_hours - compare_hours) / compare_hours × 100%` with ↑↓ direction
- [ ] Cost Change: same formula for `total_cost_idr`
- [ ] Efficiency Change: `(base_hours / base_production_units) - (compare_hours / compare_production_units)` — shows productivity per labor hour change
- [ ] Employee Count Change: difference in active employee count between periods

**Charts:**

- [ ] `Year-over-Year / Period Comparison Chart` — grouped bar chart: one group per month for the year, two bars (base year vs compare year) side by side. Line overlay for percentage change.
- [ ] `Department Benchmarking Chart` — horizontal stacked bar: all departments ranked by hours, with base vs compare stacked. Shows which departments grew/shrunk.
- [ ] Best/Average/Worst Performer summary: 3 cards below the benchmarking chart showing top/middle/bottom departments by Burn Index efficiency

**Best Practices Section:**

- [ ] 2 cards (like the prototype): highlight the top-performing department's strategy with a brief auto-generated insight: `"[Dept X] achieved the lowest cost-per-hour by averaging Y hrs/week — 23% below plant average"`

#### Technical Tasks

- [ ] `AnalyticsController@comparison` — `GET /analytics/comparison?base=2026-09&compare=2025-09&type=yoy`
- [ ] Period aggregation query: both periods aggregated to same metrics, returned as parallel arrays
- [ ] Create `resources/js/Pages/Analytics/TabComparison.vue`
- [ ] Create `resources/js/Components/Analytics/PeriodComparisonBarChart.vue`
- [ ] Create `resources/js/Components/Analytics/DepartmentBenchmarkChart.vue`
- [ ] Create `resources/js/Components/Analytics/BestPracticeCards.vue` — auto-generates insight text
- [ ] Reactive comparison: `watch([basePeriod, comparePeriod, comparisonType], () => fetchComparisonData())`

---

## Sprint 9–10 Breakdown

| Sprint Day         | Focus                                                    | Stories        |
| ------------------ | -------------------------------------------------------- | -------------- |
| Sprint 9, Day 1    | Chart.js infrastructure setup (mandatory first)          | E09-00         |
| Sprint 9, Day 2–3  | Dashboard KPI cards + mini sparklines                    | E09-01         |
| Sprint 9, Day 4–5  | Daily Burn Chart (main + section comparison)             | E09-02, E09-03 |
| Sprint 9, Day 6–8  | Bottom charts: Leaderboard + Category + Day Type + Trend | E09-04         |
| Sprint 9, Day 9–10 | Employee Summary Table + Dashboard final polish          | E09-05         |
| Sprint 10, Day 1   | Analytics page shell + tab navigation                    | E09-06         |
| Sprint 10, Day 2–3 | Predictive Analytics tab                                 | E09-07         |
| Sprint 10, Day 4–5 | Cost Analysis tab                                        | E09-08         |
| Sprint 10, Day 5–6 | Correlation & Pattern tab                                | E09-09         |
| Sprint 10, Day 7–8 | Scenario Simulation tab                                  | E09-10         |
| Sprint 10, Day 9   | Key Insights tab                                         | E09-11         |
| Sprint 10, Day 10  | Comparative Period tab + full regression testing         | E09-12         |

---

## Vue Component Inventory

All components to be created in this epic:

```
resources/js/
├── Components/
│   ├── Charts/
│   │   ├── BaseLineChart.vue          ← E09-00
│   │   ├── BaseBarChart.vue           ← E09-00
│   │   ├── BaseDonutChart.vue         ← E09-00
│   │   ├── BaseScatterChart.vue       ← E09-00
│   │   ├── BaseMiniSparkline.vue      ← E09-00
│   │   └── ChartSkeleton.vue          ← E09-00
│   ├── Dashboard/
│   │   ├── KpiCardProduction.vue      ← E09-01
│   │   ├── KpiCardWorkingDays.vue     ← E09-01
│   │   ├── KpiCardManPower.vue        ← E09-01
│   │   ├── KpiCardBurnIndex.vue       ← E09-01
│   │   ├── DailyBurnLineChart.vue     ← E09-02
│   │   ├── SectionBurnComparisonChart.vue ← E09-03
│   │   ├── OvertimeLeaderboardChart.vue   ← E09-04
│   │   ├── CategoryDistributionDonut.vue  ← E09-04
│   │   ├── TrendWorkingTimeChart.vue      ← E09-04
│   │   ├── DailyIndexTrendChart.vue       ← E09-04
│   │   ├── DayTypeBreakdownChart.vue      ← E09-04
│   │   ├── EmployeeSummaryTable.vue       ← E09-05
│   │   ├── MiniProgressBar.vue            ← E09-05
│   │   └── MiniCategoryBar.vue            ← E09-05
│   └── Analytics/
│       ├── ForecastBarChart.vue           ← E09-07
│       ├── TrendProjectionChart.vue       ← E09-07
│       ├── SeasonalPatternChart.vue       ← E09-07
│       ├── CostByDepartmentChart.vue      ← E09-08
│       ├── CostTrendStackedChart.vue      ← E09-08
│       ├── BudgetVsActualBarChart.vue     ← E09-08
│       ├── CostBreakdownTable.vue         ← E09-08
│       ├── OvertimeProductionScatter.vue  ← E09-09
│       ├── OptimalLevelZoneChart.vue      ← E09-09
│       ├── CorrelationMatrixTable.vue     ← E09-09
│       ├── ProductionCalculatorPanel.vue  ← E09-10
│       ├── ScenarioBuilderPanel.vue       ← E09-10
│       ├── ScenarioComparisonChart.vue    ← E09-10
│       ├── RiskIndicatorPanel.vue         ← E09-11
│       ├── AnomalyDetectionChart.vue      ← E09-11
│       ├── ManagementActionTable.vue      ← E09-11
│       ├── PeriodComparisonBarChart.vue   ← E09-12
│       ├── DepartmentBenchmarkChart.vue   ← E09-12
│       └── BestPracticeCards.vue          ← E09-12
└── Pages/
    ├── Dashboard/
    │   └── Index.vue                  ← E09-01 to E09-05
    └── Analytics/
        ├── Index.vue                  ← E09-06 (shell)
        ├── TabPredictive.vue          ← E09-07
        ├── TabCostAnalysis.vue        ← E09-08
        ├── TabCorrelation.vue         ← E09-09
        ├── TabScenario.vue            ← E09-10
        ├── TabInsights.vue            ← E09-11
        └── TabComparison.vue          ← E09-12
```

---

## Backend API Endpoints Summary

| Route                                 | Controller Method                           | Used By                 |
| ------------------------------------- | ------------------------------------------- | ----------------------- |
| `GET /dashboard`                      | `DashboardController@index`                 | Dashboard page load     |
| `GET /dashboard/kpi-cards`            | `DashboardController@kpiCards`              | KPI card partial reload |
| `GET /dashboard/charts/daily-burn`    | `DashboardController@dailyBurnChart`        | E09-02                  |
| `GET /dashboard/charts/section-burn`  | `DashboardController@sectionBurnComparison` | E09-03                  |
| `GET /dashboard/charts/leaderboard`   | `DashboardController@leaderboard`           | E09-04                  |
| `GET /dashboard/charts/category`      | `DashboardController@categoryDistribution`  | E09-04                  |
| `GET /dashboard/charts/trend-working` | `DashboardController@trendWorkingTime`      | E09-04                  |
| `GET /dashboard/charts/daily-index`   | `DashboardController@dailyIndexTrend`       | E09-04                  |
| `GET /dashboard/charts/day-type`      | `DashboardController@dayTypeBreakdown`      | E09-04                  |
| `GET /dashboard/employee-summary`     | `DashboardController@employeeSummaryTable`  | E09-05                  |
| `GET /analytics`                      | `AnalyticsController@index`                 | Analytics page          |
| `GET /analytics/predictive`           | `AnalyticsController@predictive`            | E09-07                  |
| `GET /analytics/cost`                 | `AnalyticsController@costAnalysis`          | E09-08                  |
| `GET /analytics/correlation`          | `AnalyticsController@correlation`           | E09-09                  |
| `POST /analytics/scenario/calculate`  | `AnalyticsScenarioController@calculate`     | E09-10                  |
| `POST /analytics/scenario/save`       | `AnalyticsScenarioController@save`          | E09-10                  |
| `GET /analytics/insights`             | `AnalyticsController@insights`              | E09-11                  |
| `GET /analytics/comparison`           | `AnalyticsController@comparison`            | E09-12                  |

---

## Performance Requirements

These dashboards are loaded by every Manager at shift start (07:00, 15:00 WIB). Performance is non-negotiable.

| Screen               | Max Load Time | Strategy                                                   |
| -------------------- | ------------- | ---------------------------------------------------------- |
| Dashboard main page  | < 1.5s        | Pre-aggregated `monthly_burn_snapshots` + lazy-load charts |
| Daily Burn Chart     | < 500ms       | Indexed query on `operational_date + section_id + status`  |
| Analytics tabs (all) | < 2s per tab  | Server-side aggregation, no raw item-level joins on load   |
| Scenario calculation | < 300ms       | Pure computation, no DB write during calculation           |
| Export (PDF/CSV)     | < 5s          | Streaming response, no memory buffer                       |

---

## Risks & Assumptions

| Risk                                                      | Likelihood | Mitigation                                                                                             |
| --------------------------------------------------------- | ---------- | ------------------------------------------------------------------------------------------------------ |
| Production volume data not available (no ERP integration) | High       | All production-related charts degrade gracefully to "ERP feed not connected" placeholder — never crash |
| Quality metrics data not available                        | High       | Correlation tab's quality chart shows placeholder — rest of tab still functional                       |
| Chart.js version incompatibilities with vue-chartjs       | Low        | Lock exact versions in `package.json`; test immediately in E09-00                                      |
| Dashboard slow on first load with many sections           | Medium     | Lazy-load charts below the fold; KPI cards load first                                                  |
| Scenario calculation producing unrealistic numbers        | Medium     | Add input sanity bounds validation; cap production volume at `10× historical max`                      |

---

## Definition of Done — Epic-09

- [ ] Executive Dashboard loads in < 1.5 seconds for a department with 12 sections
- [ ] All 7 dashboard charts render correctly with real data from approved `overtime_items`
- [ ] Employee summary table is searchable client-side without server round-trip
- [ ] Analytics page 6-tab navigation works; tab state persists in URL query param
- [ ] Predictive tab shows next-month forecast (ML value or moving average fallback)
- [ ] Cost tab shows correct IDR totals formatted in Indonesian locale
- [ ] Scenario calculator produces reasonable estimates using historical labor factor
- [ ] Risk Indicators auto-generate from real system events (not hardcoded)
- [ ] Period comparison works for YoY with correct % change calculations
- [x] NO prototype pruned items appear: no ROI formula, no Well-being Score, no difficulty tier pricing
- [x] `pnpm lint` passes with zero errors
- [x] `pnpm build` succeeds (bundle size checked — chart.js adds ~150kb gzip)
- [x] All chart components show loading skeleton when data is fetching
- [x] All chart components show "Belum ada data" empty state when no records exist
