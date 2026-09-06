# ADR-005: Asynchronous Denormalized Monthly Burn Snapshots for Dashboard Performance

**Date:** 2026-09-06  
**Status:** accepted  
**Supersedes:** None

## Context

Plant leadership and department managers conduct morning standups between 07:30 and 09:00 WIB. During this window, dozens of supervisors and managers simultaneously open the **Burn Index Dashboard** to review section quotas, remaining budget hours, and CapEx splits across 35 sections.

If the dashboard calculates current hours, Burn Index percentages, and CapEx ratios via live SQL aggregation over hundreds of thousands of raw `overtime_items` records:

1. Expensive multi-table joins and aggregation queries (`SUM()`, `GROUP BY`) cause query spikes and database lock contention on the active OLTP write path.
2. Dashboard page loads degrade beyond acceptable interactive thresholds (> 3 seconds).
3. Live submission of new timesheets is slowed by read-lock contention.

## Decision

We introduce an explicit **Analytical Denormalization Layer**:

1. Create a dedicated table: `monthly_burn_snapshots` with unique constraint `(section_id, fiscal_year, fiscal_month)`.
2. The table stores pre-calculated rollups:
    - `planned_budget_hours`
    - `cumulative_actual_hours`
    - `cumulative_opex_hours`
    - `cumulative_capex_hours`
    - `burn_index_pct`
    - `burn_velocity`
    - `burn_zone`
3. Snapshots are updated asynchronously via `RecalculateMonthlyBurnSnapshotJob` on Redis queues:
    - When a timesheet batch is submitted.
    - When a line item is approved or rejected.
    - Nightly via scheduled cron for sanity re-indexing.
4. Dashboard controllers query `monthly_burn_snapshots` directly using index lookups (`WHERE department_id = ? AND fiscal_year = ? AND fiscal_month = ?`), delivering sub-15ms response times.

## Consequences

### Positive

- **Sub-15ms Dashboard Reads**: Managers experience instant dashboard loading regardless of total historical timesheet volume.
- **Isolated OLTP / OLAP Paths**: Frontline timesheet submission writes never compete with analytical management read queries.
- **Reliable Historical Comparison**: Monthly snapshots remain available for historical month-over-month trend queries without scanning archived items.

### Negative

- Eventual consistency: dashboards reflect updates within 1–3 seconds of an approval event rather than instantaneously.
- Requires robust queue worker monitoring to ensure calculation jobs execute promptly.

### Neutral

- An explicit "Recalculate Now" button can be provided to administrators for immediate on-demand rollups.
