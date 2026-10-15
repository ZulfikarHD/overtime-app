# ADR-046: Early Warning Dashboard Link Built from the Chart Scope

**Date:** 2026-10-15
**Status:** accepted

## Context

E10-07 asks for a button on the Dashboard Early Warning chart that opens the new inbox "filtered by the dashboard's current department/section and date". The Dashboard filters (department, section, date) are already reflected in the chart payload (`scope`, `fiscal_year`, `fiscal_month`, `cutoff_day`). The inbox takes `department_id`, `section_id`, `date_from`, `date_to` and validates them (`Y-m-d`, range under 93 days). The button must not become a second source of truth for who may see what.

## Decision

Build the link in `DailyIndexTrendChart.vue` from its own `data` prop with the Wayfinder `early-warning.index` function. The range is the first day of the chart's month up to its cut-off day; the dates are omitted when there is no cut-off. The component shows the button only for admin, manager and team leader. No backend change, no new prop. Department scoping stays server-side in the inbox.

## Consequences

### Positive

- No new route, prop or controller code; one component changes.
- The inbox shows the same days the chart does, so the numbers match what the user just saw.
- A tampered query cannot widen access: `EarlyWarningInboxRequest` and `EarlyWarningService::inboxScope()` still force a non-admin to their own department.

### Negative

- The role list is duplicated from the sidebar and the route middleware. If the roles change, the chart must change too (the server still answers 403 in the meantime).
- A Team Leader's dashboard is narrowed to their section, so the link carries that section; the inbox for other roles is not narrowed that way.

### Neutral

- Two translation keys used only by the removed Dashboard panel were deleted instead of "moved", because the new form already has its own keys.
