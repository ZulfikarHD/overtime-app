# ADR-047: Rule-Based Early Warning 5W2H Generator with Explicit Evidence and an Admin-Editable Cause → PIC Mapping

**Date:** 2026-10-15  
**Status:** accepted  
**Supersedes:** The baseline body of `AlertFollowUpService::suggestionFor()` from ADR-045 (its output shape is kept)

## Context

The client wants the system to write the 5W2H of an alert so users only review and send it. The form must never open empty, a wrong or generic "Why" loses the client's trust, and the plant has no AI service, no daily production volume and no production-line field. ADR-045 left Why, How and How much empty on purpose until this story.

## Decision

1. **A deterministic, rule-based generator.** `EarlyWarningFollowUpGenerator` is a pure service. Six causes are detected with fixed thresholds in `config/early_warning.php`, ranked by score (catalog order breaks ties) and written from `__()` templates. Every cause sentence carries its evidence number; with nothing detected the text says the cause is unknown instead of guessing. No dependency was added.
2. **The generator resolves the alert itself, with the user.** `generate(User, Section, date)` calls `EarlyWarningService::alertContext()`, which re-checks the department scope, so scope safety does not rely on every caller.
3. **Evidence is stored.** `generated_payload` keeps the texts plus `primary_cause`, `supporting_causes` and `evidence`, so a saved follow-up can be audited later even when thresholds or data change.
4. **`suggestion` is a closure prop.** The detail page computes it on a normal visit and on `only: ['suggestion']`; the save / send reload skips it.
5. **Cause → PIC mapping lives in one table edited in Administration.** `alert_cause_pic_mappings` (one row per cause, nullable PIC, active flag), a card in the existing Policies tab and one admin route. No row means the configured default department code; a row without PIC, an inactive row, an inactive department or a department without active recipients (when the own department has some) falls back to the alert's own department.
6. **"Who is notified" has one definition.** `User::scopeAlertRecipientsOf()` is used by the send validation and by the generator's Who check, so the generator never proposes a department the send step would reject when a valid one exists.

## Consequences

### Positive

- Same input gives the same text; each rule is one unit test, and thresholds are tunable without a deploy of code.
- Users can trust or reject a sentence quickly because the number is in it.
- Mapping changes affect new suggestions only; saved and sent follow-ups keep their text and snapshot.
- No new page, route group or sidebar item.

### Negative

- Scores are proxies (share of the period's hours, a heuristic for manpower), not true shares of the excess hours; the ranking needs validation against real past alerts (epic Q7).
- Vitals are monthly and plant-wide (ADR-044), so the manpower cause is coarse.
- Several small aggregate queries per suggestion (about 16 in total); acceptable for one alert, but not meant for batch generation in the inbox.
- Category-specific PIC (TPM → maintenance) is not supported; the mapping is per cause.

### Neutral

- Generated text is written in the locale of the user who opens the alert; the snapshot is stored in that locale.
- `when_text`, `cost_note` and the evidence travel with the suggestion but are not form fields.
