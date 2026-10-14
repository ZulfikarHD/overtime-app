# ADR-042: Early Warning Inbox Filters in the URL, Deferred Alerts and a Lenient Redirect on Invalid Input

**Date:** 2026-10-15  
**Status:** accepted  
**Supersedes:** None (builds on ADR-040 and ADR-041)

## Context

E10-03 turns the Early Warning shell into the alert inbox. The list comes from `EarlyWarningService::alertsFor()`, which evaluates every section for every day of the range (plus a 60-day streak look-back), so it is the slowest part of the page. The epic requires shareable and reloadable filters, server-side filtering, a loading skeleton via deferred props, pagination above 25 alerts, and department isolation for non-admins. It also lists `summary` as an ordinary prop, although it comes from the same computation as the list.

## Decision

1. **Filters live in the query string** and are read by a dedicated `EarlyWarningInboxRequest`. Defaults (last 7 days up to today, Asia/Jakarta, zone and status `all`) are applied server-side and echoed back in the `filters` prop.
2. **Invalid filters redirect to the plain inbox** with an `error` flash (handled by the global flash toast) instead of returning a 422. The range is also limited to fewer than 93 days.
3. **`summary` and `alerts` are deferred props in one group** (`inbox`) and share a single `inboxFor()` result memoized in the controller. The immediate props (`filters`, `departments`, `sections`) let the filter bar render at once.
4. **The summary counts the department / section / date range only.** The zone and follow-up status filters narrow the list, not the summary.
5. **The department lock is enforced in the service** (`inboxScope()` through the same scope rule as `alertsFor()`); the disabled select in the UI is only a hint. A section outside the offered sections is dropped.
6. **Filtering after `alertsFor()` and slicing in PHP** (25 per page, out-of-range page falls back to the last page) instead of adding query parameters to the shared batch calculation.

## Consequences

### Positive

- Links are shareable and survive a reload; the browser back button is unaffected because filter changes use `replace`.
- The first paint does not wait for the alert computation; the alerts are computed once even though two props need them.
- A stale or hand-edited URL never leaves the user on an error page, and cannot leak another department.
- No change to `alertsFor()`, so the inbox, the badge and the detail page keep one source of truth.

### Negative

- The whole range is held in memory before slicing (bounded by the 93-day limit and the constant-query design of ADR-040).
- With preserved state, a filter change briefly shows the skeleton again while the deferred props reload.
- The memoizing closure only works when both deferred props are requested together (they are: one group). A custom partial reload asking for only one prop would still be correct, just computed for that request.

### Neutral

- `summary` deviates from the epic's "normal prop" wording; the page contract for the frontend is unchanged.
- The 93-day limit is a new rule not in the epic; it can be raised in `EarlyWarningInboxRequest::MAX_RANGE_DAYS`.
