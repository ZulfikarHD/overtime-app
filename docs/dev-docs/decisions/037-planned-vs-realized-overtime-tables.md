# ADR-037: Separate Tables for Planned vs Realized Overtime

**Date:** 2026-09-22
**Status:** accepted

---

## Context

The application originally had a single overtime entry flow (`overtime_submissions`) used for
recording planned or realized hours submitted by team leaders. A business requirement clarified
that this form is better suited for **planning** (projecting future overtime by category), while
**realized** overtime comes from physical paper SPL documents uploaded as Excel files
(`spl_manual_ot.xlsx`).

The question was whether to extend `overtime_submissions` to cover both use-cases, or to create
dedicated tables.

---

## Decision

Create two new dedicated table groups:

1. **`overtime_plans` + `overtime_plan_items`** — monthly planning grid (one plan per
   section × fiscal month, with per-day per-employee rows split across four categories:
   Production, TPM, Project, Others).
2. **`spl_entries`** — realized overtime rows imported from the factory's physical SPL Excel,
   one row per employee per shift segment (unique on NPK + date + start time).

The existing `overtime_submissions` / `overtime_items` tables are left intact as the electronic
submission-and-approval workflow.

---

## Consequences

### Positive

- **Clear semantic separation** — planning data carries projected hours and a DRAFT/PUBLISHED
  lifecycle. Realized SPL data carries actual times, job descriptions, and an import audit trail.
  Mixing them would require nullable columns and conditional logic throughout.
- **Independent evolution** — the planning grid can grow to include budget constraints, headcount
  limits, or approval workflows without affecting the SPL import path.
- **Upsert safety** — the SPL table's unique constraint
  `(npk_snapshot, realization_date, start_time)` enables safe re-uploads without duplication.
- **No migration risk** — existing records in `overtime_submissions` are untouched.

### Negative

- **Additional tables** — three more tables in the schema (`overtime_plans`,
  `overtime_plan_items`, `spl_entries`).
- **Duplicate employee snapshots** — both tables store `npk_snapshot` /
  `employee_name_snapshot` to remain readable even if the employee record is later archived.

### Neutral

- Employee ID is stored as a nullable FK in `spl_entries`; it is resolved at import time from
  the NPK but is not guaranteed to exist for every row (guest workers, contractors).
