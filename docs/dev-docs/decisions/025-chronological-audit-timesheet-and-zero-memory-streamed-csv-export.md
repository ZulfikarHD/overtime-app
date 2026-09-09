# ADR-025: Chronological Audit Timesheet and Zero-Memory Streamed CSV Export

**Date:** 2026-09-09  
**Status:** accepted  
**Epic:** Epic-06 (Individual Employee Reporting & Welfare Tracking)  
**Story:** E06-05 (Chronological Audit Timesheet)

## Context

Manufacturing operations require operators and line supervisors (Team Leaders, Managers) to inspect historical daily overtime records on a per-item granular level. Previous legacy software grouped items by submission codes without showing individual task notes or line-level approval states, complicating wage reconciliation and dispute investigations.

Key architectural and UX challenges included:

1. **Single UI Surface Boundary**: The Epic-06 UI/UX design plan mandates that the chronological timesheet must reside as a tab on the existing `EmployeeDossier.vue` hub (`/reports/employees/{npk}`), rather than a separate detached page, maintaining single-modal/accordion depth.
2. **Server-Side Scalability**: Factory workers accumulate hundreds of overtime records over months and fiscal years. Loading all records at once causes severe DOM tree bloat and client latency.
3. **Rejection Reason Auditing**: Operators need immediate visibility into why an overtime item was rejected, along with RCA categorization and supervisor notes, without leaving the ledger context or opening multi-level modals.
4. **Memory-Safe CSV Export**: Large date ranges or multi-year audit exports must not cause PHP memory exhaustion on resource-constrained web containers.

## Decision

We implemented the Chronological Audit Timesheet and Streamed CSV Export with the following architectural choices:

1. **Embedded Dossier Tab & Shortcut Routing**:
    - Integrated as the `Buku Jam Lembur` (`timesheet`) tab directly within `resources/js/pages/reports/EmployeeDossier.vue`.
    - Dedicated shortcut route `GET /reports/employees/{npk}/timesheet` cleanly redirects to `/reports/employees/{npk}?tab=timesheet`, preserving query parameters.
2. **Server-Side Pagination & Scoped Eager Loading**:
    - `EmployeeReportService@getTimesheet` enforces a strict 25 items per page server-side `LengthAwarePaginator`.
    - Eager loads only required relationships: `overtimeSubmission:id,submission_code,operational_date,day_type,status` and `capexProject:id,project_code,name`.
    - Calculates summary metrics (Total Items, Total Hours, Approved, Pending, Rejected, and Total Cost IDR) across the filtered set without loading all full models into memory.
3. **Inline Expandable Row Architecture**:
    - Following Anti-Splitting Rule 5.3, rejection reasons, task descriptions, and RCA metadata are rendered in an inline accordion row (`expanded-row-{id}`) directly beneath the item.
    - Avoids nested dialog popups and maintains full context on shop-floor tablets and desktops.
4. **Zero-Memory Streamed CSV Export**:
    - Implemented via `EmployeeReportService@exportTimesheetCsv` using Laravel's native `response()->streamDownload()`.
    - Uses Eloquent cursor iteration with `fputcsv` to stream records in real-time with negligible memory footprint ($O(1)$ memory).
    - Injects a UTF-8 Byte Order Mark (`\xEF\xBB\xBF`) for instant native Indonesian character encoding compatibility in Microsoft Excel.
5. **Strict Hierarchical RBAC Authorization**:
    - Line operators (`User` role) can only access and export their own timesheet records (`$user->npk === $employee->npk`).
    - Team Leaders are bounded to employees in their section (`$user->section_id === $employee->section_id`).
    - Managers are bounded to employees in their department (`$user->department_id === $employee->department_id`).
    - Unauthorized attempts throw HTTP 403 Forbidden.

## Consequences

### Positive

- **Shop-Floor Ergonomics**: Operators and supervisors inspect records with zero page transitions and view rejection notes with a single click.
- **Resource Efficiency**: High-density server-side pagination (25 items/page) and cursor-streamed CSV exports guarantee constant server memory regardless of record counts.
- **Data Integrity**: Excel-ready UTF-8 CSV exports prevent character corruption for Indonesian text and currency figures.
- **Security**: Robust controller-level and service-level authorization prevents horizontal privilege escalation.

### Negative

- Client-side tab state preservation requires query parameter synchronization when filtering or paginating across tabs.

### Neutral

- The timesheet tab utilizes the unified dossier header period selector (Year/Month) as default boundaries, with optional date overrides (`date_from`, `date_to`) and an "all time" checkbox.
