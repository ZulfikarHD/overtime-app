<?php

namespace App\Services;

use App\Models\Department;
use App\Models\Employee;
use App\Models\OvertimeBudget;
use App\Models\OvertimeItem;
use App\Models\Section;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class EmployeeReportService
{
    /**
     * Search employees by NPK or full name scoped to the user's role and hierarchy.
     *
     * @return array<int, array{
     *     id: int,
     *     npk: string,
     *     name: string,
     *     job_position: string,
     *     department_name: string,
     *     section_name: string,
     *     is_active: bool
     * }>
     */
    public function search(User $user, string $query): array
    {
        $trimmed = trim($query);

        if (mb_strlen($trimmed) < 3) {
            return [];
        }

        if ($user->isUser()) {
            abort(403, 'Akses ditolak.');
        }

        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $trimmed);

        $queryBuilder = Employee::query()
            ->with([
                'department:id,name,code',
                'section:id,name,code',
            ])
            ->where(function (Builder $builder) use ($escaped): void {
                $builder->where('npk', 'LIKE', '%'.$escaped.'%')
                    ->orWhere('full_name', 'LIKE', '%'.$escaped.'%');
            });

        $this->applyRoleScope($queryBuilder, $user);

        return $queryBuilder
            ->orderBy('full_name', 'asc')
            ->limit(10)
            ->get()
            ->map(fn (Employee $employee) => [
                'id' => $employee->id,
                'npk' => $employee->npk,
                'name' => $employee->full_name,
                'job_position' => $employee->job_position,
                'department_name' => $employee->department?->name ?? '-',
                'section_name' => $employee->section?->name ?? '-',
                'is_active' => (bool) $employee->is_active,
            ])
            ->values()
            ->all();
    }

    /**
     * Retrieve the scoped roster of employees for the dossier hub index view.
     *
     * @param  array{department_id?: int|string|null, section_id?: int|string|null, search?: string|null}  $filters
     * @return array<int, array{
     *     id: int,
     *     npk: string,
     *     full_name: string,
     *     job_position: string,
     *     is_active: bool,
     *     department: array{id: int, code: string, name: string}|null,
     *     section: array{id: int, code: string, name: string}|null
     * }>
     */
    public function getRoster(User $user, array $filters = []): array
    {
        if ($user->isUser()) {
            abort(403, 'Akses ditolak.');
        }

        $queryBuilder = Employee::query()
            ->with([
                'department:id,name,code',
                'section:id,name,code',
            ]);

        $this->applyRoleScope($queryBuilder, $user);

        if (! empty($filters['department_id']) && $user->isAdmin()) {
            $queryBuilder->where('department_id', (int) $filters['department_id']);
        }

        if (! empty($filters['section_id']) && ($user->isAdmin() || $user->isManager())) {
            $queryBuilder->where('section_id', (int) $filters['section_id']);
        }

        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);
            $queryBuilder->where(function (Builder $builder) use ($escaped): void {
                $builder->where('npk', 'LIKE', '%'.$escaped.'%')
                    ->orWhere('full_name', 'LIKE', '%'.$escaped.'%');
            });
        }

        return $queryBuilder
            ->orderBy('full_name', 'asc')
            ->limit(100)
            ->get()
            ->map(fn (Employee $employee) => [
                'id' => $employee->id,
                'npk' => $employee->npk,
                'full_name' => $employee->full_name,
                'job_position' => $employee->job_position,
                'is_active' => (bool) $employee->is_active,
                'department' => $employee->department ? [
                    'id' => $employee->department->id,
                    'code' => $employee->department->code,
                    'name' => $employee->department->name,
                ] : null,
                'section' => $employee->section ? [
                    'id' => $employee->section->id,
                    'code' => $employee->section->code,
                    'name' => $employee->section->name,
                ] : null,
            ])
            ->values()
            ->all();
    }

    /**
     * Authorize and retrieve employee dossier data.
     *
     * @return array{
     *     id: int,
     *     npk: string,
     *     full_name: string,
     *     job_position: string,
     *     hourly_rate: float,
     *     is_active: bool,
     *     department: array{id: int, code: string, name: string}|null,
     *     section: array{id: int, code: string, name: string}|null
     * }
     */
    public function getEmployeeDossier(User $user, string $npk): array
    {
        $employee = Employee::query()
            ->with([
                'department:id,code,name,default_hourly_rate',
                'section:id,department_id,code,name',
            ])
            ->where('npk', $npk)
            ->firstOrFail();

        $this->authorizeDossierAccess($user, $employee);

        return [
            'id' => $employee->id,
            'npk' => $employee->npk,
            'full_name' => $employee->full_name,
            'job_position' => $employee->job_position,
            'hourly_rate' => (float) $employee->effective_hourly_rate,
            'is_active' => (bool) $employee->is_active,
            'department' => $employee->department ? [
                'id' => $employee->department->id,
                'code' => $employee->department->code,
                'name' => $employee->department->name,
            ] : null,
            'section' => $employee->section ? [
                'id' => $employee->section->id,
                'code' => $employee->section->code,
                'name' => $employee->section->name,
            ] : null,
        ];
    }

    /**
     * Check if a user is authorized to view a specific employee's dossier.
     */
    public function authorizeDossierAccess(User $user, Employee $employee): void
    {
        if ($user->isAdmin()) {
            return;
        }

        if ($user->isUser()) {
            if ($user->npk !== $employee->npk) {
                abort(403, 'Akses ditolak.');
            }

            return;
        }

        if ($user->isManager()) {
            if (! $user->department_id || (int) $employee->department_id !== (int) $user->department_id) {
                abort(403, 'Akses ditolak. Karyawan berada di luar departemen Anda.');
            }

            return;
        }

        if ($user->isTeamLeader()) {
            if (! $user->section_id || (int) $employee->section_id !== (int) $user->section_id) {
                abort(403, 'Akses ditolak. Karyawan berada di luar seksi Anda.');
            }

            return;
        }

        abort(403, 'Akses ditolak.');
    }

    /**
     * Compute personal overtime summary metrics for an employee in a given period.
     *
     * @return array{
     *     current_month_hours: float,
     *     ytd_hours: float,
     *     burn_index: float|null,
     *     individual_planned_hours: float|null,
     *     dept_rank: array{rank: int, total_employees: int},
     *     category_breakdown: array{
     *         production: float,
     *         tpm: float,
     *         project: float,
     *         others: float,
     *         total: float,
     *         production_pct: float,
     *         tpm_pct: float,
     *         project_pct: float,
     *         others_pct: float
     *     },
     *     day_type_breakdown: array{
     *         hkn_hours: float,
     *         hlr_hours: float,
     *         total: float,
     *         hkn_pct: float,
     *         hlr_pct: float
     *     },
     *     total_cost_idr: float
     * }
     */
    public function getSummary(int $employeeId, int $year, int $month): array
    {
        $employee = Employee::find($employeeId);

        $monthStartDate = Carbon::create($year, $month, 1, 0, 0, 0, 'Asia/Jakarta')->startOfMonth()->toDateString();
        $monthEndDate = Carbon::create($year, $month, 1, 23, 59, 59, 'Asia/Jakarta')->endOfMonth()->toDateString();

        $ytdStartDate = Carbon::create($year, 1, 1, 0, 0, 0, 'Asia/Jakarta')->startOfYear()->toDateString();
        $ytdEndDate = $monthEndDate;

        // 1. Fetch monthly approved metrics
        $monthlyMetrics = OvertimeItem::query()
            ->join('overtime_submissions', 'overtime_items.overtime_submission_id', '=', 'overtime_submissions.id')
            ->where('overtime_items.employee_id', $employeeId)
            ->where('overtime_items.status', 'APPROVED')
            ->whereBetween('overtime_submissions.operational_date', [$monthStartDate, $monthEndDate])
            ->selectRaw("
                COALESCE(SUM(overtime_items.hours_production), 0) as total_prod,
                COALESCE(SUM(overtime_items.hours_tpm), 0) as total_tpm,
                COALESCE(SUM(overtime_items.hours_project), 0) as total_project,
                COALESCE(SUM(overtime_items.hours_others), 0) as total_others,
                COALESCE(SUM(overtime_items.total_hours), 0) as cumulative_hours,
                COALESCE(SUM(overtime_items.total_cost_snapshot), 0) as cumulative_cost_idr,
                COALESCE(SUM(CASE WHEN overtime_submissions.day_type = 'HKN' THEN overtime_items.total_hours ELSE 0 END), 0) as hkn_hours,
                COALESCE(SUM(CASE WHEN overtime_submissions.day_type = 'HLR' THEN overtime_items.total_hours ELSE 0 END), 0) as hlr_hours
            ")
            ->first();

        $currentMonthHours = round((float) ($monthlyMetrics->cumulative_hours ?? 0.0), 2);
        $totalCostIdr = round((float) ($monthlyMetrics->cumulative_cost_idr ?? 0.0), 2);

        $prodHours = round((float) ($monthlyMetrics->total_prod ?? 0.0), 2);
        $tpmHours = round((float) ($monthlyMetrics->total_tpm ?? 0.0), 2);
        $projHours = round((float) ($monthlyMetrics->total_project ?? 0.0), 2);
        $othersHours = round((float) ($monthlyMetrics->total_others ?? 0.0), 2);

        $categoryBreakdown = [
            'production' => $prodHours,
            'tpm' => $tpmHours,
            'project' => $projHours,
            'others' => $othersHours,
            'total' => $currentMonthHours,
            'production_pct' => $currentMonthHours > 0 ? round(($prodHours / $currentMonthHours) * 100, 1) : 0.0,
            'tpm_pct' => $currentMonthHours > 0 ? round(($tpmHours / $currentMonthHours) * 100, 1) : 0.0,
            'project_pct' => $currentMonthHours > 0 ? round(($projHours / $currentMonthHours) * 100, 1) : 0.0,
            'others_pct' => $currentMonthHours > 0 ? round(($othersHours / $currentMonthHours) * 100, 1) : 0.0,
        ];

        $hknHours = round((float) ($monthlyMetrics->hkn_hours ?? 0.0), 2);
        $hlrHours = round((float) ($monthlyMetrics->hlr_hours ?? 0.0), 2);
        $dayTypeTotal = round($hknHours + $hlrHours, 2);

        $dayTypeBreakdown = [
            'hkn_hours' => $hknHours,
            'hlr_hours' => $hlrHours,
            'total' => $dayTypeTotal,
            'hkn_pct' => $dayTypeTotal > 0 ? round(($hknHours / $dayTypeTotal) * 100, 1) : 0.0,
            'hlr_pct' => $dayTypeTotal > 0 ? round(($hlrHours / $dayTypeTotal) * 100, 1) : 0.0,
        ];

        // 2. Fetch YTD approved hours
        $ytdHours = (float) OvertimeItem::query()
            ->join('overtime_submissions', 'overtime_items.overtime_submission_id', '=', 'overtime_submissions.id')
            ->where('overtime_items.employee_id', $employeeId)
            ->where('overtime_items.status', 'APPROVED')
            ->whereBetween('overtime_submissions.operational_date', [$ytdStartDate, $ytdEndDate])
            ->sum('overtime_items.total_hours');
        $ytdHours = round($ytdHours, 2);

        // 3. Individual Burn Index %
        $burnIndex = null;
        $individualPlannedHours = null;

        if ($employee && $employee->section_id) {
            $budget = OvertimeBudget::query()
                ->where('section_id', $employee->section_id)
                ->where('fiscal_year', $year)
                ->where('fiscal_month', $month)
                ->first();

            if ($budget && (float) $budget->planned_hours > 0) {
                $activeCount = Employee::query()
                    ->where('section_id', $employee->section_id)
                    ->where('is_active', true)
                    ->count();

                if ($activeCount > 0) {
                    $individualPlannedHours = round((float) $budget->planned_hours / $activeCount, 2);
                    $burnIndex = $individualPlannedHours > 0
                        ? round(($currentMonthHours / $individualPlannedHours) * 100, 1)
                        : null;
                }
            }
        }

        // 4. Department / Section Ranking
        $rank = 1;
        $totalSectionEmployees = 1;

        if ($employee && $employee->section_id) {
            $totalSectionEmployees = Employee::query()
                ->where('section_id', $employee->section_id)
                ->count();

            $greaterHoursCount = OvertimeItem::query()
                ->join('overtime_submissions', 'overtime_items.overtime_submission_id', '=', 'overtime_submissions.id')
                ->join('employees', 'overtime_items.employee_id', '=', 'employees.id')
                ->where('employees.section_id', $employee->section_id)
                ->where('overtime_items.status', 'APPROVED')
                ->whereBetween('overtime_submissions.operational_date', [$monthStartDate, $monthEndDate])
                ->selectRaw('overtime_items.employee_id, SUM(overtime_items.total_hours) as total_approved_hours')
                ->groupBy('overtime_items.employee_id')
                ->havingRaw('SUM(overtime_items.total_hours) > CAST(? AS REAL)', [$currentMonthHours])
                ->get()
                ->count();

            $rank = $greaterHoursCount + 1;
        }

        return [
            'current_month_hours' => $currentMonthHours,
            'ytd_hours' => $ytdHours,
            'burn_index' => $burnIndex,
            'individual_planned_hours' => $individualPlannedHours,
            'dept_rank' => [
                'rank' => $rank,
                'total_employees' => max(1, $totalSectionEmployees),
            ],
            'category_breakdown' => $categoryBreakdown,
            'day_type_breakdown' => $dayTypeBreakdown,
            'total_cost_idr' => $totalCostIdr,
        ];
    }

    /**
     * Apply role-based scoping to the employee query builder.
     */
    protected function applyRoleScope(Builder $builder, User $user): void
    {
        if ($user->isAdmin()) {
            return;
        }

        if ($user->isManager()) {
            if ($user->department_id) {
                $builder->where('department_id', $user->department_id);
            } else {
                $builder->whereRaw('1 = 0');
            }

            return;
        }

        if ($user->isTeamLeader()) {
            if ($user->section_id) {
                $builder->where('section_id', $user->section_id);
            } else {
                $builder->whereRaw('1 = 0');
            }

            return;
        }

        $builder->whereRaw('1 = 0');
    }
}
