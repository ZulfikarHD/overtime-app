<?php

namespace App\Services\Policy;

use App\DTOs\PolicyWarning;
use App\DTOs\WelfareStatus;
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

    /**
     * Evaluates rolling 4-week employee welfare status, fatigue risk, and safety score.
     *
     * @param  int  $employeeId  The employee being evaluated
     * @param  string|null  $date  Reference operational date (defaults to current date in WIB)
     */
    public function getEmployeeWelfareStatus(int $employeeId, ?string $date = null): WelfareStatus
    {
        $employee = Employee::find($employeeId);
        if (! $employee) {
            return new WelfareStatus(
                employeeId: $employeeId,
                currentWeekHours: 0.0,
                weeklyLimit: 20.0,
                consecutiveWeeks: 0,
                consecutiveWeeksAlert: 3,
                exceededWeeksCount: 0,
                safetyScorePct: 100.0,
                alertLevel: 'safe',
                badges: [],
                rollingWeeks: [],
                isAdvisory: true,
                advisoryMessage: __('Indikator ini bersifat anjuran keselamatan untuk pencegahan kelelahan kerja dan tidak memblokir penugasan lembur darurat.'),
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
            ->where('overtime_items.status', 'APPROVED')
            ->whereBetween('overtime_submissions.operational_date', [$oldestWeekStart, $currentWeekEnd])
            ->select([
                'overtime_items.total_hours',
                'overtime_submissions.operational_date',
            ])
            ->get();

        $weeklyHoursMap = [];
        foreach ($records as $record) {
            $opDate = Carbon::parse($record->operational_date, 'Asia/Jakarta');
            $weekStartKey = $opDate->copy()->startOfWeek(Carbon::MONDAY)->toDateString();
            $weeklyHoursMap[$weekStartKey] = ($weeklyHoursMap[$weekStartKey] ?? 0.0) + (float) $record->total_hours;
        }

        $currentWeekHours = round((float) ($weeklyHoursMap[$currentWeekStart] ?? 0.0), 2);
        $currentWeekOver = $currentWeekHours > $weeklyLimit;

        // Calculate consecutive previous weeks exceeding weekly limit (looking back up to 12 weeks)
        $pastStreak = 0;
        for ($w = 1; $w <= 12; $w++) {
            $pastWeekKey = $targetDate->copy()->subWeeks($w)->startOfWeek(Carbon::MONDAY)->toDateString();
            $pastWeekTotal = round((float) ($weeklyHoursMap[$pastWeekKey] ?? 0.0), 2);
            if ($pastWeekTotal > $weeklyLimit) {
                $pastStreak++;
            } else {
                break;
            }
        }

        $consecutiveWeeks = $currentWeekOver ? (1 + $pastStreak) : $pastStreak;

        // Build 4 rolling weeks (Week -3 to Week 0)
        $rollingWeeks = [];
        $exceededWeeksCount = 0;

        for ($w = 3; $w >= 0; $w--) {
            $wDate = $targetDate->copy()->subWeeks($w);
            $wStart = $wDate->copy()->startOfWeek(Carbon::MONDAY);
            $wEnd = $wDate->copy()->endOfWeek(Carbon::SUNDAY);
            $wKey = $wStart->toDateString();
            $hours = round((float) ($weeklyHoursMap[$wKey] ?? 0.0), 2);
            $isOver = $hours > $weeklyLimit;

            if ($isOver) {
                $exceededWeeksCount++;
            }

            $label = match ($w) {
                0 => __('Minggu Ini'),
                1 => __('1 Minggu Lalu'),
                2 => __('2 Minggu Lalu'),
                3 => __('3 Minggu Lalu'),
                default => "Minggu -{$w}",
            };

            $rollingWeeks[] = [
                'week_key' => $wKey,
                'week_label' => $label,
                'start_date' => $wStart->format('d M'),
                'end_date' => $wEnd->format('d M'),
                'hours' => $hours,
                'is_over_limit' => $isOver,
                'is_current_week' => ($w === 0),
            ];
        }

        // Safety Score = 100% - (overloaded weeks / 4 weeks * 100%)
        $safetyScorePct = round(max(0.0, 100.0 - (($exceededWeeksCount / 4.0) * 100.0)), 1);

        $badges = [];
        $advisoryMessage = __('Indikator ini bersifat anjuran keselamatan untuk pencegahan kelelahan kerja dan tidak memblokir penugasan lembur darurat.');

        if ($consecutiveWeeks >= $consecutiveAlert) {
            $alertLevel = 'danger';
            $badges[] = [
                'type' => 'danger',
                'label' => __('Risiko Kelelahan'),
                'message' => __('Risiko Kelelahan: :weeks minggu berturut-turut melebihi batas!', ['weeks' => $consecutiveWeeks]),
            ];
            if ($currentWeekOver) {
                $badges[] = [
                    'type' => 'warning',
                    'label' => __('Batas Mingguan Terlampaui'),
                    'message' => __('Batas Mingguan Terlampaui (:current/:limit jam)', [
                        'current' => number_format($currentWeekHours, 1),
                        'limit' => number_format($weeklyLimit, 1),
                    ]),
                ];
            }
        } elseif ($currentWeekOver) {
            $alertLevel = 'warning';
            $badges[] = [
                'type' => 'warning',
                'label' => __('Mendekati Batas Mingguan'),
                'message' => __('Mendekati Batas Mingguan (:current/:limit jam)', [
                    'current' => number_format($currentWeekHours, 1),
                    'limit' => number_format($weeklyLimit, 1),
                ]),
            ];
        } else {
            $alertLevel = 'safe';
            $badges[] = [
                'type' => 'safe',
                'label' => __('Dalam Batas Aman'),
                'message' => __('Jam lembur mingguan dalam batas aman perusahaan.'),
            ];
        }

        return new WelfareStatus(
            employeeId: $employeeId,
            currentWeekHours: $currentWeekHours,
            weeklyLimit: $weeklyLimit,
            consecutiveWeeks: $consecutiveWeeks,
            consecutiveWeeksAlert: $consecutiveAlert,
            exceededWeeksCount: $exceededWeeksCount,
            safetyScorePct: $safetyScorePct,
            alertLevel: $alertLevel,
            badges: $badges,
            rollingWeeks: $rollingWeeks,
            isAdvisory: true,
            advisoryMessage: $advisoryMessage,
        );
    }
}
