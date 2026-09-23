# ADR-039: Department-Default Dashboard Scope with Optional Section Drill-Down

**Date:** 2026-09-24  
**Status:** accepted  
**Supersedes:** Extends ADR-028 and ADR-030 filter-bar behavior

## Context

The Executive Operational Dashboard filter bar historically scoped data by
department (and date) only. Managers often need a quick drill-down into one
production section during morning standup without leaving `/dashboard` or
opening Burn Index. Leaving section filtering only inside individual chart
headers (for example the legacy daily burn card) was inconsistent after the
tabbed progressive-disclosure redesign moved the hero chart layout.

## Decision

Keep **department-level aggregation as the default** when no section is
selected (`section_id` absent / `Semua Seksi (Departemen)`). Add a second
dropdown in the shared dashboard filter bar so admins and managers can
optionally narrow KPI cards and charts to one section. Team Leaders remain
locked to their assigned section and do not see the picker.

Invalid combinations (section outside the active department) are dropped by
`DashboardController::sanitizeSectionId()` before service calls.

## Consequences

### Positive

- One filter bar controls the whole `/dashboard` surface (KPI + all tabs).
- Default view stays aligned with department standup workflows.
- Section drill-down does not create a new route or sidebar item (UX plan
  anti-splitting preserved).

### Negative

- Plant-wide admin section lists can be large until a department is chosen.
- Section Burn Comparison remains department-scoped so peer ranking stays
  visible even when a single section is selected.

### Neutral

- Query param `section_id` is now a first-class dashboard filter alongside
  `department_id` and `date`.
