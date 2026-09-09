# ADR-024: Rolling 4-Week Welfare Indicators and Calendar-Month Fatigue Alert Deduplication

**Date:** 2026-09-09
**Status:** accepted

## Context

In automotive manufacturing plants, prolonged employee overtime across multiple consecutive weeks significantly increases physical exhaustion, workplace accidents, and production line defects. However, strict hard blocks on emergency shift staffing can stall production schedules when machine breakdowns or urgent supply chain bottlenecks occur.

Prior to Story E06-04, the application provided weekly policy warnings during SPKL entry, but lacked:

1. A continuous, rolling 4-week historical trend on the employee's dossier to detect cumulative fatigue cycles.
2. A single intuitive safety recovery score summarizing multi-week rest adequacy.
3. Automated, proactive alerts to Team Leaders when a direct report maintains a dangerous consecutive-week overwork streak.
4. Notification deduplication guardrails to prevent alert fatigue during frequent daily batch approvals.

## Decision

We implemented a comprehensive, advisory-only safety and fatigue soft indicator system:

1. **Rolling 4-Week Evaluation Engine**:
    - `OvertimePolicyEvaluator@getEmployeeWelfareStatus` queries approved overtime hours across a 12-week lookback window, structuring the 4 most recent weekly cycles (Week -3 to Week 0).
    - Computes overloaded weeks count where weekly hours exceed the department's configured `weekly_soft_limit_hours` (default: 20 hours).
    - Computes consecutive overloaded weeks streak looking back chronologically up to 12 weeks.

2. **Standardized Safety Score Formula**:
    - Safety Score % is calculated as `round(100.0 - ((exceeded_weeks_count / 4.0) * 100.0), 1)`.
    - Scaled from 100% (perfect rest balance, 0 overloaded weeks) to 0% (high fatigue risk, 4 overloaded weeks).

3. **Advisory Soft Indicators (Zero Operational Roadblocks)**:
    - All visual elements (`FatigueRollingChart.vue`, `SafetyScoreGauge.vue`, status badges) are strictly advisory.
    - An explicit industrial disclaimer banner clarifies that indicators never block urgent shift assignments or approvals.

4. **Calendar-Month Notification Deduplication**:
    - `FatigueAlertService` evaluates employees in a section post-approval (`RecalculateMonthlyBurnSnapshotJob`).
    - If an employee reaches `consecutive_weeks >= consecutive_weeks_alert` (default: 3 weeks), the service queries the `notifications` table:
        ```php
        DB::table('notifications')
            ->where('type', FatigueAlertNotification::class)
            ->where('data->employee_id', $employee->id)
            ->where('data->fiscal_year', $year)
            ->where('data->fiscal_month', $month)
            ->exists();
        ```
    - If already sent in the current calendar month, subsequent approvals update visual metrics reactively without spamming Team Leaders with duplicate in-app alerts.

5. **Single-Click Topbar Navigation**:
    - `NotificationBell.vue` renders fatigue alerts with distinct red badge and employee details.
    - Clicking "Buka Dossier" navigates directly to `/reports/employees/{npk}?tab=overview`.

## Consequences

### Positive

- **Transparent Fatigue Visibility**: Supervisors can easily diagnose cumulative overwork and rotate team members before safety incidents occur.
- **Zero Production Bottlenecks**: Plant operations are not impeded during critical emergency repairs or assembly bottlenecks.
- **Noise-Free Notifications**: Strict calendar-month deduplication eliminates notification flooding while ensuring supervisors are proactively alerted on the initial breach.
- **Unified Industrial Interface**: All metrics reside within Tab 1 (`Ringkasan & Kesejahteraan`) on `EmployeeDossier.vue` with no fragmented routes.

### Negative

- **Advisory Reliance**: Because alerts do not hard-block submissions, managerial diligence is required to act on fatigue warnings.

### Neutral

- Background recalculation adds a lightweight post-approval query per section in `RecalculateMonthlyBurnSnapshotJob`.
