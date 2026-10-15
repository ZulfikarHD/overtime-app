# ADR-040: Computed Early Warning Alerts on a Shared Daily Index

**Date:** 2026-10-14  
**Status:** accepted (the index formula was changed from per-day to month-to-date by [ADR-048](./048-early-warning-month-to-date-index.md))  
**Supersedes:** None (builds on ADR-010 and ADR-039)

## Context

Epic-10 turns the Dashboard Early Warning chart into an alert workflow. Alerts must match what the chart shows, must be listed for the whole plant over a month without a per-section × per-day query pattern, and must respect department scope on the server. The follow-up table (`alert_follow_ups`) only knew a fiscal month, so it could not be linked to a concrete alert.

## Decision

1. **Alerts are computed, not stored.** `EarlyWarningService` derives them on demand from approved overtime, budgets, the operational calendar and zone limits. Only follow-ups are persisted, linked through the new `alert_follow_ups.alert_date` (+ `section_id`).
2. **One daily index formula.** `DashboardKpiService::calculateMonthToDateIndex()` / `calculateExpectedHours()` (originally `calculateDailyIndex()` / `calculateDailyPacing()`) / `resolvePlannedHoursForSections()` are used by both `getDailyIndexTrend()` and `EarlyWarningService`.
3. **Constant query count.** One grouped query for hours over `scan range = range + 60 days`, one each for sections, budgets (+ snapshot fallback), calendar, thresholds and follow-ups.
4. **Streak** counts consecutive HKN days in Kuning/Merah; HLR days are skipped; a Hijau working day or a month without budget resets it.
5. **Scope.** Admin sees all (optional filters); Manager and Team Leader see their own department, server-side; other roles see nothing.
6. **Migration split.** The `alert_date` and `streak_days` columns ship with E10-01 because its acceptance criteria expose `follow_up_status`; backfill of legacy rows and the save/send flow stay in E10-06 (legacy rows keep `alert_date = NULL` and are never linked).

## Consequences

### Positive

- Chart and alert list cannot diverge; a parity test guards it.
- Scales with sections and days at a constant number of queries (asserted in a feature test).
- No alert table to keep in sync when budgets, thresholds or the calendar change.

### Negative

- Streaks are capped by the 60-day lookback window.
- Alerts are recalculated on every request (acceptable for the targets in Epic-10: < 1.5 s whole plant, 31 days).

### Neutral

- Days without a budget are not evaluated, which differs from the Dashboard showing 0 % / 100 % for an unbudgeted scope.
