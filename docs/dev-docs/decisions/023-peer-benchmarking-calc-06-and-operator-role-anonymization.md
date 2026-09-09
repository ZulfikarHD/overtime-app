# ADR-023: Peer Benchmarking Workload Distribution (CALC-06) and Server-Side Operator Anonymization

**Date:** 2026-09-09  
**Status:** accepted  
**Deciders:** Lead Architect, Frontend Engineering Lead

---

## Context

In an industrial manufacturing plant operating multi-shift rotations, overtime hours can become concentrated on a small group of senior operators or key technicians. This workload disparity introduces two major operational concerns:

1. **Industrial Safety & Worker Fatigue**: Overworked personnel face elevated fatigue, resulting in machinery incidents, assembly defects, and unplanned downtime.
2. **Shopfloor Dissatisfaction & Perceived Favoritism**: Unequal overtime allocation creates suspicion and perceived bias among line workers regarding shift scheduling.

Supervisors (Team Leaders and Department Managers) require section-wide benchmarking data to balance shifts fairly. Concurrently, line operators (`User` role) need visibility into how their personal hours compare to the section norm. However, exposing individual co-workers' names, hours, and earnings directly to line operators risks gossip, interpersonal friction, and workplace envy.

---

## Decision

1. **CALC-06 Peer Variance Calculation Engine**:
    - Calculate the per-capita section average:
      $$\text{Section Average Hours} = \frac{\sum \text{Approved Overtime Hours in Section}}{\text{Total Active Employees in Section}}$$
    - Calculate individual peer variance (CALC-06):
      $$\text{CALC-06 Variance} = \text{Individual Hours} - \text{Section Average Hours}$$
    - Apply semantic industrial badge styling:
        - **Overloaded** ($\text{Variance} > 0$): Amber warning badge (`+X.X jam di atas rata-rata seksi`).
        - **Underloaded** ($\text{Variance} < 0$): Emerald rested badge (`-X.X jam di bawah rata-rata seksi`).
        - **Balanced** ($\text{Variance} = 0$): Slate neutral badge (`0.0 jam sama dengan rata-rata seksi`).

2. **Single-Surface Overview Tab Integration (`PeerComparisonPanel.vue`)**:
    - Embed the peer benchmarking panel directly inside the Overview Tab (`Ringkasan & Kesejahteraan`) of `EmployeeDossier.vue` per UX Plan constraints (no separate route or modal).
    - Display a responsive 3-metric summary row: Individual Overtime Hours, Section Average Hours, and the CALC-06 Variance Pill.
    - Embed a Chart.js section distribution histogram (`SectionDistributionChart.vue`) highlighting the active employee in ISUZU Red (`#cc0000`) and peers in neutral Slate (`#94a3b8`).
    - Include "Top 5 Highest Hours" and "Bottom 5 Lowest Hours" quick lists for rapid supervisor audit.

3. **Server-Side Anonymization Pipeline for Operator Role**:
    - When the authenticated user has the `User` role (`$user->isUser()`), the backend pipeline (`EmployeeReportService@getPeerComparison`) strips all identifiable co-worker data:
        - `employee_id` is set to `null`.
        - `name` is masked to `Karyawan #<rank>`.
        - `npk` is masked to `••••`.
        - `job_position` is set to `null`.
    - The user's own record retains their true name, NPK, and ranking.
    - Supervisors (`Team Leader`, `Manager`, `Admin`) receive unmasked names and NPKs to make informed workload distribution decisions.

---

## Consequences

### Positive

- **Workload Transparency Without Friction**: Line operators understand their relative standing without knowing co-workers' confidential details.
- **Proactive Fatigue Mitigation**: Supervisors immediately identify outlier employees who have accumulated excessive overtime relative to section peers.
- **Zero Route Footprint Growth**: Retains the UX Plan's strict 2-route limit for Epic E06, housing all benchmarking components inside the unified dossier overview tab.
- **Secure by Default**: Anonymization is enforced at the service/controller layer before Inertia props are serialized to JSON, preventing sensitive names from being inspected via browser devtools.

### Negative

- **Section Aggregation Overhead**: Computing the distribution requires aggregating monthly approved items across all section members. This is mitigated by indexing `(employee_id, status)` and grouping within a single database query.
- **Dual Display Testing**: Requires maintaining automated test assertions for both supervisor (named) and operator (anonymized) rendering modes.

---

## Related

- [Epic-06 Scrum Specification](../../scrum/Epic-06.md)
- [Epic-06 UX Plan](../../scrum/Epic-06-ux-plan.md)
- [Individual Employee Reporting & Welfare Tracking Feature Specification](../features/employee-welfare-report.md)
- [User Guide: Individual Employee Dossier](../../user-docs/guides/individual-employee-dossier.md)
