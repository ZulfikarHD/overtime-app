# ADR-002: Immutable Financial Rate Snapshotting on Overtime Approval

**Date:** 2026-09-06  
**Status:** accepted  
**Supersedes:** None

## Context

Overtime hours represent both operational labor consumption and direct monetary expense in Indonesian Rupiah (Rp). In manufacturing operations, labor rates vary:

- Standard departmental baseline rate (`departments.default_hourly_rate`).
- Specific employee wage tiers (`employees.hourly_rate`).

In subsequent fiscal years or following union collective bargaining revisions, wage baselines are adjusted. If historical overtime costs are calculated dynamically via runtime SQL queries (`hours * employees.hourly_rate`), modifying a base wage retroactively recalculates historical accounting ledger values. This violates standard financial audit compliance (SOX, IFRS, Indonesian tax audit standards).

## Decision

We enforce **Immutable Financial Rate Snapshotting**:

1. When an overtime item is recorded or approved, the system resolves the effective labor rate:
   $$\text{rate} = \begin{cases} \text{employees.hourly\_rate} & \text{if } > 0 \\ \text{departments.default\_hourly\_rate} & \text{otherwise} \end{cases}$$
2. The resolved rate is permanently written to `overtime_items.hourly_rate_snapshot`.
3. Total line cost is permanently calculated and written to `overtime_items.total_cost_snapshot` using high-precision decimal operations (`bcmul`).
4. These columns are immutable once status transitions to `APPROVED`. Database triggers or policy classes reject retroactive mutations.

## Consequences

### Positive

- **Audit Compliance**: Historical overtime expenditure for fiscal year 2026 remains identical when audited in 2028, even if employee hourly rates doubled.
- **Query Performance**: Aggregate cost reporting sums `total_cost_snapshot` without joining and computing rates across historical employee tables.
- **Predictable CapEx Capitalization**: Capitalized labor attributed to fixed assets (`capex_projects`) remains fixed and certifiable for tax depreciation.

### Negative

- Minor storage overhead (two `NUMERIC(15,2)` columns per line item).
- Correcting an erroneous rate snapshot requires an explicit administrative adjustment ledger entry rather than a simple wage update.

### Neutral

- Roster updates require clear communication to payroll administrators regarding the effective cutoff date for timesheets.
