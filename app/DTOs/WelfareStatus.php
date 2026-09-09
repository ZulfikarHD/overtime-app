<?php

namespace App\DTOs;

class WelfareStatus
{
    /**
     * @param  'safe'|'warning'|'danger'  $alertLevel
     * @param  list<array{
     *     type: 'safe'|'warning'|'danger',
     *     label: string,
     *     message: string
     * }>  $badges
     * @param  list<array{
     *     week_key: string,
     *     week_label: string,
     *     start_date: string,
     *     end_date: string,
     *     hours: float,
     *     is_over_limit: bool,
     *     is_current_week: bool
     * }>  $rollingWeeks
     */
    public function __construct(
        public int $employeeId,
        public float $currentWeekHours,
        public float $weeklyLimit,
        public int $consecutiveWeeks,
        public int $consecutiveWeeksAlert,
        public int $exceededWeeksCount,
        public float $safetyScorePct,
        public string $alertLevel,
        public array $badges,
        public array $rollingWeeks,
        public bool $isAdvisory = true,
        public string $advisoryMessage = '',
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'employee_id' => $this->employeeId,
            'current_week_hours' => $this->currentWeekHours,
            'weekly_limit' => $this->weeklyLimit,
            'consecutive_weeks' => $this->consecutiveWeeks,
            'consecutive_weeks_alert' => $this->consecutiveWeeksAlert,
            'exceeded_weeks_count' => $this->exceededWeeksCount,
            'safety_score_pct' => $this->safetyScorePct,
            'alert_level' => $this->alertLevel,
            'badges' => $this->badges,
            'rolling_weeks' => $this->rollingWeeks,
            'is_advisory' => $this->isAdvisory,
            'advisory_message' => $this->advisoryMessage,
        ];
    }
}
