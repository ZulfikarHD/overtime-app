<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\CapexProject;
use App\Models\OvertimeItem;
use App\Models\User;
use App\Notifications\CapexBurnAlertNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class CapExAccountingService
{
    /**
     * Compute comprehensive labor burn metrics, team contribution roster,
     * and weekly timeline burndown for a specific CapEx project.
     *
     * @return array{
     *     allocated_hours: float,
     *     consumed_hours: float,
     *     remaining_hours: float,
     *     allocated_budget_idr: float,
     *     consumed_cost_idr: float,
     *     remaining_budget_idr: float,
     *     burn_index_pct: float,
     *     physical_progress_pct: float,
     *     milestone_burn_ratio: float,
     *     days_remaining: int,
     *     is_at_risk: bool,
     *     is_overdue: bool,
     *     top_contributors: list<array{
     *         employee_id: int,
     *         npk: string,
     *         name: string,
     *         section: string,
     *         hours: float,
     *         cost_idr: float,
     *         percentage: float
     *     }>,
     *     weekly_timeline: list<array{
     *         week_number: int,
     *         label: string,
     *         date_range: string,
     *         actual_hours: float,
     *         cumulative_actual_hours: float|null,
     *         planned_cumulative_hours: float,
     *         is_current: bool,
     *         is_future: bool
     *     }>
     * }
     */
    public function getProjectLaborMetrics(CapexProject $project): array
    {
        $allocatedHours = (float) $project->allocated_labor_hours;
        $allocatedBudget = (float) $project->allocated_labor_budget_idr;
        $physicalPct = (float) $project->physical_progress_pct;

        // Query approved items attributed to this project
        $totals = OvertimeItem::query()
            ->where('capex_project_id', $project->id)
            ->where('status', 'APPROVED')
            ->selectRaw('COALESCE(SUM(hours_project), 0) as consumed_hours, COALESCE(SUM(total_cost_snapshot), 0) as consumed_cost')
            ->first();

        $consumedHours = (float) ($totals->consumed_hours ?? 0);
        $consumedCost = (float) ($totals->consumed_cost ?? 0);

        $burnIndexPct = $allocatedHours > 0 ? round(($consumedHours / $allocatedHours) * 100, 1) : 0.0;
        $milestoneBurnRatio = $physicalPct > 0 ? round($burnIndexPct / $physicalPct, 2) : 0.0;

        $now = Carbon::now('Asia/Jakarta')->startOfDay();
        $targetDate = $project->target_end_date ? Carbon::parse($project->target_end_date)->startOfDay() : null;
        $daysRemaining = $targetDate ? (int) $now->diffInDays($targetDate, false) : 0;
        $isOverdue = $targetDate ? $now->greaterThan($targetDate) && ! in_array($project->status, ['COMPLETED', 'CLOSED'], true) : false;

        $isAtRisk = in_array($project->status, ['ACTIVE', 'ON_HOLD'], true)
            && ($milestoneBurnRatio > 1.20 || $burnIndexPct > 90.0);

        $contributors = $this->getTeamContributors($project->id, $consumedHours);
        $timeline = $this->getWeeklyTimeline($project, $allocatedHours);

        return [
            'allocated_hours' => $allocatedHours,
            'consumed_hours' => $consumedHours,
            'remaining_hours' => max(0.0, round($allocatedHours - $consumedHours, 2)),
            'allocated_budget_idr' => $allocatedBudget,
            'consumed_cost_idr' => $consumedCost,
            'remaining_budget_idr' => max(0.0, round($allocatedBudget - $consumedCost, 2)),
            'burn_index_pct' => $burnIndexPct,
            'physical_progress_pct' => $physicalPct,
            'milestone_burn_ratio' => $milestoneBurnRatio,
            'days_remaining' => $daysRemaining,
            'is_at_risk' => $isAtRisk,
            'is_overdue' => $isOverdue,
            'top_contributors' => $contributors,
            'weekly_timeline' => $timeline,
        ];
    }

    /**
     * Retrieve ranked team contributors sorted by approved hours descending.
     *
     * @return list<array{
     *     employee_id: int,
     *     npk: string,
     *     name: string,
     *     section: string,
     *     hours: float,
     *     cost_idr: float,
     *     percentage: float
     * }>
     */
    public function getTeamContributors(int $projectId, float $totalConsumedHours): array
    {
        $rows = OvertimeItem::query()
            ->where('capex_project_id', $projectId)
            ->where('status', 'APPROVED')
            ->selectRaw('employee_id, SUM(hours_project) as total_hours, SUM(total_cost_snapshot) as total_cost')
            ->groupBy('employee_id')
            ->orderByDesc('total_hours')
            ->with(['employee:id,npk,full_name,section_id', 'employee.section:id,name'])
            ->get();

        return $rows->map(function ($row) use ($totalConsumedHours) {
            $hours = (float) $row->total_hours;
            $cost = (float) $row->total_cost;
            $pct = $totalConsumedHours > 0 ? round(($hours / $totalConsumedHours) * 100, 1) : 0.0;

            return [
                'employee_id' => (int) $row->employee_id,
                'npk' => (string) ($row->employee?->npk ?? '—'),
                'name' => (string) ($row->employee?->full_name ?? '—'),
                'section' => (string) ($row->employee?->section?->name ?? '—'),
                'hours' => round($hours, 1),
                'cost_idr' => round($cost, 2),
                'percentage' => $pct,
            ];
        })->values()->all();
    }

    /**
     * Build weekly timeline burndown showing weekly consumption vs linear planned allocation.
     *
     * @return list<array{
     *     week_number: int,
     *     label: string,
     *     date_range: string,
     *     actual_hours: float,
     *     cumulative_actual_hours: float|null,
     *     planned_cumulative_hours: float,
     *     is_current: bool,
     *     is_future: bool
     * }>
     */
    public function getWeeklyTimeline(CapexProject $project, float $allocatedHours): array
    {
        $startDate = $project->start_date
            ? Carbon::parse($project->start_date)->startOfDay()
            : Carbon::now('Asia/Jakarta')->startOfDay();

        $targetEndDate = $project->target_end_date
            ? Carbon::parse($project->target_end_date)->startOfDay()
            : (clone $startDate)->addMonths(3);

        if ($targetEndDate->lessThan($startDate)) {
            $targetEndDate = (clone $startDate)->addDays(7);
        }

        // Fetch all approved overtime items for this project with submission operational dates
        $approvedItems = OvertimeItem::query()
            ->join('overtime_submissions', 'overtime_items.overtime_submission_id', '=', 'overtime_submissions.id')
            ->where('overtime_items.capex_project_id', $project->id)
            ->where('overtime_items.status', 'APPROVED')
            ->select([
                'overtime_items.hours_project',
                'overtime_submissions.operational_date',
            ])
            ->get();

        $today = Carbon::now('Asia/Jakarta')->startOfDay();

        // Calculate span of weeks (minimum 4 weeks, maximum 26 weeks for optimal chart display)
        $totalDays = max(7, $startDate->diffInDays($targetEndDate) + 1);
        $totalWeeks = max(4, min(26, (int) ceil($totalDays / 7)));

        $timeline = [];
        $runningCumulative = 0.0;
        $hasItems = $approvedItems->isNotEmpty();

        for ($w = 1; $w <= $totalWeeks; $w++) {
            $wStart = (clone $startDate)->addDays(($w - 1) * 7);
            $wEnd = (clone $wStart)->addDays(6);

            $isCurrent = $today->betweenIncluded($wStart, $wEnd);
            $isFuture = $wStart->greaterThan($today);

            // Sum actual hours for this week window
            $weekActualHours = 0.0;
            foreach ($approvedItems as $item) {
                if ($item->operational_date) {
                    $itemDate = Carbon::parse($item->operational_date)->startOfDay();
                    if ($itemDate->betweenIncluded($wStart, $wEnd)) {
                        $weekActualHours += (float) $item->hours_project;
                    }
                }
            }

            $plannedCumulative = $allocatedHours > 0
                ? round(($allocatedHours / $totalWeeks) * $w, 1)
                : 0.0;

            if ($isFuture && ! $hasItems) {
                $cumulativeActual = null;
            } elseif ($isFuture && $weekActualHours === 0.0) {
                $cumulativeActual = null;
            } else {
                $runningCumulative += $weekActualHours;
                $cumulativeActual = round($runningCumulative, 1);
            }

            $dateRangeLabel = $wStart->format('d/m').' - '.$wEnd->format('d/m');

            $timeline[] = [
                'week_number' => $w,
                'label' => 'M'.$w,
                'date_range' => $dateRangeLabel,
                'actual_hours' => round($weekActualHours, 1),
                'cumulative_actual_hours' => $cumulativeActual,
                'planned_cumulative_hours' => $plannedCumulative,
                'is_current' => $isCurrent,
                'is_future' => $isFuture,
            ];
        }

        return $timeline;
    }

    /**
     * Evaluate CapEx Burn Alert (>80% allocation) and dispatch notification if not already sent this calendar month.
     */
    public function evaluateAndNotifyBurnAlert(CapexProject $project, ?float $burnIndexPct = null): bool
    {
        $allocatedHours = (float) $project->allocated_labor_hours;
        if ($allocatedHours <= 0) {
            return false;
        }

        if ($burnIndexPct === null) {
            $consumedHours = (float) OvertimeItem::query()
                ->where('capex_project_id', $project->id)
                ->where('status', 'APPROVED')
                ->sum('hours_project');
            $burnIndexPct = ($consumedHours / $allocatedHours) * 100;
        } else {
            $consumedHours = round(($allocatedHours * $burnIndexPct) / 100, 1);
        }

        if ($burnIndexPct <= 80.0) {
            return false;
        }

        // Deduplication: ensure at most 1 alert fires per project per calendar month
        $startOfMonth = Carbon::now('Asia/Jakarta')->startOfMonth();
        $alreadyNotified = DB::table('notifications')
            ->where('type', CapexBurnAlertNotification::class)
            ->where('data->project_id', $project->id)
            ->where('created_at', '>=', $startOfMonth)
            ->exists();

        if ($alreadyNotified) {
            return false;
        }

        $recipients = $this->getEligibleAlertRecipients($project->department_id);
        if ($recipients->isEmpty()) {
            return false;
        }

        Notification::send(
            $recipients,
            new CapexBurnAlertNotification($project, $burnIndexPct, $allocatedHours, $consumedHours)
        );

        return true;
    }

    /**
     * Retrieve active Administrators and Department Managers eligible for CapEx alerts.
     *
     * @return Collection<int, User>
     */
    protected function getEligibleAlertRecipients(int $departmentId): Collection
    {
        $admins = User::query()
            ->where('role', UserRole::Admin)
            ->where('is_active', true)
            ->get();

        $managers = User::query()
            ->where('role', UserRole::Manager)
            ->where('department_id', $departmentId)
            ->where('is_active', true)
            ->get();

        return $admins->concat($managers)
            ->unique('id')
            ->values();
    }

    /**
     * Build the scoped, filtered query for CapEx labor attribution items.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<OvertimeItem>
     */
    public function buildAttributionQuery(array $filters, User $user): Builder
    {
        /** @var Builder<OvertimeItem> $query */
        $query = OvertimeItem::query()
            ->whereNotNull('overtime_items.capex_project_id')
            ->where('overtime_items.status', 'APPROVED')
            ->join('capex_projects', 'capex_projects.id', '=', 'overtime_items.capex_project_id')
            ->join('overtime_submissions', 'overtime_submissions.id', '=', 'overtime_items.overtime_submission_id')
            ->select('overtime_items.*')
            ->with([
                'capexProject:id,project_code,name,asset_code,department_id',
                'capexProject.department:id,code,name',
                'employee:id,npk,full_name',
                'overtimeSubmission:id,submission_code,operational_date,department_id,section_id',
                'reviewedBy:id,name',
            ]);

        // Scoping: Manager is strictly scoped to their department
        if ($user->isManager() && $user->department_id) {
            $query->where('capex_projects.department_id', (int) $user->department_id);
        } elseif ($user->isAdmin() && ! empty($filters['department_id'])) {
            $query->where('capex_projects.department_id', (int) $filters['department_id']);
        }

        // Filter by specific CapEx project
        if (! empty($filters['project_id']) || ! empty($filters['capex_project_id'])) {
            $projectId = (int) ($filters['project_id'] ?? $filters['capex_project_id']);
            $query->where('overtime_items.capex_project_id', $projectId);
        }

        // Date range filtering on operational_date
        if (! empty($filters['date_from'])) {
            $query->whereDate('overtime_submissions.operational_date', '>=', (string) $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('overtime_submissions.operational_date', '<=', (string) $filters['date_to']);
        }

        // Search filter: NPK, full name, submission code, project code, name, asset code
        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(function (Builder $q) use ($search) {
                $q->whereHas('employee', function (Builder $eq) use ($search) {
                    $eq->where('npk', 'like', "%{$search}%")
                        ->orWhere('full_name', 'like', "%{$search}%");
                })
                    ->orWhere('overtime_items.npk_snapshot', 'like', "%{$search}%")
                    ->orWhere('overtime_submissions.submission_code', 'like', "%{$search}%")
                    ->orWhere('capex_projects.project_code', 'like', "%{$search}%")
                    ->orWhere('capex_projects.name', 'like', "%{$search}%")
                    ->orWhere('capex_projects.asset_code', 'like', "%{$search}%");
            });
        }

        $query->orderBy('capex_projects.project_code', 'asc')
            ->orderBy('overtime_submissions.operational_date', 'desc')
            ->orderBy('overtime_items.id', 'asc');

        return $query;
    }

    /**
     * Compute grouped CapEx labor attribution schedule with project subtotals and grand totals.
     *
     * @param  array<string, mixed>  $filters
     * @return array{
     *     groups: list<array{
     *         project_id: int,
     *         project_code: string,
     *         project_name: string,
     *         asset_code: string|null,
     *         department_id: int|null,
     *         department_name: string,
     *         items: list<array{
     *             id: int,
     *             date: string,
     *             employee_id: int,
     *             npk: string,
     *             employee_name: string,
     *             hours_project: float,
     *             hourly_rate_snapshot: float,
     *             total_cost_snapshot: float,
     *             submission_code: string,
     *             reviewed_at: string|null,
     *             reviewed_by_name: string
     *         }>,
     *         subtotal_hours: float,
     *         subtotal_cost: float,
     *         item_count: int
     *     }>,
     *     grand_total_hours: float,
     *     grand_total_cost: float,
     *     total_items: int,
     *     total_projects: int
     * }
     */
    public function getLaborAttributionReport(array $filters, User $user): array
    {
        $items = $this->buildAttributionQuery($filters, $user)->get();

        $groups = [];
        $grandTotalHours = 0.0;
        $grandTotalCost = 0.0;
        $totalItems = $items->count();

        /** @var Collection<int, Collection<int, OvertimeItem>> $grouped */
        $grouped = $items->groupBy('capex_project_id');

        foreach ($grouped as $projectId => $projectItems) {
            /** @var OvertimeItem $firstItem */
            $firstItem = $projectItems->first();
            $project = $firstItem->capexProject;

            $subtotalHours = (float) $projectItems->sum('hours_project');
            $subtotalCost = (float) $projectItems->sum('total_cost_snapshot');

            $grandTotalHours += $subtotalHours;
            $grandTotalCost += $subtotalCost;

            $mappedItems = $projectItems->map(function (OvertimeItem $item) {
                return [
                    'id' => (int) $item->id,
                    'date' => $item->overtimeSubmission?->operational_date?->format('Y-m-d') ?? '',
                    'employee_id' => (int) $item->employee_id,
                    'npk' => $item->npk_snapshot ?: ($item->employee?->npk ?? ''),
                    'employee_name' => $item->employee?->full_name ?? '',
                    'hours_project' => (float) $item->hours_project,
                    'hourly_rate_snapshot' => (float) $item->hourly_rate_snapshot,
                    'total_cost_snapshot' => (float) $item->total_cost_snapshot,
                    'submission_code' => $item->overtimeSubmission?->submission_code ?? '',
                    'reviewed_at' => $item->reviewed_at
                        ? Carbon::parse($item->reviewed_at)->setTimezone('Asia/Jakarta')->format('Y-m-d H:i')
                        : null,
                    'reviewed_by_name' => $item->reviewedBy?->name ?? 'System',
                ];
            })->values()->all();

            $groups[] = [
                'project_id' => (int) $projectId,
                'project_code' => $project?->project_code ?? '',
                'project_name' => $project?->name ?? '',
                'asset_code' => $project?->asset_code,
                'department_id' => $project?->department_id,
                'department_name' => $project?->department?->name ?? '',
                'items' => $mappedItems,
                'subtotal_hours' => round($subtotalHours, 2),
                'subtotal_cost' => round($subtotalCost, 2),
                'item_count' => $projectItems->count(),
            ];
        }

        return [
            'groups' => $groups,
            'grand_total_hours' => round($grandTotalHours, 2),
            'grand_total_cost' => round($grandTotalCost, 2),
            'total_items' => $totalItems,
            'total_projects' => count($groups),
        ];
    }
}
