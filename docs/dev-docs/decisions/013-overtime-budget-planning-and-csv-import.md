# ADR-013: Overtime Budget Planning and Two-Stage CSV Import Architecture

**Date:** 2026-09-07  
**Status:** accepted  
**Supersedes:** None

## Context

Epic E02 requires managing section-level monthly overtime hour quotas that serve as the baseline for the plant-wide Overtime Burn Index and budget alerts. Plant operations required:

1. Role-based scoping where Administrators can configure any department, while Department Managers are strictly restricted to sections in their assigned department.
2. An interactive 5-week weekly distribution breakdown where individual weeks can deviate from equal distribution without blocking allocation saves.
3. Accurate real-time labor expenditure estimates calculated from department-specific standard hourly rates.
4. Fast batch ingestion of budget plans via CSV without allowing corrupted lines or unauthorized foreign department sections to enter the database.

## Decision

1. Consolidate all budget planning operations under a single primary route `/budgets/planning` protected by `role:admin,manager` middleware.
2. Delegate all section allocation edits and bulk CSV imports to slide-in right drawers (`BudgetFormSheet.vue` and `BudgetImportSheet.vue`) rather than dedicated URL routes, satisfying the UX plan anti-splitting constraints.
3. Implement a two-stage pre-commit safety net for CSV imports:
    - **Stage 1 (Dry-Run Audit):** The server analyzes the uploaded CSV in memory against live department and section data, returning line-by-line validation errors without touching the database.
    - **Stage 2 (Commit):** The client sends validated rows to be persisted inside an atomic database transaction.
4. Calculate weekly distribution automatically as `planned_hours / 4.3` when not explicitly entered, while displaying an advisory amber pill if manual weekly inputs do not sum to the monthly total.

## Consequences

### Positive

- **Prevents Dirty State:** Two-stage CSV import prevents partial or corrupt records from being inserted into the database.
- **Strict Role Boundary Enforcement:** Scoping prevents department managers from accessing or modifying budgets of adjacent departments.
- **Operational Flexibility:** Advisory non-blocking weekly warnings allow plant supervisors to account for scheduled maintenance peaks or holidays in week 4 or 5.
- **Clean Navigation Footprint:** Single sidebar item (`Budget Planning`) for Admins and Managers, keeping navigation streamlined.

### Negative

- **Large CSV Memory Footprint:** Very large CSV files require sufficient memory during the Stage 1 in-memory audit, though manufacturing section budgets typically comprise under 1,000 rows per month.

### Neutral

- Historical labor rate changes do not alter previously snapshotted budget estimates unless an administrator explicitly updates the budget record for that period.
