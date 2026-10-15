# ADR-043: Early Warning Detail Back Link (Referer + Session) and Staged Scope Ahead of E10-05 / E10-06 / E10-08

**Date:** 2026-10-15  
**Status:** accepted  
**Supersedes:** None (builds on ADR-040, ADR-041 and ADR-042)

## Context

E10-04 builds the alert detail page. Two questions are not answered by the epic text alone:

1. The back link must "preserve the inbox filters (use the previous URL / query string)". The inbox filters live in the query string (ADR-042), but the detail URL (`/early-warning/{section}/{date}`) is fixed by the Navigation Footprint and cannot carry them. A browser `history.back()` is not enough: it breaks after a reload or when the page is opened directly.
2. The epic lists the vitals (E10-05), the 5W2H form (E10-06) and the system suggestion (E10-08) inside the E10-04 acceptance criteria, yet the Sprint plan schedules those stories **before** E10-04 and none of them is built. Building them inside E10-04 would hide three stories (20 points) behind one.

## Decision

1. **The server computes `backUrl`.** `EarlyWarningController@show` returns the inbox URL from the same-origin `Referer` when its path is exactly the inbox path; otherwise from the inbox query string stored in the session by `index()`; otherwise the plain inbox. The value is always `inbox path` + optional `?query`, never a full or foreign URL.
2. **The page ships in stages.** E10-04 delivers the header, burn bar, vitals cards, chart, status block, history and error handling now. Data and features owned by other stories stay out: vitals props are `null` (shown as `N/A`), the 5W2H form and "Follow-up baru" wait for E10-06 / E10-08, and the `departments` prop (only the form's "Who" select needs it) is not sent.
3. **The vitals contract is fixed now.** `vitals` already has the final shape (`volume_change_pct`, `volume_period_label`, `manpower_change_pct`, `manpower_period_label`), so E10-05 only replaces the controller's placeholder with `EarlyWarningService::vitalsFor()`.

## Consequences

### Positive

- The back link survives a reload and a direct visit, and cannot be abused as an open redirect.
- No fabricated vitals: until real data exists the page says `N/A`, matching the epic's "no dummy numbers" rule.
- E10-05 / E10-06 / E10-08 plug into stable props and components without reshaping the page.

### Negative

- Until E10-06 ships, an alert detail has no form: the inbox **Follow Up** button opens a read-only page. The Dashboard panel still works in the meantime.
- The session fallback is per user, not per tab: with two inbox tabs open, a reload of a detail page returns to the most recently visited inbox filters.
- The "N/A" tooltip says the data is not available for the period, which is also what users see for sections that do have monthly data until E10-05 lands.

### Neutral

- `AlertFollowUpSummary.vue` is an additional component beyond the epic's inventory and may be merged with `FollowUpForm.vue` in E10-06.
- `AlertFollowUpService::toPayload()` gained `alert_date`, `streak_days`, `sent_by_name` and `created_at`; the Dashboard panel ignores them.
