# ADR-020: Budget Threshold Alert Timestamp Locking for Anti-Fatigue Deduplication

**Date:** 2026-09-09  
**Status:** accepted  
**Supersedes:** None

## Context

In high-volume automotive production facilities, overtime submissions are submitted and approved incrementally throughout shifts by multiple Section Heads and Department Managers. As cumulative overtime hours approach or exceed monthly planned limits, asynchronous background recalculation jobs (`RecalculateMonthlyBurnSnapshotJob`) run repeatedly to refresh section burn metrics.

Without a robust deduplication mechanism, consecutive approval events (such as approving 5 individual overtime requests over an afternoon) would re-trigger alert evaluations and bombard Department Managers and Plant Administrators with duplicate warning notifications for the same section within the same fiscal month.

Key requirements to satisfy:

1. **Anti-Fatigue Guarantee**: Each production section must trigger at most **one Warning alert** and at most **one Danger alert** per fiscal month.
2. **Persistence Across Restarts**: The deduplication lock must survive queue worker restarts, application deployments, and server failovers.
3. **Multi-Month Reset**: When a new fiscal month begins, the section must automatically start with a clean alert slate without requiring manual cron cleanup or record deletion.
4. **Hierarchical Escalation**: If a section's burn index leaps directly from normal to danger (e.g., from 90% straight to 118%), both the danger alert and the warning state must be appropriately locked so that subsequent drops and minor rises do not trigger redundant warning alerts.

## Decision

We chose **Database Timestamp Locking on `monthly_burn_snapshots`** (`warned_at` and `danger_at`) to govern alert dispatch:

1. **Schema Additions (`monthly_burn_snapshots`)**:
    - Added `warned_at` (`timestamp nullable`) and `danger_at` (`timestamp nullable`).
    - Because `monthly_burn_snapshots` is partitioned naturally per `(section_id, fiscal_year, fiscal_month)`, alert deduplication is natively scoped to the specific fiscal period. When a new month's snapshot record is created, `warned_at` and `danger_at` default to `NULL`.

2. **Deduplication Logic in `BudgetAlertService`**:
    - **Danger Crossing**: If `burn_index_pct >= burn_danger_pct` and `danger_at IS NULL`:
        - Dispatches `BudgetThresholdAlert` with `alert_level = 'danger'`.
        - Sets `danger_at = now()`.
        - If `warned_at IS NULL`, also sets `warned_at = now()` to prevent backwards warning triggers.
    - **Warning Crossing**: If `burn_index_pct >= burn_warning_pct` and `warned_at IS NULL`:
        - Dispatches `BudgetThresholdAlert` with `alert_level = 'warning'`.
        - Sets `warned_at = now()`.
    - **Subsequent Recalculations**: If the condition evaluates but the corresponding timestamp is non-null, the evaluation silently skips alert dispatch.

3. **Recipient Resolution & Opt-Out Guard**:
    - Recipients are restricted to active Administrators (`role = admin`) and the specific Department Manager (`role = manager` where `department_id = $snapshot->department_id`).
    - User preference `budget_threshold_alert` (and `notifications.budget_alerts`) is verified before queuing notifications.

4. **1-Click UX Resolution (Zero Navigation Friction)**:
    - In accordance with `Epic-05-ux-plan.md`, notifications link directly to `/dashboard/burn-index?tab=sections&section={id}`.
    - Upon navigation, `BurnIndex.vue` immediately triggers `SectionBurndownSheet.vue` to slide open, enabling 1-click investigation without browsing tables.

## Consequences

### Positive

- **Eliminates Notification Fatigue**: Managers and Admins receive exactly one alert per threshold level per section per month.
- **Zero Cache Dependency**: Avoids Redis/memcached key eviction risks, cache prefix mismatches, or serialization overhead.
- **Audit-Traceable**: The exact timestamp when a section breached warning or danger limits is permanently recorded on the snapshot model for post-mortem management review.
- **Multi-Month Cleanliness**: Clean slate each fiscal month is guaranteed by the composite primary identity of `(section_id, fiscal_year, fiscal_month)`.

### Negative / Trade-offs

- **Manual Reset Edge Case**: If an approved batch of overtime items is later rejected or cancelled due to clerical error, the snapshot's actual burn drops back down below 100%, but `warned_at` remains stamped unless explicitly cleared. This is considered acceptable industrial behavior, as the initial breach did occur.

## References

- [Epic-05 Scrum Plan](../../scrum/Epic-05.md)
- [Epic-05 UX Plan](../../scrum/Epic-05-ux-plan.md)
- [Feature Documentation: Budget Threshold Alerts](../features/budget-threshold-alerts.md)
