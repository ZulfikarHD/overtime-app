# ADR-015: Advisory Overtime Policy Soft Warning Indicators

**Date:** 2026-09-08  
**Status:** accepted  
**Supersedes:** None

## Context

In automotive manufacturing plants, overtime spikes frequently occur during shift handovers due to machine breakdowns, trial runs, or line replenishment. While management sets guidelines to mitigate operator fatigue and maintain labor standards (e.g., maximum 20 hours of overtime per week), emergency plant stoppages must not be paralyzed by hard software locks.

If timesheet submissions were rigidly blocked when thresholds are breached, Team Leaders would be forced to bypass the digital system (e.g., keeping offline paper tallies, delaying submission past payroll cutoffs, or misallocating hours to peers), compromising audit trails and safety tracking.

## Decision

We implemented an **Advisory Soft Policy Warning Engine** (E03-06, BR-06):

1. **Non-Blocking Operation**: Policy threshold evaluations produce advisory warnings that are clearly surfaced to supervisors and managers, but **never block** or disable form submission.
2. **Two-Tier Visual Indicator System**:
    - **Caution Yellow (`warning`)**: Triggered when the current week's cumulative overtime plus newly submitted hours exceeds the department or plant-wide `weekly_soft_limit_hours` (default: 20.0 hrs).
    - **Critical Red (`danger`)**: Triggered when an operator has sustained high workload across `consecutive_weeks_alert` or more consecutive weeks (default: 3 weeks).
3. **Debounced Client-Side Evaluation (500ms)**: Reactive API checks (`GET /overtime/policy-check`) run seamlessly on input blur or keystroke debounce, preventing server overload during 1,500-item shift handover bursts.
4. **Permanent Audit Visibility**: Policy status is evaluated and cached during submission, ensuring Department Managers reviewing line items in `SubmissionDetailModal` have immediate visibility into operator workload trends.

## Consequences

### Positive

- **Zero Shift Stoppage**: Frontline supervisors can record emergency shifts without friction or bureaucratic software barriers.
- **Proactive Fatigue Awareness**: Supervisors receive immediate visual feedback on operator workload before finalizing shift assignments.
- **Managerial Decision Support**: Department Managers can rebalance crew staffing during morning approval reviews based on explicit fatigue indicators.
- **Hierarchical Policy Inheritance**: Department-specific thresholds automatically take precedence over plant-wide defaults.

### Negative

- Overtime limits are advisory; enforcement relies on supervisory discipline and managerial review rather than automated system blocks.

### Neutral

- Weekly calculations are evaluated on a Monday-to-Sunday operational calendar strictly aligned with `Asia/Jakarta` (WIB).
