<?php

namespace App\Services;

use App\Models\CapexProject;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class CapexProjectService
{
    /**
     * Map of valid lifecycle status transitions.
     *
     * @var array<string, list<string>>
     */
    public const VALID_STATUS_TRANSITIONS = [
        'PLANNING' => ['ACTIVE'],
        'ACTIVE' => ['ON_HOLD', 'COMPLETED'],
        'ON_HOLD' => ['ACTIVE', 'COMPLETED'],
        'COMPLETED' => ['CLOSED'],
        'CLOSED' => [],
    ];

    /**
     * Get paginated Capex projects with aggregated metrics and portfolio stats.
     *
     * @param  array<string, mixed>  $filters
     * @return array{
     *     projects: LengthAwarePaginator<CapexProject>,
     *     stats: array{
     *         total_active: int,
     *         total_allocated_hours: float,
     *         total_consumed_hours: float,
     *         total_capitalized_cost: float,
     *         at_risk_count: int
     *     }
     * }
     */
    public function list(array $filters, User $user, int $perPage = 20): array
    {
        $baseQuery = CapexProject::query();

        // Scope by department if Manager
        if ($user->isManager() && $user->department_id) {
            $baseQuery->where('department_id', $user->department_id);
        } elseif ($user->isAdmin() && ! empty($filters['department_id'])) {
            $baseQuery->where('department_id', (int) $filters['department_id']);
        }

        // Compute portfolio summary metrics across the scoped dataset (before pagination)
        $stats = $this->calculatePortfolioStats(clone $baseQuery);

        // Apply filters to list query
        $query = (clone $baseQuery)
            ->with('department:id,code,name')
            ->withSum(['overtimeItems as consumed_hours' => fn ($q) => $q->where('status', 'APPROVED')], 'hours_project')
            ->withSum(['overtimeItems as consumed_cost' => fn ($q) => $q->where('status', 'APPROVED')], 'total_cost_snapshot');

        if (! empty($filters['status']) && strtoupper((string) $filters['status']) !== 'ALL') {
            $query->where('status', strtoupper((string) $filters['status']));
        }

        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(function (Builder $q) use ($search) {
                $q->where('project_code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('asset_code', 'like', "%{$search}%");
            });
        }

        $projects = $query->orderBy('created_at', 'desc')->paginate($perPage)->withQueryString();

        // Compute runtime derived attributes for each project
        $now = Carbon::now('Asia/Jakarta')->startOfDay();
        $projects->getCollection()->transform(function (CapexProject $project) use ($now) {
            $allocatedHours = (float) $project->allocated_labor_hours;
            $consumedHours = (float) ($project->consumed_hours ?? 0);
            $burnIndexPct = $allocatedHours > 0 ? round(($consumedHours / $allocatedHours) * 100, 1) : 0.0;
            $physicalPct = (float) $project->physical_progress_pct;
            $milestoneBurnRatio = $physicalPct > 0 ? round($burnIndexPct / $physicalPct, 2) : 0.0;

            $targetDate = $project->target_end_date ? Carbon::parse($project->target_end_date)->startOfDay() : null;
            $daysRemaining = $targetDate ? (int) $now->diffInDays($targetDate, false) : 0;
            $isOverdue = $targetDate ? $now->greaterThan($targetDate) && ! in_array($project->status, ['COMPLETED', 'CLOSED'], true) : false;

            $isAtRisk = in_array($project->status, ['ACTIVE', 'ON_HOLD'], true)
                && ($milestoneBurnRatio > 1.20 || $burnIndexPct > 90.0);

            $project->setAttribute('computed_burn_index_pct', $burnIndexPct);
            $project->setAttribute('computed_milestone_burn_ratio', $milestoneBurnRatio);
            $project->setAttribute('days_remaining', $daysRemaining);
            $project->setAttribute('is_overdue', $isOverdue);
            $project->setAttribute('is_at_risk', $isAtRisk);

            return $project;
        });

        return [
            'projects' => $projects,
            'stats' => $stats,
        ];
    }

    /**
     * Calculate portfolio summary metrics.
     *
     * @param  Builder<CapexProject>  $query
     * @return array{
     *     total_active: int,
     *     total_allocated_hours: float,
     *     total_consumed_hours: float,
     *     total_capitalized_cost: float,
     *     at_risk_count: int
     * }
     */
    protected function calculatePortfolioStats(Builder $query): array
    {
        $allProjects = $query
            ->withSum(['overtimeItems as consumed_hours' => fn ($q) => $q->where('status', 'APPROVED')], 'hours_project')
            ->withSum(['overtimeItems as consumed_cost' => fn ($q) => $q->where('status', 'APPROVED')], 'total_cost_snapshot')
            ->get();

        $totalActive = 0;
        $totalAllocatedHours = 0.0;
        $totalConsumedHours = 0.0;
        $totalCapitalizedCost = 0.0;
        $atRiskCount = 0;

        foreach ($allProjects as $project) {
            $allocated = (float) $project->allocated_labor_hours;
            $consumed = (float) ($project->consumed_hours ?? 0);
            $cost = (float) ($project->consumed_cost ?? 0);
            $physical = (float) $project->physical_progress_pct;

            if ($project->status === 'ACTIVE') {
                $totalActive++;
                $totalAllocatedHours += $allocated;
                $totalConsumedHours += $consumed;
            }

            $totalCapitalizedCost += $cost;

            $burnIndex = $allocated > 0 ? ($consumed / $allocated) * 100 : 0.0;
            $milestoneRatio = $physical > 0 ? ($burnIndex / $physical) : 0.0;

            if (in_array($project->status, ['ACTIVE', 'ON_HOLD'], true)
                && ($milestoneRatio > 1.20 || $burnIndex > 90.0)) {
                $atRiskCount++;
            }
        }

        return [
            'total_active' => $totalActive,
            'total_allocated_hours' => round($totalAllocatedHours, 1),
            'total_consumed_hours' => round($totalConsumedHours, 1),
            'total_capitalized_cost' => round($totalCapitalizedCost, 2),
            'at_risk_count' => $atRiskCount,
        ];
    }

    /**
     * Create a new CapEx project record.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $user): CapexProject
    {
        // Manager can only create for their own department
        if ($user->isManager() && $user->department_id) {
            $data['department_id'] = $user->department_id;
        }

        $projectCode = strtoupper(trim((string) $data['project_code']));

        return CapexProject::create([
            'project_code' => $projectCode,
            'asset_code' => ! empty($data['asset_code']) ? strtoupper(trim((string) $data['asset_code'])) : null,
            'name' => trim((string) $data['name']),
            'department_id' => (int) $data['department_id'],
            'allocated_labor_hours' => (float) $data['allocated_labor_hours'],
            'allocated_labor_budget_idr' => (float) $data['allocated_labor_budget_idr'],
            'physical_progress_pct' => (float) ($data['physical_progress_pct'] ?? 0.00),
            'status' => $data['status'] ?? 'PLANNING',
            'start_date' => $data['start_date'],
            'target_end_date' => $data['target_end_date'],
        ]);
    }

    /**
     * Update an existing CapEx project record.
     * Note: project_code is immutable after creation.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function update(CapexProject $project, array $data, User $user): CapexProject
    {
        $this->ensureDepartmentAccess($project, $user);

        if (array_key_exists('project_code', $data) && $data['project_code'] !== $project->project_code) {
            throw ValidationException::withMessages([
                'project_code' => [__('Kode proyek bersifat permanen dan tidak dapat diubah setelah dibuat demi menjaga jejak audit akuntansi.')],
            ]);
        }

        $departmentId = $project->department_id;
        if (! $user->isManager() && isset($data['department_id'])) {
            $departmentId = (int) $data['department_id'];
        }

        $updatePayload = [
            'name' => isset($data['name']) ? trim((string) $data['name']) : $project->name,
            'asset_code' => array_key_exists('asset_code', $data)
                ? (! empty($data['asset_code']) ? strtoupper(trim((string) $data['asset_code'])) : null)
                : $project->asset_code,
            'department_id' => $departmentId,
            'allocated_labor_hours' => isset($data['allocated_labor_hours'])
                ? (float) $data['allocated_labor_hours']
                : $project->allocated_labor_hours,
            'allocated_labor_budget_idr' => isset($data['allocated_labor_budget_idr'])
                ? (float) $data['allocated_labor_budget_idr']
                : $project->allocated_labor_budget_idr,
            'start_date' => $data['start_date'] ?? $project->start_date,
            'target_end_date' => $data['target_end_date'] ?? $project->target_end_date,
        ];

        if (isset($data['physical_progress_pct'])) {
            $updatePayload['physical_progress_pct'] = (float) $data['physical_progress_pct'];
        }

        $project->update($updatePayload);

        return $project->fresh(['department']);
    }

    /**
     * Transition project lifecycle status following strict state machine rules.
     *
     * @throws ValidationException
     */
    public function updateStatus(CapexProject $project, string $newStatus, ?string $notes, User $user): CapexProject
    {
        $this->ensureDepartmentAccess($project, $user);

        $currentStatus = $project->status;
        $newStatus = strtoupper(trim($newStatus));

        if ($currentStatus === $newStatus) {
            return $project;
        }

        $allowedTransitions = self::VALID_STATUS_TRANSITIONS[$currentStatus] ?? [];

        if (! in_array($newStatus, $allowedTransitions, true)) {
            throw ValidationException::withMessages([
                'status' => [__('Transisi status dari :from ke :to tidak diizinkan sesuai alur kerja proyek.', [
                    'from' => $currentStatus,
                    'to' => $newStatus,
                ])],
            ]);
        }

        $project->update([
            'status' => $newStatus,
        ]);

        return $project->fresh(['department']);
    }

    /**
     * Delete a CapEx project if no overtime items are attributed.
     *
     * @throws ValidationException
     */
    public function delete(CapexProject $project, User $user): void
    {
        $this->ensureDepartmentAccess($project, $user);

        if ($project->overtimeItems()->exists()) {
            throw ValidationException::withMessages([
                'project' => [__('Proyek CapEx tidak dapat dihapus karena sudah memiliki catatan jam lembur terkait.')],
            ]);
        }

        $project->delete();
    }

    /**
     * Get detailed metrics and attributes for project detail cockpit.
     *
     * @return array{
     *     project: CapexProject,
     *     metrics: array{
     *         allocated_hours: float,
     *         consumed_hours: float,
     *         remaining_hours: float,
     *         allocated_budget_idr: float,
     *         consumed_cost_idr: float,
     *         remaining_budget_idr: float,
     *         burn_index_pct: float,
     *         physical_progress_pct: float,
     *         milestone_burn_ratio: float,
     *         days_remaining: int,
     *         is_at_risk: bool,
     *         is_overdue: bool
     *     }
     * }
     */
    public function getProjectDetail(CapexProject $project, User $user): array
    {
        $this->ensureDepartmentAccess($project, $user);

        $project->load('department:id,code,name');
        $project->loadSum(['overtimeItems as consumed_hours' => fn ($q) => $q->where('status', 'APPROVED')], 'hours_project');
        $project->loadSum(['overtimeItems as consumed_cost' => fn ($q) => $q->where('status', 'APPROVED')], 'total_cost_snapshot');

        $allocatedHours = (float) $project->allocated_labor_hours;
        $consumedHours = (float) ($project->consumed_hours ?? 0);
        $allocatedBudget = (float) $project->allocated_labor_budget_idr;
        $consumedCost = (float) ($project->consumed_cost ?? 0);
        $physicalPct = (float) $project->physical_progress_pct;

        $burnIndexPct = $allocatedHours > 0 ? round(($consumedHours / $allocatedHours) * 100, 1) : 0.0;
        $milestoneBurnRatio = $physicalPct > 0 ? round($burnIndexPct / $physicalPct, 2) : 0.0;

        $now = Carbon::now('Asia/Jakarta')->startOfDay();
        $targetDate = $project->target_end_date ? Carbon::parse($project->target_end_date)->startOfDay() : null;
        $daysRemaining = $targetDate ? (int) $now->diffInDays($targetDate, false) : 0;
        $isOverdue = $targetDate ? $now->greaterThan($targetDate) && ! in_array($project->status, ['COMPLETED', 'CLOSED'], true) : false;

        $isAtRisk = in_array($project->status, ['ACTIVE', 'ON_HOLD'], true)
            && ($milestoneBurnRatio > 1.20 || $burnIndexPct > 90.0);

        return [
            'project' => $project,
            'metrics' => [
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
            ],
        ];
    }

    /**
     * Check that manager is authorized to access the given project's department.
     */
    protected function ensureDepartmentAccess(CapexProject $project, User $user): void
    {
        if ($user->isManager() && $user->department_id && (int) $project->department_id !== (int) $user->department_id) {
            abort(403, __('Anda tidak memiliki hak akses untuk proyek di departemen ini.'));
        }
    }
}
