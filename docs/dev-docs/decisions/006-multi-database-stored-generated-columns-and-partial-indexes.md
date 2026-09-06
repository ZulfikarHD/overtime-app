# ADR-006: Multi-Database Stored Generated Columns and Partial Indexes

**Date:** 2026-09-06  
**Status:** accepted  
**Supersedes:** None

## Context

The OT-CapEx system mandates mathematical integrity on overtime allocations (BR-01 & BR-04: total hours must equal the sum of production, TPM, project, and other hours) and high-performance querying on partial subsets (e.g., pending SPKL documents, active employees, and non-dismissed anomalies).

The system runs tests locally and in CI using fast in-memory SQLite (`:memory:`), while target deployment targets PostgreSQL 16+ or MySQL 8.0+ InnoDB.

Different SQL engines handle partial index syntax and CHECK constraints differently:

- PostgreSQL and SQLite natively support filtered partial indexes (`CREATE INDEX ... WHERE ...`).
- MySQL 8.0 (InnoDB) does not support `WHERE` clauses on B-Tree indexes, requiring standard composite indexing.
- SQLite parses column-level CHECK constraints but rejects `ALTER TABLE ADD CONSTRAINT` syntax post-creation.

## Decision

1. **Use Laravel Schema `storedAs()` for `overtime_items.total_hours`**:
   Define `total_hours` via `$table->decimal('total_hours', 4, 2)->storedAs('hours_production + hours_tpm + hours_project + hours_others')`, which compiles to `GENERATED ALWAYS AS (...) STORED` across SQLite, PostgreSQL, and MySQL.
2. **Apply Driver-Aware Migration Logic**:
   Check the database driver via `Schema::getConnection()->getDriverName()`:
    - For PostgreSQL and SQLite, execute filtered partial index statements via `DB::statement()`.
    - For MySQL, fallback to standard composite index definitions on the blueprint.
    - For `ALTER TABLE ADD CONSTRAINT CHECK`, execute conditionally on PostgreSQL and MySQL where post-create table alteration is supported.

## Consequences

### Positive

- **Enforced Invariants**: Row-level overtime math cannot diverge from the individual categories, preventing auditing discrepancies.
- **Sub-Millisecond Query Response**: Filtered indexes keep B-Tree index pages small by omitting closed or irrelevant rows (e.g., millions of historical verified SPKL documents or dismissed anomalies).
- **Zero-Friction Testing**: Test suite runs against `:memory:` SQLite at full speed while preserving production-grade database constraints on PostgreSQL/MySQL.

### Negative

- Migrations contain minor conditional branches based on `Schema::getConnection()->getDriverName()`.

### Neutral

- Modifying the formula of stored generated columns requires dropping and recreating the column or table if business rules change.
