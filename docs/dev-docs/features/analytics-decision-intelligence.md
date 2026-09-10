# Analytics & Decision Intelligence Hub

## Overview

The Analytics & Decision Intelligence Hub (`/analytics`) is PT Isuzu Astra Motor Indonesia's strategic decision support screen (Module B of Epic-09). Designed for Plant General Managers, Department Heads, and Finance Controllers, it consolidates predictive machine learning forecasts, financial OpEx/CapEx breakdowns, productivity sweet-spot correlations, what-if workload simulators, fatigue risk indicators, and cross-departmental benchmarking under a unified 6-tab interface.

## Architecture Diagram

```mermaid
flowchart TD
    subgraph Client [Vue 3 Inertia Client]
        Nav["AppSidebar (Admin & Manager)"] -->|Clicks 'Analitik & Keputusan'| Shell["Pages/Analytics/Index.vue"]
        Shell --> Header["Page Header + Live WIB Clock"]
        Shell --> FilterBar["Global Filter Bar (Dept & Date Range)"]
        Shell --> ExportBtn["Export Report Popover (PDF / CSV)"]
        Shell --> TabNav["6-Tab Navigation (?tab=...)"]

        TabNav --> T1["TabPredictive.vue (?tab=predictive)"]
        TabNav --> T2["TabCostAnalysis.vue (?tab=cost)"]
        TabNav --> T3["TabCorrelation.vue (?tab=correlation)"]
        TabNav --> T4["TabScenario.vue (?tab=scenario)"]
        TabNav --> T5["TabInsights.vue (?tab=insights)"]
        TabNav --> T6["TabComparison.vue (?tab=comparison)"]
    end

    subgraph Backend [Laravel Backend]
        WebRoutes["routes/web.php (role:admin,manager)"] --> AnalyticsCtrl["AnalyticsController@index"]
        ExportRoute["GET /analytics/export"] --> AnalyticsCtrlExport["AnalyticsController@export"]
        AnalyticsCtrlExport --> ExportService["AnalyticsExportService (DomPDF / Streamed CSV)"]
        AnalyticsCtrl --> InertiaRender["Inertia::render('Analytics/Index')"]
    end

    Shell <-->|Query Sync & Partial Reload| WebRoutes
```

## Data Model

```mermaid
erDiagram
    DEPARTMENT ||--o{ SECTION : contains
    DEPARTMENT ||--o{ OVERTIME_BUDGET : plans
    SECTION ||--o{ OVERTIME_SUBMISSION : submits
    OVERTIME_SUBMISSION ||--o{ OVERTIME_ITEM : details
    DEPARTMENT ||--o{ MONTHLY_BURN_SNAPSHOT : records
    DEPARTMENT ||--o{ ML_PREDICTION : forecasts
```

## Key Files & UI Mapping

| Layer            | File / Route / Menu                                         | Purpose                                         |
| :--------------- | :---------------------------------------------------------- | :---------------------------------------------- |
| Sidebar Menu     | `Analitik & Keputusan` (`testId: 'nav-analytics'`)          | User entry point in UI (Admin & Manager only)   |
| Page Component   | `resources/js/pages/Analytics/Index.vue`                    | Master shell, header, filter bar, tab switcher  |
| Sub-Tab 1        | `resources/js/pages/Analytics/TabPredictive.vue`            | Predictive analytics & ML forecasting           |
| Sub-Tab 2        | `resources/js/pages/Analytics/TabCostAnalysis.vue`          | Overtime financial breakdown & OpEx/CapEx audit |
| Sub-Tab 3        | `resources/js/pages/Analytics/TabCorrelation.vue`           | Bivariate correlation & productivity sweet spot |
| Sub-Tab 4        | `resources/js/pages/Analytics/TabScenario.vue`              | Production volume & workload scenario simulator |
| Sub-Tab 5        | `resources/js/pages/Analytics/TabInsights.vue`              | Automated risk indicators & fatigue alerts      |
| Sub-Tab 6        | `resources/js/pages/Analytics/TabComparison.vue`            | Period comparison & departmental benchmarking   |
| Export Component | `resources/js/components/analytics/ExportReportPopover.vue` | Dropdown trigger for PDF and CSV exports        |
| Controller       | `app/Http/Controllers/AnalyticsController.php`              | Handles page rendering and export requests      |
| Export Service   | `app/Services/Analytics/AnalyticsExportService.php`         | Generates executive PDF and streamed UTF-8 CSV  |
| PDF Template     | `resources/views/pdf/analytics-executive-summary.blade.php` | Executive A4 summary layout with ISUZU branding |

## Flow Explanation

1. **User triggers navigation**: An Admin or Manager clicks **Analitik & Keputusan** in the sidebar. (Team Leaders and Operators do not have this link and receive HTTP 403 if navigating directly).
2. **Request handling**: `AnalyticsController@index` intercepts the request, validates the requested `tab` (defaulting to `predictive`), scopes the department list based on role (full list for Admin, single assigned department for Manager), and renders `Analytics/Index`.
3. **Tab Switching**: Clicking any tab button updates the local `activeTab` ref instantly. The URL query parameter is updated via `window.history.replaceState` without triggering a full page reload.
4. **Filter Adjustments**: Changing the department or date filter updates query parameters and initiates an Inertia partial reload (`preserveState: true`, `preserveScroll: true`) to update server-supplied datasets while keeping the active tab intact.
5. **Exporting Reports**: Clicking **Ekspor Laporan** opens the popover displaying current filter scope. Selecting PDF or CSV sends a GET request to `/analytics/export`, returning either a streamed CSV download or an executive 1-page PDF summary.

## API Endpoints & Routes

| Method | URI                 | Controller Action            | Purpose                             | Auth / Middleware                    |
| :----- | :------------------ | :--------------------------- | :---------------------------------- | :----------------------------------- |
| `GET`  | `/analytics`        | `AnalyticsController@index`  | Render Analytics shell & active tab | `auth, verified, role:admin,manager` |
| `GET`  | `/analytics/export` | `AnalyticsController@export` | Export PDF or CSV report            | `auth, verified, role:admin,manager` |

## Decisions & Trade-offs

- **Client-Side Dynamic Components vs Separate Pages**: Retaining all 6 tabs in a single route `/analytics` avoids route explosion, keeps filter state cached, and guarantees 0ms tab transitions.
- **Role-Based Scoping at Controller Level**: Managers are strictly prevented from viewing or exporting data belonging to other departments.
- **Wayfinder Route Generation**: Fully typed route functions imported from `@/routes/analytics` replace legacy Ziggy `route()` calls.

## Related

- [ADR-031: Analytics Page Shell and Client-Side Tab Navigation Architecture](../decisions/031-analytics-shell-and-tab-navigation-architecture.md)
- [Analytics API Endpoints](../api/analytics-endpoints.md)
- [Analytics & Decision Intelligence User Guide](../../user-docs/guides/analytics-decision-intelligence.md)
- [Epic-09: Executive Dashboard & Analytics Decision Intelligence](../../scrum/Epic-09.md)
