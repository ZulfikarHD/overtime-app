# ADR-028: Shared Vue Chart.js Infrastructure and Operational KPI Cards

**Date:** 2026-09-09  
**Status:** accepted  
**Supersedes:** None (extends ADR-004)

## Context

In Epic 09 (Executive Dashboard & Analytics Foundation), the application requires high-density operational telemetry on `/dashboard` (E09-01) and subsequent deep-dive analytical charts (E09-02 through E09-07). Previously, individual dashboard widgets each independently imported and registered Chart.js plugins, causing code duplication, inconsistent styling, and potential memory leaks across fast Inertia page transitions.

Furthermore, manufacturing plant dashboards require zero-crash guarantees when external feeds (such as vehicle production volume from plant ERP systems) are disconnected or intermittent. The UX plan (`docs/scrum/Epic-09-ux-plan.md`) strictly dictates a Two-Surface Architecture, monospace tabular numerals, and zero menu proliferation.

## Decision

1. **Shared Vue Chart.js Infrastructure (`resources/js/components/charts/`)**:
    - Centralize Chart.js tree-shakeable component registration and plant defaults (Instrument Sans typography, responsive mode, slate grid lines) in `resources/js/plugins/chartjs.ts`.
    - Provide standard reusable wrappers: `BaseLineChart`, `BaseBarChart`, `BaseDonutChart`, `BaseScatterChart`, and `BaseMiniSparkline`.
    - Provide `ChartSkeleton` as a unified pulsing loading placeholder matching chart heights.
    - Establish `useChartTheme` composable with ISUZU plant color tokens: Primary (`#2563eb`), Success (`#16a34a`), Warning (`#d97706`), ISUZU Red (`#cc0000`), CapEx (`#7c3aed`), OpEx (`#0891b2`), HKN (`#3b82f6`), and HLR (`#f59e0b`).

2. **Executive Operational Header KPI Cards (`resources/js/components/dashboard/`)**:
    - Implement four cards on `/dashboard`:
        - **Card 1 (Production Volume)**: 14-day sparkline with daily target units (1,450 units) and ERP degradation fallback banner (`N/A — Integrasi data produksi ERP belum terhubung`).
        - **Card 2 (Working Days)**: Sourced from `operational_calendars` table with HKN progress and weekly distribution bars.
        - **Card 3 (Active Manpower)**: Sourced from `employees` active count with section distribution bars.
        - **Card 4 (Burn Index Plan vs Actual)**: Dual progress bars (100% Plan baseline vs Actual realization %) color-coded by operational burn zone.
    - Provide date picker and department selector with Inertia partial reloading (`only: ['kpiCards', 'selectedDepartmentId', 'selectedDate']`).

3. **Backend Service & Routing**:
    - Encapsulate data calculation and defensive degradation in `app/Services/Analytics/DashboardKpiService.php`.
    - Expose `GET /dashboard` (Inertia page) and `GET /dashboard/kpi-cards` (`dashboard.kpi-cards` JSON endpoint).

## Consequences

### Positive

- **Consistent Industrial Visual Language**: All charts throughout the application share the same typography, tooltip appearance, and color tokens.
- **Zero-Crash Resilience**: If plant ERP feeds are offline, the dashboard gracefully displays a neutral informational badge without throwing 500 errors.
- **Sub-Second Partial Reloads**: Changing filters uses Inertia partial reloads with visual skeletons, avoiding full page redraws.
- **Strict UX Plan Compliance**: Adheres to the Two-Surface constraint and eliminates prototype clutter.

### Negative

- **Client Bundle Size**: Adding Chart.js bundle adds approximately ~60 kB (gzipped) to the asset footprint; mitigated by selective tree-shakeable imports.

### Neutral

- Existing stand-alone charts can progressively migrate to base wrappers over future sprint refactors.
