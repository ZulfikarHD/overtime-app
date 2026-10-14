# ADR-041: Early Warning Sidebar Badge Cached per Scope and Policy-Based Section Access

**Date:** 2026-10-14  
**Status:** accepted  
**Supersedes:** None (builds on ADR-040)

## Context

E10-02 adds an Early Warning sidebar item with a badge of open Merah alerts. The sidebar is rendered on every page, so the count is shared through `HandleInertiaRequests`. The epic asked for "a single aggregate query, cached per request", but an alert is a zone of the daily index (approved hours against a daily pacing target, classified with department limits), which cannot be expressed as one SQL aggregate without duplicating the formula that ADR-040 deliberately keeps in one place. The detail route also needs a department check, and the same rule already existed in `AlertFollowUpPolicy`.

## Decision

1. **Reuse `alertsFor()` for the count.** `EarlyWarningService::openRedAlertCount()` asks for Merah alerts from the first day of the current month up to today and drops those whose latest follow-up is `sent`. No second implementation of the index or zone rules.
2. **Cache for 60 seconds per scope and Jakarta day.** Key `early-warning:open-red:{all|departmentId}:{Y-m-d}`. Admins share one entry, each department shares another. Users who cannot see alerts, or non-admins without a department, return 0 without a query.
3. **Share it as a closure.** Inertia evaluates the closure on full visits and on partial reloads that request it, so follow-up saves do not trigger it.
4. **Authorize with the existing policy.** `AlertFollowUpPolicy::viewAlertSection()` and `viewAny()` are called through `Gate::authorize()`; the department rule is a single protected method shared by `view`, `update` and the new ability.
5. **Section 403 before date validation.** The controller authorizes first, so a foreign section never reveals whether a date is valid.

## Consequences

### Positive

- The badge can never disagree with the inbox, because both come from `alertsFor()`.
- Page loads stay cheap: at most one evaluation per scope per minute.
- One department-scope rule for follow-ups and alert detail.

### Negative

- The badge can lag by up to 60 seconds after a follow-up is sent or an approval changes the index.
- A cold cache costs the constant number of queries of `alertsFor()` (about 7) on one request.

### Neutral

- Tests run on the array cache store, so each test starts with a cold cache.
- If a later story needs an instant refresh after sending, `AlertFollowUpService::save()` can forget the scope key.
