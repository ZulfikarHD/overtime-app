<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Employee;
use App\Models\User;
use App\Notifications\FatigueAlertNotification;
use App\Services\Policy\OvertimePolicyEvaluator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class FatigueAlertService
{
    public function __construct(
        protected OvertimePolicyEvaluator $evaluator,
    ) {}

    /**
     * Evaluate an employee for consecutive-week fatigue limit breach and notify their Team Leader.
     * Enforces strict calendar-month deduplication (at most once per employee per calendar month).
     */
    public function evaluateAndNotifyForEmployee(
        Employee $employee,
        int $year,
        int $month,
        ?string $referenceDate = null,
    ): bool {
        $welfare = $this->evaluator->getEmployeeWelfareStatus($employee->id, $referenceDate);

        // Check if consecutive weeks threshold has been reached
        if ($welfare->consecutiveWeeks < $welfare->consecutiveWeeksAlert) {
            return false;
        }

        // Calendar-month deduplication check
        $alreadyNotified = DB::table('notifications')
            ->where('type', FatigueAlertNotification::class)
            ->where('data->employee_id', $employee->id)
            ->where('data->fiscal_year', $year)
            ->where('data->fiscal_month', $month)
            ->exists();

        if ($alreadyNotified) {
            return false;
        }

        $recipients = $this->getEligibleRecipients($employee);
        if ($recipients->isEmpty()) {
            return false;
        }

        Notification::send(
            $recipients,
            new FatigueAlertNotification(
                employee: $employee,
                consecutiveWeeks: $welfare->consecutiveWeeks,
                weeklyHours: $welfare->currentWeekHours,
                weeklyLimit: $welfare->weeklyLimit,
                fiscalYear: $year,
                fiscalMonth: $month,
            )
        );

        return true;
    }

    /**
     * Evaluate all active employees in a given section and dispatch deduplicated fatigue notifications.
     */
    public function evaluateAndNotifyForSection(
        int $sectionId,
        int $year,
        int $month,
        ?string $referenceDate = null,
    ): int {
        $employees = Employee::query()
            ->where('section_id', $sectionId)
            ->where('is_active', true)
            ->get();

        $dispatchedCount = 0;
        foreach ($employees as $employee) {
            if ($this->evaluateAndNotifyForEmployee($employee, $year, $month, $referenceDate)) {
                $dispatchedCount++;
            }
        }

        return $dispatchedCount;
    }

    /**
     * Retrieve eligible Team Leaders for the employee's section, falling back to Department Manager.
     *
     * @return Collection<int, User>
     */
    public function getEligibleRecipients(Employee $employee): Collection
    {
        if ($employee->section_id) {
            $teamLeaders = User::query()
                ->where('role', UserRole::TeamLeader)
                ->where('section_id', $employee->section_id)
                ->where('is_active', true)
                ->get();

            if ($teamLeaders->isNotEmpty()) {
                return $teamLeaders;
            }
        }

        if ($employee->department_id) {
            return User::query()
                ->where('role', UserRole::Manager)
                ->where('department_id', $employee->department_id)
                ->where('is_active', true)
                ->get();
        }

        return new Collection;
    }
}
