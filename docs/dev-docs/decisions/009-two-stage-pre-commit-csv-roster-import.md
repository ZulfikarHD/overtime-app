# ADR-009: Two-Stage Pre-Commit In-Memory CSV Roster Import

**Date:** 2026-09-06  
**Status:** accepted

## Context

In an automotive assembly plant, employee rosters change regularly due to operator reassignments, contract updates, and seasonal shifts. Administrators frequently need to register batches of tens or hundreds of plant workers at once.

Standard blind CSV uploads (uploading directly to the database or queue without interactive feedback) pose significant operational and financial risks:

1. Typos in department codes or section codes can associate workers with incorrect cost centers or cause raw database constraint crashes.
2. Inadvertent NPK duplicates between the file and database—or multiple occurrences within the file itself—lead to partial failures and messy manual database rollbacks.
3. Blindly writing invalid rows corrupts subsequent supervisor timesheet workflows and payroll rate calculations.

Furthermore, user experience requirements from **Epic-02 UX Plan** require all administrative actions to remain within the single-page Master Data Hub with a slide-in drawer (`Sheet`), maintaining the 25-row roster table in view without jarring page navigations.

## Decision

We designed and implemented a **Two-Stage Pre-Commit CSV Import Architecture** powered by in-memory dry-run validation:

1. **Stage 1: Pre-Commit Live Audit Preview (`POST /admin/employees/import/preview`)**:
    - The uploaded CSV file is parsed entirely in memory.
    - The validation engine pre-loads all existing departments, sections, and registered NPKs once, establishing O(1) in-memory lookup sets.
    - Every row is validated for:
        - Header conformity (`npk`, `full_name`, `department_code`, `section_code`, `job_position`, `hourly_rate`).
        - Global database NPK uniqueness AND intra-file duplicate NPK occurrences.
        - Department code existence.
        - Section code existence and verification that the section belongs to the stated department.
        - Numeric non-negative hourly rate.
    - A detailed payload is returned to the client containing metrics (`total`, `valid_count`, `error_count`) and an array of audited rows with specific error strings.
    - The UI renders an interactive audit table highlighting valid rows with emerald badges and error rows with red badges detailing exact errors.

2. **Stage 2: Selective Transactional Commit (`POST /admin/employees/import`)**:
    - Only after an administrator reviews the audit table and clicks **Konfirmasi & Import** are the valid rows submitted to the commit endpoint.
    - The server validates the rows via `ImportEmployeeCsvRequest` and commits them in batches within a database transaction.
    - Zero invalid rows are committed, preventing partial corruption.

## Consequences

### Positive

- **Zero Accidental Corruption**: Administrators catch typos, duplicate NPKs, and mismatched hierarchies prior to persisting any records into the database.
- **High Performance (O(1) Lookups)**: Pre-loading active departments, sections, and NPKs avoids issuing individual SQL queries per row during large CSV audits.
- **Immediate Interactive Feedback**: Users see color-coded status badges and line-by-line feedback without page reloads, operating smoothly inside a slide-in drawer.
- **Graceful Partial Imports**: If a file has 95 valid rows and 5 errors, the administrator can choose to import the 95 valid records immediately while exporting or correcting the 5 flawed rows.

### Negative

- **Two HTTP Round-Trips**: Importing requires an initial preview request followed by a confirmation commit request.
- **Client-Payload Transmission**: Valid rows are transmitted back to the client and re-submitted on commit. (For enterprise rosters exceeding thousands of rows, an asynchronous background job `ImportEmployeesFromCsvJob` is provided).

### Neutral

- Both multipart file uploads and parsed text payloads are supported interchangeably to guarantee testability across in-process HTTP drivers and production environments.
