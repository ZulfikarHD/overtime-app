# ADR-021: Department-Level Consolidated Dashboard and Server-Side PDF Reporting

**Date:** 2026-09-09  
**Status:** accepted  
**Supersedes:** None

## Context

In plant manufacturing operations, department managers, plant leaders, and finance controllers require macro-level visibility to prioritize interventions during daily and weekly morning standups. While section cards (Story E05-01) offer visual overview, managers with 5–15 sections need a ranked tabular view sorted by **Burn Index (%)** descending to instantly identify which section is burning hours faster than allocated.

Furthermore:

1. **Executive Management Briefings**: Plant managers and section heads require clean, print-optimized 1-page PDF summaries for morning shopfloor standup meetings.
2. **Monthly Financial Closing**: Controllers require detailed monthly analytical reports containing overtime hours, CapEx vs OpEx distribution, and project variances for labor capitalization audits.
3. **Cross-Department Administration**: Plant administrators need a plant-wide comparison across all active manufacturing departments ("Semua Departemen") with quick drill-down capabilities.

In strict compliance with the **Epic-05 UX Plan** (`docs/scrum/Epic-05-ux-plan.md` Sections 1.2, 1.3, and 5.1):

- **Hard Single-Hub Constraint**: All views must reside within `/dashboard/burn-index` via tab switching (`?tab=department`). No separate route like `/dashboard/burn-index/department/{id}` is permitted.
- **Zero Context Loss**: Clicking any section in the ranked table slides open the right-side drawer (`SectionBurndownSheet.vue`) instead of navigating to a new page.
- **Server-Side PDF Generation**: Reports must be generated server-side using Blade templates and streamed via `barryvdh/laravel-dompdf` for consistent corporate typography, styling, and offline executive circulation.

## Decision

We implemented Story **E05-05: Department-Level Consolidated Burn Dashboard** with the following technical architecture:

1. **Backend Infrastructure & Data Aggregation**:
    - Installed `barryvdh/laravel-dompdf` (^3.1) for server-side PDF rendering.
    - Enhanced `MonthlySnapshotService::getDashboardData()` to support:
        - `department_id = 'all'` or `0`: Queries all active sections across all departments for Admins.
        - Computes `departments_summary`: Aggregated planned hours, actual hours, remaining balance, burn %, control matrix zone, and risk section counts per department.
    - Added `MonthlySnapshotService::getPdfExportData()`: Assembles structured datasets for DomPDF templates, including rank numbering, risk filtering, and Jakarta timestamp formatting (`d/m/Y H:i WIB`).
    - Added `DashboardBurnIndexController::exportPdf()`: Secured by role-based authorization (`admin`, `manager`, `team_leader`), setting A4 portrait dimensions and streaming attachments via `Pdf::loadView()`.
    - Registered route in `routes/web.php`:
        ```php
        Route::get('/burn-index/export-pdf', [DashboardBurnIndexController::class, 'exportPdf'])
            ->name('burn-index.export-pdf');
        ```

2. **Server-Side Blade PDF Templates**:
    - `resources/views/pdf/burn-index-standup.blade.php`: High-density 1-page executive standup briefing digest featuring ISUZU red branding, KPI summary blocks, high-risk alert callouts, ranked sections table, verification signature lines, and WIB timestamps.
    - `resources/views/pdf/burn-index-monthly.blade.php`: Full monthly analytical closing report containing sections ranking, CapEx vs OpEx labor breakdown, CapEx project variance performance, and cross-department plant consolidation.

3. **High-Density Industrial Frontend Components**:
    - `SectionBurnTable.vue`: Ranked table sorted by Burn Index descending by default. Includes columns for Rank (`#`), Section Code & Name, Planned Hours, Actual Hours, Remaining Quota, Burn Index % with status pill, Budget Control Matrix Zone badge, Velocity (`jam/mgg`), Projected Period-End Total, Trajectory indicator (`→ Aman`, `↗ Waspada`, `↑ Kritis`), and Action button (`Detail →`).
        - Subtle amber background tint for Warning (>100%), subtle red tint for Danger (>115%).
        - Real-time client-side instant search and status filtering (`Aman`, `Terkendali`, `Peringatan`, `Defisit`, `Belum Diatur`).
        - Emits `selectSection(id)` to slide open `SectionBurndownSheet.vue`.
    - `DepartmentComparisonCards.vue`: Grid of summary cards rendered for Admins when viewing "Semua Departemen", displaying planned vs actual progress bar, burn %, risk section count, and a 1-click filter button.
    - `PdfExportButton.vue`: Toolbar dropdown component providing quick export triggers for both "Ringkasan Standup Mingguan (1 Halaman PDF)" and "Laporan Analisis Bulanan Lengkap (PDF)" with download status indicators.

## Consequences

### Positive

- **Standup Operational Ergonomics**: Shift leads and managers can review or print standup digests in under 3 clicks without cognitive overload.
- **Zero Context Loss**: Drawer integration allows deep inspection into weekly trends and control matrices without losing table filter or scroll state.
- **Auditable Server-Side Outputs**: Deterministic A4 PDFs adhere to corporate formatting standards and provide permanent records for ISO and labor compliance audits.
- **Strict Architecture Compliance**: Follows the single-hub constraint and eliminates routing sprawl.

### Negative / Considerations

- DomPDF executes synchronous rendering on the server thread. For typical section counts (10–50 sections), generation takes < 300ms, which is well within acceptable HTTP request timeouts.

## Related

- [Epic-05: Budget Management & Burn Index Dashboard](../../scrum/Epic-05.md)
- [Epic-05 UX Plan](../../scrum/Epic-05-ux-plan.md)
- [ADR-005: Denormalized Monthly Burn Snapshots](./005-denormalized-monthly-burn-snapshots.md)
- [ADR-019: Single-Hub Tab-Based CapEx vs OpEx Distribution Panel](./019-single-hub-tab-based-capex-opex-distribution.md)
