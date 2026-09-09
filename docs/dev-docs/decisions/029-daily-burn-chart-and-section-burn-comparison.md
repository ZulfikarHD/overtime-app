# ADR-029: Daily Burn Line Chart and Section Burn Comparison

**Date:** 2026-09-09  
**Status:** accepted  
**Supersedes:** None (extends ADR-004 and ADR-028)

## Context

Following the establishment of shared Chart.js infrastructure in ADR-028, Epic-09 requires two primary operational analytics widgets on the Executive Operational Dashboard (`/dashboard`):

1. **Daily Burn Chart Index (E09-02)**: Plant supervisors and department managers need a continuous trajectory of daily cumulative approved overtime hours plotted against their monthly budget ceiling, with early-warning projection of potential overruns before month-end closure.
2. **Section Burn Comparison (E09-03)**: Department managers supervising multiple production lines need an instant comparative ranking of all active sections to identify which sections are burning budget faster than planned pace.

The implementation must strictly adhere to the UX Plan (`docs/scrum/Epic-09-ux-plan.md`):

- Single-surface operational integration on `/dashboard` with 0-click depth for primary operational awareness.
- 1-click drilldown into Section Burndown Cockpit (`/dashboard/burn-index?section={id}`).
- Zero unpruned prototype metrics (no synthetic ROI or well-being scores).
- Industrial ergonomics: Asia/Jakarta WIB timezone, monospace tabular numbers, and ISUZU factory color standards.

## Decision

1. **Daily Burn Line Chart (`DailyBurnLineChart.vue`)**:
    - Provide a full-width hero line chart (~384px height / `h-96`) directly below the four macro KPI cards on `/dashboard`.
    - Implement three distinct series:
        - **Plan (Linear Budget)**: Dashed blue line representing evenly distributed monthly planned hours across days 1–31.
        - **Actual (Realization)**: Solid line color-coded by the active Burn Index zone, plotting daily cumulative approved overtime up to today's date.
        - **ML Trajectory (Projection)**: Dotted violet line forecasting the month-end trajectory sourced from `ml_predictions` (or linear run-rate velocity).
    - Implement a native Chart.js `beforeDraw` canvas plugin that renders the horizontal 100% Budget Ceiling threshold line and fills overrun area above the ceiling with soft red warning shading (`rgba(220, 38, 38, 0.08)`).
    - Integrate month navigation controls (`←` / `→`) and section filter dropdown within the card header.

2. **Section Burn Comparison Chart (`SectionBurnComparisonChart.vue`)**:
    - Implement a horizontal bar chart ranking all active sections in descending order of Burn Index percentage (`burn_index_pct`).
    - Color code bars according to standardized burn zones: Green (`<85%`), Blue (`85–100%`), Amber (`101–115%`), and ISUZU Red (`>115%`).
    - Enable 1-click navigation to the section's weekly burndown cockpit on bar click, complemented by high-density section cards below the chart for plant tablet accessibility.

3. **Backend Service Layer (`DashboardKpiService.php`) & Controllers**:
    - Add `getDailyBurnChart()` and `getSectionBurnComparison()` to `DashboardKpiService`.
    - Update `DashboardController@index` to provide `dailyBurnChart` and `sectionBurnComparison` props on initial render.
    - Register dedicated JSON endpoints `GET /dashboard/charts/daily-burn` and `GET /dashboard/charts/section-burn` for dynamic partial updates.

## Consequences

### Positive

- **Immediate Overrun Visibility**: Supervisors immediately recognize whether current burn pace is outpacing the linear budget curve days before month-end.
- **Actionable Ranking**: By ordering sections descending by burn percentage, problematic sections are immediately visible at the top of the chart without sorting or filtering.
- **Seamless Drill-Down**: Direct click from comparison bars to section detail eliminates friction and adheres to the 3-click ergonomics standard.
- **Lightweight Architecture**: Native plugin implementation avoids heavyweight external dependencies, maintaining fast page rendering.

### Negative

- **Chart Canvas Overhead**: Rendering two complex Chart.js instances concurrently on the dashboard increases initial DOM mount time slightly (~15-20ms).

### Neutral

- Historical months display the full realization curve across all days of the month without projecting ML trajectories, as past months are already finalized.
