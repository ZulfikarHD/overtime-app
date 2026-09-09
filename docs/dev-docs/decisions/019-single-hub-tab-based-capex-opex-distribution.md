# ADR-019: Single-Hub Tab-Based CapEx vs OpEx Distribution Panel

**Date:** 2026-09-09  
**Status:** accepted  
**Supersedes:** None

## Context

In heavy manufacturing and commercial automotive assembly plants (such as ISUZU Astra Motor Indonesia), labor overtime splits strictly into two distinct accounting streams:

1. **Operating Expenditure (OpEx)**: Routine line production overruns, scheduled Total Productive Maintenance (TPM), and standard shopfloor support categorized as routine operating costs.
2. **Capital Expenditure (CapEx)**: Factory automation upgrades, tooling jigs, robot cell commissioning, and asset additions, which must be capitalized on the corporate balance sheet under IAS 16 / PSAK 16 compliant accounting standards.

In early scrum planning ([Epic-05.md](../../scrum/Epic-05.md)), a standalone controller route `GET /reports/capex-opex` was tentatively proposed. However, the comprehensive **Epic-05 UX Plan** (`Epic-05-ux-plan.md` Sections 1.2, 1.3, and 5.1) established a **hard architectural constraint**:

> _"All budget burn, departmental analytics, and labor capitalization views MUST reside within the unified Burn Index Hub (`/dashboard/burn-index`). Do NOT split these views into separate controller routes or standalone reports."_

Key architectural requirements addressed:

1. **Single Command Center (Zero Context Loss)**: Keep Plant Administrators, Finance Controllers, and Department Managers inside `/dashboard/burn-index?tab=capex-opex` with preserved filters and smooth client-side tab switching.
2. **Backward & Legacy Route Compatibility**: Support historical or external links pointing to `/reports/capex-opex` via seamless HTTP 301/302 redirects without code duplication.
3. **Multi-Period Date Granularity**: Support `Bulan Ini (Default)`, `Tahun Berjalan (YTD)`, and `Kustom` date filtering evaluated in `Asia/Jakarta (WIB)`.
4. **Interactive Visualization Engine**: Render accessible Chart.js donut and bar charts with high-density tabular numeral layouts (`font-mono tabular-nums`) and standard ISUZU color tokens.
5. **CapEx Project Variance Tracking**: Provide real-time variance tracking ($\text{Logged Hours} - \text{Allocated Hours}$) with emerald green badges for under-budget projects and ISUZU Red badges for over-budget overruns.

## Decision

We implemented the **Single-Hub Tab-Based CapEx vs OpEx Distribution Panel** (E05-03) adhering to the Epic-05 UX Plan:

1. **Unified Hub Tab Routing & Controller Integration**:
    - Resides on `/dashboard/burn-index?tab=capex-opex` inside `DashboardBurnIndexController@index`.
    - Added legacy redirect in `routes/web.php`:
        ```php
        Route::redirect('/reports/capex-opex', '/dashboard/burn-index?tab=capex-opex');
        ```
    - Enhanced `DashboardBurnIndexController@index` to accept `range_type`, `start_date`, and `end_date`, passing them down to `MonthlySnapshotService::getDashboardData()`.

2. **Backend Aggregation Service (`MonthlySnapshotService::getCapexOpexBreakdown`)**:
    - Queries approved `overtime_items` within resolved date range (`month`, `ytd`, or `custom`) scoped to user role (`Team Leader` -> section, `Manager` -> department, `Admin` -> target department).
    - Computes macro metrics: `capex_hours`, `opex_hours`, `total_hours`, `capex_ratio_pct` (CALC-07), `opex_ratio_pct`, `capex_cost_idr`, `opex_cost_idr`, `total_cost_idr`.
    - Aggregates side-by-side section comparison for bar chart visualization.
    - Computes CapEx project variance: `variance_hours = cumulative_logged_hours - allocated_labor_hours`.

3. **High-Density Industrial Frontend Architecture (`CapexOpexTab.vue`)**:
    - **Toolbar**: Date range filter pills (`Bulan Ini`, `Tahun Berjalan (YTD)`, `Kustom`) with inline start/end date inputs and apply button.
    - **Capitalization Summary KPI Cards**: 4-card grid displaying Total Hours, CapEx Hours with formula tooltip (`(Total Jam Proyek ÷ Total Seluruh Jam) × 100%`), OpEx Hours, and Capitalization Compliance.
    - **Empty State**: Friendly banner (`Belum ada jam lembur CapEx pada periode ini.`) displayed when CapEx hours equal zero.
    - **Distribution Charts**:
        - `CapexOpexDonutChart.vue`: Chart.js Doughnut with Sky Blue (`#0284c7`) for CapEx and Slate (`#64748b`) for OpEx, center CapEx % readout, and interactive tooltip displaying Rupiah amounts.
        - `CapexOpexSectionBarChart.vue`: Side-by-side bars per section comparing CapEx and OpEx hours.
    - **Project Variance Table (`CapexProjectTable.vue`)**:
        - High-density table featuring monospace project codes, asset tags, logged period hours, cumulative hours, allocated quota, progress bar, status, and variance badge (green negative for under budget, red positive for over budget).
        - Live client-side search across project code, name, and asset tags.

## Consequences

### Positive

- **Full UX Plan Compliance**: Adheres to the single-hub constraint and eliminates navigation fragmentation.
- **Accounting Audit Readiness**: Provides finance controllers with immediate visibility into labor capitalization ratios for accounting compliance.
- **Zero Query Duplication**: Leverages existing indexed relationship between `overtime_submissions` and `overtime_items`.
- **Seamless Responsive Experience**: Fast client-side partial reloads using Inertia Wayfinder routes without page blinking.

### Negative

- High data volumes across plant-wide multi-year custom ranges can generate heavier aggregation queries. To mitigate, date boundaries default to single-month intervals with indexed foreign keys.
