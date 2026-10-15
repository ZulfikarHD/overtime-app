# ADR-048: Early Warning Index Is Month-to-Date

**Date:** 2026-10-15  
**Status:** accepted  
**Supersedes:** The per-day index formula of [ADR-040](./040-early-warning-alert-detection-shared-daily-index.md) (everything else in ADR-040 still holds)

## Context

E10-01 applied CALC-02 to a single day: the hours of that day against `plannedHours / daysInMonth`. Overtime is booked on a few days of the month, so almost every day with overtime was Merah even when the month was far under budget (a section at 46 % of its monthly budget had 9 Merah days out of 10). The Dashboard burn index and burn curve already judge the month so far, so the Early Warning chart disagreed with the rest of the Dashboard.

## Decision

1. The index of a day is **month-to-date**: cumulative approved hours from the 1st up to the day ÷ `plannedHours × day ÷ daysInMonth` × 100 (`DashboardKpiService::calculateMonthToDateIndex()` / `calculateExpectedHours()`). The expected hours are the plan line of the Dashboard burn curve. The cumulative hours reset on the 1st of each month.
2. The Dashboard chart (`getDailyIndexTrend`) and the alerts (`EarlyWarningService`) keep sharing that one formula. The chart payload keeps `daily_indices` (now month-to-date) and `daily_hours` (hours of that day) and adds `cumulative_hours`; `average_index` / `average_zone` are the index of the last day shown.
3. The streak, zone limits, scope and constant query count are unchanged. The scan now starts on the 1st of the month of the lookback start, to rebuild the cumulative hours.
4. The 5W2H generator measures "how much" as the month-to-date overspend (cumulative − expected at the alert date, never negative), spread over `follow_up_days`.

## Consequences

- A busy day in a month that is still under its pace is no longer an alert; a month over its pace stays flagged on following quiet days until the plan catches up.
- Early in the month the index is volatile (day 1 expects only one day's share of the budget). Accepted: it settles within the first week.
- Calendar days are used for pacing, like the burn curve. Pacing per working day (HKN) is a possible follow-up if the client prefers it.
