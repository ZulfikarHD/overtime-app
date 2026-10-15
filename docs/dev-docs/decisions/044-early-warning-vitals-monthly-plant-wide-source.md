# ADR-044: Early Warning Vitals from Monthly Plant Data (Volume Change and Plant-Wide Manpower)

**Date:** 2026-10-15  
**Status:** accepted  
**Supersedes:** None (fulfils the vitals contract of ADR-043)

## Context

E10-05 must show "production volume change" and "manpower change" around an alert, using real data and `N/A` otherwise. The data does not match the client's example:

1. Production volume exists only per month (`ml_training_data.production_volume`). There is no daily or weekly volume, so "+20% in the last 7 days" cannot be computed.
2. The epic asks for the change in active employees of the section between the alert date and 7 days earlier, "using hire / termination dates if available". `employees` has no such dates, only `is_active` and `created_at`. A past head count cannot be rebuilt: `created_at` is the record creation date (a bulk import gives every employee the same day, so the change would read "0%" instead of "unknown"), and deactivation has no date.
3. `ml_training_data.man_power` is a plant total, not a section figure.

## Decision

1. **Both vitals compare the alert month with the previous month** from `ml_training_data`, labelled with the real period ("vs September 2026").
2. **Manpower uses the monthly `man_power`** (the epic's stated fallback) and its label says it is plant-wide ("vs September 2026 (plant-wide)" / "(seluruh pabrik)"), so a section manager does not read it as their own head count.
3. **No guessing.** A missing month, or a previous value of 0 or less, gives `null` and no label; the page shows `N/A` with the existing tooltip. The result is rounded to one decimal.
4. **`vitalsFor(Section, date)` keeps the section parameter** although the current sources ignore it, so a section-level source can replace the plant-wide one later without changing the controller or the E10-08 generator.

## Consequences

### Positive

- Every number on the card is traceable to a row and a period; no dummy value can reach the page.
- One query of at most two rows; the shape of the `vitals` prop did not change, so the frontend needed no change.
- The honest label avoids a wrong "Why" for the 5W2H generator: E10-08 can only cite what the label says.

### Negative

- Manpower says little about one section: a section that lost staff while the plant grew shows a positive or zero number. The label warns about it.
- A month-on-month change is a weak signal for a 1 to 3 day alert. The epic's "volume +10% and MP ≤ 0" cause detector (E10-08) works on the same coarse data.
- The numbers exist only for months an admin has entered in the ML training data; recent months are often missing, so `N/A` will be common until the ERP integration.

### Neutral

- When employee hire / termination dates (or daily ERP volume) are added, only `vitalsFor()` changes; the labels and the grid stay.
- The plant-wide manpower figure is visible to department-scoped users. It is a plant total with no personal or section data.
