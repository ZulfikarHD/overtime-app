# ADR-045: Early Warning Follow-Up Linked to an Alert, with a Server-Side Suggestion Baseline

**Date:** 2026-10-15
**Status:** accepted (the suggestion baseline was replaced by the generator of [ADR-047](./047-early-warning-rule-based-follow-up-generator.md); the alert link and snapshot decisions stand)
**Supersedes:** The "Dashboard panel still works in the meantime" consequence of [ADR-043](./043-early-warning-detail-back-link-and-staged-scope.md)

## Context

1. E10-06 links the 5W2H follow-up to one alert (section + date) and moves its UI and routes into the Early Warning module. The form must open **prefilled by the system** and keep a snapshot of that text so edits can be tagged and audited.
2. The generator that writes the full suggestion (E10-08) is not built, yet the acceptance criteria forbid an empty form.
3. Moving the form and replacing `dashboard.alert-follow-ups.*` leaves the Dashboard panel without a component or routes to use.
4. The alert snapshot (zone, index, streak) must be server truth. The same is true for the "what the system generated" snapshot, otherwise "Diubah" can be forged.

## Decision

1. **Alert identity** is `section_id` + `alert_date`. `AlertFollowUpService::save()` recomputes the alert through `EarlyWarningService::alertDetail()` on every save; no alert = 422, another department = 403. Update never accepts a new section or date.
2. **Suggestion baseline.** `AlertFollowUpService::suggestionFor(array $alert)` returns the form-shaped 5W2H the alert data fully justifies (What, When, Where, Who = own department). Why, How and How much stay empty. Its shape is the contract E10-08 keeps; E10-08 replaces the body, not the callers (`EarlyWarningController@show`, `save()`).
3. **`generated_payload` / `generated_at`** are written by the server on create from `suggestionFor()`, never from the request, and are never rewritten.
4. **One draft per alert** is checked inside the save transaction (row lock). Sent follow-ups accumulate as history. A `FOR UPDATE` re-read on update stops a double send.
5. **The Dashboard panel is removed in this story**, with the `alertFollowUps` prop, the controller loading and the old routes. E10-07 keeps the entry link and its own cleanup checks.
6. **Legacy rows** get `alert_date` = creation date in the migration (PHP, driver-neutral). They have no snapshot and are read-only in practice: a save on them fails with a clear message.

## Consequences

### Positive

- The form opens filled, with honest "Dibuat sistem" / "Diubah" tags, and an audit trail of what the system suggested.
- E10-08 plugs in without touching the controller, the form or the schema.
- Zone, index and streak cannot be tampered with from the client; scope isolation is enforced in one place.
- No duplicate form: one component, one set of routes.

### Negative

- Until E10-08, Why, How and How much start empty and carry no tag; the user types them (as before).
- The stored snapshot is the suggestion at first save, which can differ from what the user saw if the data changed in between.
- E10-07 shrinks: its Dashboard cleanup items are done here, so its remaining scope is the entry link and tests.

### Neutral

- The Who default is the alert's own department, the fallback E10-08 uses when no cause mapping exists.
- Backfilled legacy rows may appear in the history of the section's detail page for the date they were created on.
