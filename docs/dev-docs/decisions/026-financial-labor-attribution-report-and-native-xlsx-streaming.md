# ADR-026: Financial Labor Attribution Schedule & Native OpenXML Streaming Export

**Date:** 2026-09-09
**Status:** accepted

## Context

Under international financial accounting standards (IAS 16) and Indonesian financial accounting standards (PSAK 16), direct overtime labor costs incurred in the creation, assembly, or modification of qualifying fixed capital assets must be capitalized onto the balance sheet rather than expensed as operational costs.

For corporate tax depreciation and statutory financial audit compliance, corporate accounting requires an itemized labor attribution schedule showing:

- Direct attribution to specific CapEx project codes and asset tags.
- Detailed technician information (NPK, name), date, hours, immutable rate snapshots, and resulting capitalized cost.
- Project subtotals and grand totals.
- Digital export to OpenXML spreadsheet (`.xlsx`) format suitable for direct submission to corporate finance and external auditors.

Historically, reports across different modules often suffered from:

1. Navigation fragmentation with standalone reporting pages cluttering menus.
2. Inconsistent queries between on-screen tables and exported spreadsheets.
3. Memory exhaustion and process timeouts caused by heavy third-party spreadsheet generators (e.g. PhpSpreadsheet) on large datasets.

## Decision

We designed and implemented the CapEx Project Labor Attribution Report and native spreadsheet streaming export according to the following principles:

1. **Unified Hub Placement (Zero Navigation Bloat)**:
   In strict compliance with `docs/scrum/Epic-07-ux-plan.md`, the attribution report is hosted on **Tab 2** (`Laporan Atribusi Finansial`, `?tab=attribution`) of the unified CapEx Project Hub (`/admin/capex-projects`), replacing placeholder cards. Legacy route `/reports/capex-labor` redirects transparently with query parameter preservation.

2. **Query Isolation and Shared Domain Service**:
   `CapExAccountingService::buildAttributionQuery(array $filters, User $user)` serves as the single source of truth for both the Inertia web interface and the export engine. This guarantees 100% calculation and row count parity between what the user reviews on screen and what is written to the downloaded spreadsheet.

3. **Immutable Snapshot Enforcement**:
   The report strictly uses `total_cost_snapshot` and `hourly_rate_snapshot` stamped at the time of submission approval. Historical labor values are never recalculated using current employee wage scales, fulfilling statutory audit requirements.

4. **Native Zero-Dependency OpenXML Streaming (`CapexLaborExportService`)**:
   Rather than introducing large dependencies like `phpoffice/phpspreadsheet`, we implemented a native OpenXML generator using PHP's built-in `ZipArchive` and `XMLWriter`. Data rows, project subtotals, and grand totals are streamed directly into temporary XML storage and compressed into an `.xlsx` archive, keeping memory usage constant ($O(1)$) regardless of row count.

5. **True Numeric Spreadsheet Typing**:
   Numerical columns (hours, rates, and costs) are emitted as numeric XML cells (`<c t="n"><v>...</v></c>`), allowing financial controllers to execute `=SUM()` formulas and audit ledger calculations immediately upon opening in Microsoft Excel or LibreOffice.

6. **Role-Based Access Control Scoping**:
   Department Managers are strictly restricted to their department's projects. Unauthorized attempts to request or export cross-department records are rejected with HTTP 403 Forbidden.

## Consequences

### Positive

- **Audit Compliance**: Completely satisfies IAS 16 and PSAK 16 itemization standards with immutable rate snapshots.
- **Resource Efficiency**: Zero-memory streaming architecture prevents server memory leaks and worker starvation during peak export periods.
- **Unified UX**: Eliminates cognitive overhead and navigation fragmentation by centralizing portfolio management and attribution reporting on a single hub.
- **Audit Trail**: Every export operation is permanently logged to `export_logs` with actor details, record count, and applied filter criteria.

### Negative

- **Complex Spreadsheet Layouts**: Writing OpenXML via raw `XMLWriter` requires manual coordinate management (`A1`, `H{row}`, etc.) and XML schema adherence compared to higher-level abstractions.

### Neutral

- **File Cleanup**: Temporary `.xlsx` files generated in `sys_get_temp_dir()` are cleanly discarded post-transfer via Symfony's `deleteFileAfterSend(true)`.
