# ADR-030: Executive Dashboard Tabbed Progressive Disclosure Architecture

**Date:** 2026-09-10  
**Status:** accepted  
**Supersedes:** Extends ADR-028 and ADR-029

## Context

During the rollout of Epic E09 (Stories E09-01 to E09-05), the Executive Operational Dashboard (`/dashboard`) consolidated 4 macro KPI cards with sparklines, 7 Chart.js canvases, a 50-row summary employee table, and legacy Sprint 1 status cards into a single endless vertical scroll. On factory-floor mobile devices and tablets, this resulted in an unreadable ~4,500px page height, causing severe scroll fatigue, visual clutter, and cognitive overload during rapid 60-second morning shift standups (07:15–08:30 WIB).

This violated the core UI/UX guardrails outlined in `docs/instruction-template/ux-planning.md`:

1. **Smartphone-Primary UX**: Shopfloor supervisors abandon software requiring endless vertical scrolling.
2. **Cognitive Load Budget**: Maximum 3–4 primary visual elements per viewport.
3. **Standup Velocity**: Standup situational awareness must be achieved in $\le 4$ steps within 60 seconds.

## Decision

We redesigned `/dashboard` from a flat, vertical scroll into a **progressive-disclosure, 3-tab operational cockpit** under the single route `/dashboard`:

1. **Persistent Macro KPI Header Row (Always Visible)**:
    - Houses the 4 macro KPI cards with mini sparklines (E09-01: Production Volume, Working Days, Man Power, Burn Index Plan vs Actual).
    - Positioned persistently above the tab navigation to guarantee instantaneous 60-second morning pulse awareness regardless of the active tab.

2. **Three Focused Operational Tabs**:
    - **Tab 1: `Laju Lembur & Seksi` (`?tab=pacing`)** [Default]:
        - Hero: Daily Cumulative Burn Line Chart with Plan vs Actual vs ML Trajectory (E09-02).
        - Mid: Section Burn Comparison Horizontal Bar Chart (E09-03).
        - Focus: Real-time budget burn pacing and identifying outlier sections.
    - **Tab 2: `Distribusi & Tren` (`?tab=distribution`)**:
        - Houses the 5-chart multi-grid band (E09-04): Overtime Leaderboard (Top 10), Category Donut (CapEx vs OpEx), 12-Month Trend Line, Daily Index Trend, and Day Type Breakdown.
        - Focus: Resource allocation across people, project categories, and holiday shifts.
    - **Tab 3: `Daftar Karyawan` (`?tab=employees`)**:
        - Houses the high-density, searchable Summary Employee Overtime Table (E09-05) with 50-row virtualized pagination, sorting, SPKL status badges, and the slide-in `EmployeeQuickDossierDrawer.vue`.
        - Focus: Shopfloor personnel management, SPKL verification, and individual fatigue inspection.

3. **URL State Synchronization & Deep Linking**:
    - Tab state is bidirectionally synchronized with the browser query parameter (`/dashboard?tab=pacing|distribution|employees`) via `window.history.replaceState` and Inertia visit params.
    - Filtering by department or date preserves the active tab via Inertia partial reloads.
    - Selecting a category in the Category Donut automatically navigates to Tab 3 (`employees`) with that category pre-filtered.

4. **Streamlined Infrastructure & System Readiness Section**:
    - Legacy Sprint 1 readiness banners and role capability cards are compacted into a clean system status pill (`Sprint 1 Active • OT-CapEx System`) and bottom status row, eliminating visual noise while maintaining backward-compatibility with authentication tests.

## Consequences

### Positive

- **Drastic Reduction in Scroll Depth**: Page height reduced by ~70% per view. Each tab fits cleanly within 1–2 viewport heights on desktop and mobile.
- **Zero Route Proliferation**: All features remain under the single `/dashboard` route, adhering to the strict anti-splitting rule in `docs/scrum/Epic-09-ux-plan.md`.
- **Preserved Test Automation**: Browser test suites for all 5 sub-epics pass with clean, explicit tab transitions matching authentic user flows.
- **Improved Chart Rendering Performance**: Non-active tabs are hidden, reducing simultaneous canvas rendering overhead during initial page load.

### Negative / Trade-offs

- Tab navigation requires 1 explicit click to switch between burn pacing and employee tables, but this trade-off dramatically improves usability and eliminates scroll chaos.
