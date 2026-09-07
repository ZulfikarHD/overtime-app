<?php

namespace App\Services\Policy;

use App\DTOs\PolicyWarning;
use App\Models\Employee;
use App\Models\OvertimeItem;
use App\Services\PolicyThresholdService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class OvertimePolicyEvaluator
{
    public function __construct(
        public PolicyThresholdService $policyThresholdService,
    ) {}

    /**
     * Evaluates company overtime soft policy limits for an employee.
     *
     * @param  int  $employeeId  The employee being evaluated
     * @param  float  $additionalHours  Hours being added in the current submission
     * @param  string|null  $date  Operational date in Y-m-d format (defaults to current date in WIB)
     * @param  int|null  $excludeSubmissionId  Optional submission ID to exclude (e.g. when updating)
     */
    public function evaluateEmployee(
        int $employeeId,
        float $additionalHours = 0.0,
        ?string $date = null,
        ?int $excludeSubmissionId = null,
    ): PolicyWarning {
        $employee = Employee::find($employeeId);
        if (! $employee) {
            return new PolicyWarning(
                level: 'none',
                message: '',
                weeklyTotal: 0.0,
                consecutiveWeeks: 0,
                weeklyLimit: 20.0,
            );
        }

        $threshold = $this->policyThresholdService->getForDepartment($employee->department_id);
        $weeklyLimit = (float) $threshold->weekly_soft_limit_hours;
        $consecutiveAlert = (int) $threshold->consecutive_weeks_alert;

        $targetDate = $date
            ? Carbon::parse($date, 'Asia/Jakarta')
            : Carbon::now('Asia/Jakarta');

        $currentWeekStart = $targetDate->copy()->startOfWeek(Carbon::MONDAY)->toDateString();
        $currentWeekEnd = $targetDate->copy()->endOfWeek(Carbon::SUNDAY)->toDateString();
        $oldestWeekStart = $targetDate->copy()->subWeeks(12)->startOfWeek(Carbon::MONDAY)->toDateString();

        /** @var Collection<int, OvertimeItem> $records */
        $records = OvertimeItem::query()
            ->join('overtime_submissions', 'overtime_items.overtime_submission_id', '=', 'overtime_submissions.id')
            ->where('overtime_items.employee_id', $employeeId)
            ->where('overtime_items.status', '!=', 'REJECTED')
            ->whereNotIn('overtime_submissions.status', ['REJECTED', 'DRAFT'])
            ->whereBetween('overtime_submissions.operational_date', [$oldestWeekStart, $currentWeekEnd])
            ->when($excludeSubmissionId !== null, function ($query) use ($excludeSubmissionId) {
                $query->where('overtime_submissions.id', '!=', $excludeSubmissionId);
            })
            ->select([
                'overtime_items.total_hours',
                'overtime_submissions.operational_date',
            ])
            ->get();

        $currentWeekHours = 0.0;
        $weeklyHoursMap = [];

        foreach ($records as $record) {
            $opDate = Carbon::parse($record->operational_date, 'Asia/Jakarta');
            $weekStartKey = $opDate->copy()->startOfWeek(Carbon::MONDAY)->toDateString();
            $hours = (float) $record->total_hours;

            if ($weekStartKey === $currentWeekStart) {
                $currentWeekHours += $hours;
            } else {
                $weeklyHoursMap[$weekStartKey] = ($weeklyHoursMap[$weekStartKey] ?? 0.0) + $hours;
            }
        }

        $weeklyTotal = round($currentWeekHours + max(0.0, $additionalHours), 2);
        $currentWeekOver = $weeklyTotal > $weeklyLimit;

        // Calculate consecutive previous weeks exceeding weekly limit
        $pastStreak = 0;
        for ($w = 1; $w <= 12; $w++) {
            $pastWeekKey = $targetDate->copy()->subWeeks($w)->startOfWeek(Carbon::MONDAY)->toDateString();
            $pastWeekTotal = round($weeklyHoursMap[$pastWeekKey] ?? 0.0, 2);
            if ($pastWeekTotal > $weeklyLimit) {
                $pastStreak++;
            } else {
                break;
            }
        }

        $consecutiveWeeks = $currentWeekOver ? (1 + $pastStreak) : $pastStreak;

        if ($consecutiveWeeks >= $consecutiveAlert) {
            $level = 'danger';
            $message = __('High Workload: :weeks consecutive weeks over limit', ['weeks' => $consecutiveWeeks]);
        } elseif ($weeklyTotal > $weeklyLimit) {
            $level = 'warning';
            $message = __('Weekly limit may be exceeded (:total/:limit hrs)', [
                'total' => number_format($weeklyTotal, 1),
                'limit' => number_format($weeklyLimit, 1),
            ]);
        } else {
            $level = 'none';
            $message = '';
        }

        return new PolicyWarning(
            level: $level,
            message: $message,
            weeklyTotal: $weeklyTotal,
            consecutiveWeeks: $consecutiveWeeks,
            weeklyLimit: $weeklyLimit,
        );
    }
}
