# ADR-022: Unified Single-Surface Employee Dossier Hub and Client-Side Cached Lookups

**Date:** 2026-09-09  
**Status:** accepted  
**Deciders:** Lead Architect, Frontend Engineering Lead

---

## Context

In an industrial manufacturing plant operating three shifts with fast handover windows (10–15 minutes), shift supervisors (Team Leaders) and department managers need to look up employee overtime profiles, verify accumulated monthly hours, and inspect fatigue indicators before scheduling critical overtime tasks.

A fragmented information architecture—splitting employee search, roster lists, overview KPIs, and chronological timesheets across different URL endpoints and modal windows—introduces navigational friction, excessive page transitions, and mental fatigue for plant supervisors. Furthermore, querying 1,500+ plant employees over slow factory floor Wi-Fi connections can lead to sluggish input response if search requests are unbounded.

---

## Decision

1. **Unified Single-Surface Dossier Hub**:
    - Consolidate the employee lookup screen (`/reports/employees`) and individual dossier view (`/reports/employees/{npk}`) into a single primary Vue page component: `resources/js/pages/reports/EmployeeDossier.vue`.
    - When no employee NPK is selected in the URL, the page renders the search-as-you-type bar, recent lookups strip, and the user's authorized section roster quick-pick grid.
    - When an NPK parameter is present, the page renders the persistent dossier header card with employee identity, active status, period picker, and tabs for Overview & Welfare and Timesheet.

2. **Debounced Live Search with Server-Side Hierarchical Scoping**:
    - The search input triggers backend queries only when 3 or more characters are entered, debounced at 300 milliseconds.
    - Results are capped at 10 matches and strictly scoped at the service layer:
        - Admin: plant-wide access.
        - Manager: scoped to user's assigned `department_id`.
        - Team Leader: scoped to user's assigned `section_id`.
        - User (Operator): search endpoint forbidden (HTTP 403); direct access restricted strictly to their own NPK.

3. **Client-Side Zero-Latency Recent Lookups**:
    - Store the last 5 inspected employee profiles in browser `localStorage` (`recentLookups`) rather than persisting lookup histories to the database.
    - Automatically record the employee upon dossier view mount, enabling 1-click zero-latency return access without database writes.

---

## Consequences

### Positive

- **Ergonomic Simplicity**: Supervisors can search, inspect, and return to recent subordinates in ≤ 2 clicks without navigating multiple sub-menus.
- **Shopfloor Network Efficiency**: Debounced input and 10-item result capping eliminate heavy network payloads and UI freezing on factory Wi-Fi.
- **Zero Database Write Overhead**: Storing recent employee lookups in `localStorage` avoids database writes and unnecessary database table migrations.
- **Architectural Cohesion**: Overview analytics, peer benchmarking, welfare indicators, and chronological timesheets share a consistent period and employee context.

### Negative

- **Cross-Device Cache Isolation**: Recent lookups are stored per browser/device, so a supervisor switching from a factory desktop terminal to a mobile tablet will start with an empty recent list on the new device.
- **Roster Initial Payload**: Loading the section roster on first visit requires a database query, mitigated by scoping to the user's section/department and limiting to active records.

### Neutral

- Tab state (`overview` vs `timesheet`) and reporting periods are synchronized via URL query parameters (`?tab=...&year=...&month=...`) preserving Inertia scroll and state.

---

## Related

- [Epic-06 UX Plan](../../scrum/Epic-06-ux-plan.md)
- [Individual Employee Reporting & Welfare Tracking Feature Specification](../features/employee-welfare-report.md)
- [User Guide: Individual Employee Dossier](../../user-docs/guides/individual-employee-dossier.md)
