# ADR-016: Streaming Overtime Export and Audit Logging

**Date:** 2026-09-08  
**Status:** accepted  
**Supersedes:** None

## Context

In high-volume manufacturing environments, monthly overtime records across plant departments can scale into thousands of entries. When Department Managers or Plant Administrators export overtime data for payroll processing, finance auditing, and enterprise ERP ingestion:

1. Buffering entire Eloquent record sets in server memory (`collect()->all()`) risks PHP memory limit exhaustion (`Allowed memory size of bytes exhausted`) and long request timeouts.
2. Incomplete or non-standardized export structures create friction with HR and payroll systems that require exact wage rate snapshots, CapEx capitalization codes, and SPKL documentation status.
3. Financial audit compliance requires every export action to be traceable to the requesting actor, applied filter boundaries, and execution timestamp.

## Decision

We implemented a **High-Performance Streaming Export Engine** (E04-04):

1. **LazyCollection Cursor Streaming**: Database queries use Eloquent's `$query->cursor()` wrapped in `LazyCollection`, streaming records row-by-row into the output buffer with \(O(1)\) constant memory consumption regardless of record count.
2. **Dual-Format Support (CSV & OpenXML XLSX)**:
    - **CSV**: Streamed directly with a UTF-8 Byte Order Mark (BOM: `0xEF, 0xBB, 0xBF`) to guarantee immediate and clean display of special characters in Microsoft Excel and ERP tools.
    - **XLSX**: Native OpenXML spreadsheet packaging using PHP's built-in `XMLWriter` and `ZipArchive`, avoiding heavyweight third-party dependencies and network packagist timeouts while producing 100% compliant Microsoft Excel files with bold headings and numeric typing.
3. **19 Standardized Manufacturing Columns**:
   `submission_code`, `operational_date`, `day_type`, `department`, `section`, `npk`, `employee_name`, `hours_production`, `hours_tpm`, `hours_project`, `hours_others`, `total_hours`, `hourly_rate_snapshot`, `total_cost_idr`, `rca_category`, `status`, `rejection_reason`, `capex_project_code`, `spkl_status`.
4. **Hierarchical RBAC & Department Scoping**:
    - Managers are strictly restricted to their assigned department. Any attempt to export another department's records via query parameter tampering aborts with `403 Forbidden`.
    - Plant Administrators have plant-wide export access and can filter by department or export the entire factory dataset (`overtime-export-all-{YYYY-MM}.{ext}`).
5. **Dual Audit Logging**:
   Every export operation creates an entry in the dedicated `export_logs` table (capturing actor, format, filename, record count, and serialized query filters) and an immutable record in `overtime_item_audits` with `action: 'EXPORT'`.

## Consequences

### Positive

- **Constant Memory Footprint**: Exporting 50,000+ overtime entries runs safely within default PHP worker memory limits.
- **Immediate First-Byte Delivery**: Browser downloads begin instantaneously without long server-side buffering delays.
- **Enterprise ERP Compatibility**: All 19 standardized columns strictly align with factory ERP payroll and CapEx labor asset accounting rules.
- **Zero Heavyweight Dependencies**: Native PHP implementation requires no third-party spreadsheet packages.

### Negative

- XLSX streaming writes to a temporary filesystem archive before downloading; server temp directory (`/tmp`) must have sufficient storage during concurrent massive exports.

### Neutral

- Output filenames are deterministic: `overtime-export-{department}-{YYYY-MM}.{format}`.
