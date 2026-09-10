# ADR-034: Cost Analysis OpEx vs CapEx Segregation and Budget Variance Architecture

**Date:** 2026-09-10  
**Status:** accepted

## Context

Story [E09-08] introduces the **Cost Analysis Tab** (_Analisis Biaya_) as Tab 2 within the unified Analytics & Decision Intelligence Hub (`/analytics?tab=cost`).
Plant Finance Controllers and Department Managers require detailed visibility into overtime expenditures, labor cost capitalization (CapEx vs OpEx), remaining budget pacing, and departmental cost efficiency.

Key engineering challenges:

1. **Financial Precision**: Must strictly rely on immutable historical snapshot rates (`hourly_rate_snapshot`, `total_cost_snapshot`) rather than live employee rates to prevent retroactive accounting distortions.
2. **CapEx vs OpEx Separation**: Plant overtime serves both regular production/TPM (OpEx) and capitalized machinery/line expansion projects (CapEx). Labor capitalization must be accurately separated at the item level.
3. **Prototype Pruning Compliance**: The initial prototype contained a synthetic "ROI Projection" card. Per BA specification §4 and Epic-09 UX constraints, synthetic metrics must be pruned and replaced with concrete, auditable financial indicators: **CapEx Cost Ratio** (`SUM(hours_project × rate) / SUM(total_cost) × 100%`).
4. **Visual Ergonomics**: Monospace tabular numerals (`font-mono tabular-nums`), Indonesian Rupiah currency formatting (`Rp 125,5 Jt` for compact cards, full `Rp 125.500.000` for audit tables), and standard ISUZU color palettes (ISUZU Red, Sky Blue/Violet for CapEx, Slate/Cyan for OpEx).

## Decision

We implemented a dedicated service-oriented architecture centered on `CostAnalysisService` and reactive Vue components:

1. **Immutable Snapshot Aggregation**:
    - `CostAnalysisService::getCostData()` aggregates strictly approved overtime records from `overtime_items` joined with `overtime_submissions` within the specified date range.
    - OpEx cost is computed as: `(hours_production + hours_tpm + hours_others) × hourly_rate_snapshot`.
    - CapEx cost is computed as: `hours_project × hourly_rate_snapshot`.
    - Average rate per hour is computed as: `total_cost / total_hours`.

2. **Budget Variance & Burn Zone Classification**:
    - Matches planned budget from `overtime_budgets` for the corresponding fiscal year and month.
    - Computes remaining budget (`planned_cost - total_cost`) and consumption percentage (`total_cost / planned_cost × 100%`).
    - Categorizes budget status into 4 discrete burn zones:
        - `safe`: < 85% consumed (Emerald green)
        - `on_track`: 85% – 100% consumed (ISUZU Blue)
        - `warning`: 100% – 110% consumed (Amber orange)
        - `danger`: > 110% consumed / over budget (ISUZU Red `#cc0000`)

3. **6-Month Historical Stacked Trend**:
    - Generates a chronological 6-month sequence ending at the filtered period.
    - Decomposes each month into OpEx (Slate `#64748b`) and CapEx (Sky Blue `#0284c7`) stacked area datasets with translucent fills (`0.25` opacity) and smooth cubic interpolation (`tension: 0.3`).

4. **Compact Currency Formatter**:
    - Added `formatCompactRupiah(amount)` in `resources/js/lib/formatters.ts` using `Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', notation: 'compact', maximumFractionDigits: 1 })`.
    - Produces localized strings such as `Rp 125,5 Jt` or `Rp 2,5 M`.

5. **Role-Based Scoping**:
    - Managers are strictly locked to their assigned `department_id` in `CostAnalysisService`. Any requested filter override is discarded in favor of `$user->department_id`.
    - Admins can query all departments or filter down to a specific department.

## Consequences

### Positive

- **Audit-Grade Accounting**: Complete alignment with factory accounting principles; no synthetic or placeholder data is presented.
- **Immediate Variance Detection**: Horizontal bar chart and grouped bar chart immediately highlight departments exceeding budgeted thresholds with red deficit coloring.
- **Fast Client Interaction**: High-density table features client-side sorting and text filtering with zero server round-trips.
- **Unified Export**: Executive PDF and streamed CSV exports consume the exact same `CostAnalysisService` logic, ensuring consistency between on-screen analytics and downloaded reports.

### Negative

- Overtime submissions that are still pending verification/approval do not appear in cost analysis, by design.

### Neutral

- Historical trend queries query across up to 6 prior calendar months; optimized with composite index coverage on `(department_id, status, operational_date)`.
