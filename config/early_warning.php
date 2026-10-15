<?php

/*
|--------------------------------------------------------------------------
| Early Warning 5W2H generator (E10-08)
|--------------------------------------------------------------------------
|
| Every threshold of the rule-based follow-up generator lives here so the rules can be tuned at UAT
| (epic open item Q7) without touching code. Percentages are plain numbers (50 = 50 %).
|
*/

return [
    /*
     * Cause detectors.
     */
    'thresholds' => [
        // MANPOWER_SHORTAGE: production volume change (vs previous month) at or above this value, with manpower flat or lower.
        'volume_increase_pct' => 10,
        // CATEGORY_DOMINANT: one category holds at least this share of the period's overtime hours.
        'category_dominant_pct' => 50,
        // EMPLOYEE_CONCENTRATION: the top employees together hold at least this share of the period's hours.
        'employee_concentration_pct' => 40,
        // HOLIDAY_OVERTIME: overtime on HLR days is at least this share of the period's hours.
        'holiday_share_pct' => 30,
        // UNPLANNED_OVERTIME: actual hours exceed the published overtime plan by at least this much.
        'plan_overrun_pct' => 20,
    ],

    /*
     * Length of one shift in hours (Q9); used to turn excess hours per day into a head count.
     */
    'shift_hours' => 8,

    /*
     * Default length of the suggested follow-up in working days (Q9).
     */
    'follow_up_days' => 7,

    /*
     * Calendar days looked back from the alert date to judge the cause (widened to cover the whole streak).
     */
    'history_days' => 7,

    /*
     * How many top employees the EMPLOYEE_CONCENTRATION detector looks at.
     */
    'top_employees' => 3,

    /*
     * Days of approved overtime used to estimate the cost of one overtime hour of a section.
     */
    'cost_lookback_days' => 30,

    /*
     * Default PIC department (by `departments.code`) per cause (Q6). A cause without an entry, or whose department
     * does not exist, falls back to the alert's own department. Admins override these in Administration.
     */
    'default_pic_department_codes' => [
        'MANPOWER_SHORTAGE' => 'DEPT_HCM',
    ],
];
