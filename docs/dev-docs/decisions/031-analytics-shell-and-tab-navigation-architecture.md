# ADR-031: Analytics Page Shell and Client-Side Tab Navigation Architecture

**Date:** 2026-09-10  
**Status:** accepted  
**Supersedes:** Extends ADR-028, ADR-029, and ADR-030

## Context

Following the completion of the Executive Operational Dashboard (Stories E09-01 through E09-05 under Module A), Story E09-06 introduces **Module B: Analytics & Decision Intelligence** (`/analytics`) from the `AnalyticDecision/analytic-and-decision.html` prototype.

Unlike the daily operational dashboard (`/dashboard`), which focuses on rapid 60-second shift monitoring, the Analytics & Decision Intelligence hub serves executive plant heads and finance controllers who perform deep monthly and quarterly strategic evaluations. It comprises 6 distinct analytical domains:

1. `Prediksi Lembur` (Predictive Analytics & ML Forecasting)
2. `Analisis Biaya` (Cost Audit & Financial Breakdown)
3. `Korelasi & Pola` (Bivariate Correlation & Productivity Frontiers)
4. `Simulasi Skenario` (What-if Production Volume & Staffing Simulation)
5. `Wawasan Kunci` (Automated Risk Indicators & Fatigue Alerts)
6. `Perbandingan Periode` (Cross-Period & Departmental Benchmarking)

Key design requirements and constraints established in `docs/scrum/Epic-09-ux-plan.md` include:

- Single URL route (`GET /analytics`) with role-based access for Admin and Manager only (`role:admin,manager`).
- Exactly 1 new navigation item in the sidebar for Admin and Manager (**Analitik & Keputusan** / `Analytics & Decision`). Operators and Team Leaders must not see this item and must be denied access with HTTP 403.
- Fast, client-side tab switching without full-page reloads, while maintaining shareable deep-linking via query parameters (`?tab=...`).
- Global persistent filter bar (Department selector and Date range) that scopes all 6 tabs simultaneously.
- Unified executive export mechanism for 1-page summary PDF and raw tab CSV data via `GET /analytics/export`.

## Decision

We designed and implemented a unified, reactive page shell architecture for `/analytics`:

1. **Single Entry Controller and Inertia View**:
    - `AnalyticsController@index` serves as the single entry point.
    - It validates and normalizes the `tab` parameter against an allowed whitelist (`predictive`, `cost`, `correlation`, `scenario`, `insights`, `comparison`), falling back gracefully to `predictive`.
    - It scopes departments based on role: Admins can select any active department or "all", while Managers are locked to their assigned department.
    - It renders `resources/js/pages/Analytics/Index.vue`.

2. **Client-Side Tab Architecture with URL Synchronization**:
    - The shell imports 6 discrete sub-tab components (`TabPredictive.vue`, `TabCostAnalysis.vue`, `TabCorrelation.vue`, `TabScenario.vue`, `TabInsights.vue`, `TabComparison.vue`) rendered dynamically using `<component :is="tabComponents[activeTab]" :filters="filters" />`.
    - Tab switching is instantaneous via local reactive state (`activeTab`).
    - Deep linking and URL synchronization are maintained via `window.history.replaceState` and Inertia partial reloading on filter changes (`preserveState: true`, `preserveScroll: true`).
    - Navigating directly to `/analytics?tab=cost` automatically activates the corresponding tab on initial mount.

3. **Global Filter Bar with Live Operational Clock**:
    - Positioned persistently beneath the header.
    - Houses the Department selector, Date Range picker (Month / Year), and Reset Filters button.
    - Includes live WIB operational clock integration via `useShiftInfo()`.

4. **Dedicated Export Popover & Service**:
    - Implemented `ExportReportPopover.vue` anchored to the "Export Report" (`data-test="btn-analytics-export"`) header action.
    - Provides two export formats:
        - **PDF (Executive 1-Page Summary)**: Generated via `Barryvdh\DomPDF\Facade\Pdf` using `resources/views/pdf/analytics-executive-summary.blade.php`, styled with ISUZU brand identity (`#cc0000`).
        - **CSV (Raw Tab Data)**: High-speed UTF-8 streamed CSV download via `AnalyticsExportService`.
    - `AnalyticsController@export` enforces department authority, preventing Managers from exporting data outside their department.

5. **Strict Navigation Footprint & Security Boundaries**:
    - Sidebar item `Analitik & Keputusan` (`nav-analytics`) is rendered exclusively for `admin` and `manager` roles.
    - Route group in `routes/web.php` enforces `role:admin,manager`. Requests by Team Leaders or Operators receive an immediate HTTP 403 Forbidden.

## Consequences

### Positive

- **Fluid User Experience**: Switching between complex analytical perspectives takes 0ms without server round-trips or re-rendering whole pages.
- **Deep Linking & Bookmarking**: Analysts can share direct URLs (e.g., `/analytics?tab=scenario&department_id=2`) with full state hydration.
- **Clean Component Encapsulation**: Each of the 6 tabs is encapsulated in its own component, providing modular scaffolding for Stories E09-07 through E09-12.
- **Consistent Export Pipeline**: Centralizes reporting outputs into structured executive PDFs and tabular CSVs.

### Negative / Trade-offs

- Sub-components for all tabs are bundled into the page shell chunk or split via dynamic imports; however, Vite tree-shaking and modern code splitting keep the bundle size small (~12.7 kB for `Index.vue`).
