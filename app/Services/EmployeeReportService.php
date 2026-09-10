<?php

namespace App\Services;

use App\Models\Department;
use App\Models\Employee;
use App\Models\OvertimeBudget;
use App\Models\OvertimeItem;
use App\Models\Section;
use App\Models\User;
use App\Services\Policy\OvertimePolicyEvaluator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeeReportService
{
    public function __construct(
        protected ?OvertimePolicyEvaluator $evaluator = null,
    ) {
        $this->evaluator ??= app(OvertimePolicyEvaluator::class);
    }

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
                ->havingRaw('SUM(overtime_items.total_hours) > CAST(? AS DECIMAL(10,2))', [$currentMonthHours])
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
     * Retrieve peer benchmarking and workload distribution comparison for an employee in a section.
     *
     * @return array{
     *     has_section: bool,
     *     section_id: int|null,
     *     section_name: string|null,
     *     section_code: string|null,
     *     total_section_employees: int,
     *     section_total_hours: float,
     *     section_average_hours: float,
     *     individual_hours: float,
     *     variance_hours: float,
     *     variance_status: string,
     *     is_anonymized: bool,
     *     distribution: array<int, array{
     *         employee_id: int|null,
     *         npk: string,
     *         name: string,
     *         job_position: string|null,
     *         hours: float,
     *         variance_hours: float,
     *         is_current_employee: bool,
     *         rank: int
     *     }>,
     *     top_5: array<int, array{
     *         employee_id: int|null,
     *         npk: string,
     *         name: string,
     *         job_position: string|null,
     *         hours: float,
     *         variance_hours: float,
     *         is_current_employee: bool,
     *         rank: int
     *     }>,
     *     bottom_5: array<int, array{
     *         employee_id: int|null,
     *         npk: string,
     *         name: string,
     *         job_position: string|null,
     *         hours: float,
     *         variance_hours: float,
     *         is_current_employee: bool,
     *         rank: int
     *     }>
     * }
     */
    public function getPeerComparison(
        int $employeeId,
        int $sectionId,
        int $year,
        int $month,
        bool $anonymize = false,
    ): array {
        if ($sectionId <= 0) {
            return [
                'has_section' => false,
                'section_id' => null,
                'section_name' => null,
                'section_code' => null,
                'total_section_employees' => 0,
                'section_total_hours' => 0.0,
                'section_average_hours' => 0.0,
                'individual_hours' => 0.0,
                'variance_hours' => 0.0,
                'variance_status' => 'equal',
                'is_anonymized' => $anonymize,
                'distribution' => [],
                'top_5' => [],
                'bottom_5' => [],
            ];
        }

        $section = Section::find($sectionId);

        $monthStartDate = Carbon::create($year, $month, 1, 0, 0, 0, 'Asia/Jakarta')->startOfMonth()->toDateString();
        $monthEndDate = Carbon::create($year, $month, 1, 23, 59, 59, 'Asia/Jakarta')->endOfMonth()->toDateString();

        $approvedTotals = OvertimeItem::query()
            ->join('overtime_submissions', 'overtime_items.overtime_submission_id', '=', 'overtime_submissions.id')
            ->join('employees', 'overtime_items.employee_id', '=', 'employees.id')
            ->where('employees.section_id', $sectionId)
            ->where('overtime_items.status', 'APPROVED')
            ->whereBetween('overtime_submissions.operational_date', [$monthStartDate, $monthEndDate])
            ->selectRaw('overtime_items.employee_id as emp_id, SUM(overtime_items.total_hours) as total_approved_hours')
            ->groupBy('overtime_items.employee_id')
            ->pluck('total_approved_hours', 'emp_id')
            ->all();

        $sectionEmployees = Employee::query()
            ->where('section_id', $sectionId)
            ->orderBy('full_name', 'asc')
            ->get(['id', 'npk', 'full_name', 'job_position', 'is_active']);

        if ($sectionEmployees->isEmpty()) {
            return [
                'has_section' => true,
                'section_id' => $sectionId,
                'section_name' => $section?->name ?? '-',
                'section_code' => $section?->code ?? '-',
                'total_section_employees' => 0,
                'section_total_hours' => 0.0,
                'section_average_hours' => 0.0,
                'individual_hours' => 0.0,
                'variance_hours' => 0.0,
                'variance_status' => 'equal',
                'is_anonymized' => $anonymize,
                'distribution' => [],
                'top_5' => [],
                'bottom_5' => [],
            ];
        }

        $individualHours = round((float) ($approvedTotals[$employeeId] ?? 0.0), 2);
        $sectionTotalHours = round(array_sum(array_map('floatval', $approvedTotals)), 2);
        $totalSectionEmployees = $sectionEmployees->count();
        $sectionAverageHours = $totalSectionEmployees > 0
            ? round($sectionTotalHours / $totalSectionEmployees, 2)
            : 0.0;

        // CALC-06: Individual Hours - Section Average Hours
        $varianceHours = round($individualHours - $sectionAverageHours, 2);
        $varianceStatus = 'equal';
        if ($varianceHours > 0) {
            $varianceStatus = 'above';
        } elseif ($varianceHours < 0) {
            $varianceStatus = 'below';
        }

        $rawList = $sectionEmployees->map(function (Employee $emp) use ($approvedTotals, $employeeId): array {
            $hours = round((float) ($approvedTotals[$emp->id] ?? 0.0), 2);

            return [
                'employee' => $emp,
                'hours' => $hours,
                'is_current' => ($emp->id === $employeeId),
            ];
        })->all();

        usort($rawList, function (array $a, array $b): int {
            if ($a['hours'] !== $b['hours']) {
                return $b['hours'] <=> $a['hours'];
            }

            return strcasecmp($a['employee']->full_name, $b['employee']->full_name);
        });

        $distribution = [];
        foreach ($rawList as $index => $item) {
            /** @var Employee $emp */
            $emp = $item['employee'];
            $hours = $item['hours'];
            $rank = $index + 1;
            $isCurrent = $item['is_current'];

            $distribution[] = [
                'employee_id' => ($anonymize && ! $isCurrent) ? null : $emp->id,
                'npk' => ($anonymize && ! $isCurrent) ? '••••' : $emp->npk,
                'name' => ($anonymize && ! $isCurrent) ? "Karyawan #{$rank}" : $emp->full_name,
                'job_position' => ($anonymize && ! $isCurrent) ? null : $emp->job_position,
                'hours' => $hours,
                'variance_hours' => round($hours - $sectionAverageHours, 2),
                'is_current_employee' => $isCurrent,
                'rank' => $rank,
            ];
        }

        $top5 = array_slice($distribution, 0, 5);
        $bottom5Raw = array_slice($distribution, -5);
        $bottom5 = array_reverse($bottom5Raw);

        return [
            'has_section' => true,
            'section_id' => $sectionId,
            'section_name' => $section?->name ?? '-',
            'section_code' => $section?->code ?? '-',
            'total_section_employees' => $totalSectionEmployees,
            'section_total_hours' => $sectionTotalHours,
            'section_average_hours' => $sectionAverageHours,
            'individual_hours' => $individualHours,
            'variance_hours' => $varianceHours,
            'variance_status' => $varianceStatus,
            'is_anonymized' => $anonymize,
            'distribution' => $distribution,
            'top_5' => $top5,
            'bottom_5' => $bottom5,
        ];
    }

    /**
     * Compute rolling 4-week welfare and safety metrics for an employee in a given period.
     *
     * @return array<string, mixed>
     */
    public function getWelfareStatus(int $employeeId, ?int $year = null, ?int $month = null): array
    {
        $now = Carbon::now('Asia/Jakarta');
        $referenceDate = null;

        if ($year !== null && $month !== null) {
            $isCurrentMonth = ($year === (int) $now->format('Y') && $month === (int) $now->format('n'));
            if (! $isCurrentMonth) {
                $referenceDate = Carbon::create($year, $month, 1, 23, 59, 59, 'Asia/Jakarta')->endOfMonth()->toDateString();
            }
        }

        return $this->evaluator->getEmployeeWelfareStatus($employeeId, $referenceDate)->toArray();
    }

    /**
     * Build the query for an employee's overtime items with filters applied.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<OvertimeItem>
     */
    public function buildTimesheetQuery(int $employeeId, array $filters = []): Builder
    {
        $query = OvertimeItem::query()
            ->join('overtime_submissions', 'overtime_items.overtime_submission_id', '=', 'overtime_submissions.id')
            ->where('overtime_items.employee_id', $employeeId)
            ->select('overtime_items.*');

        // 1. Status Filter
        if (! empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('overtime_items.status', strtoupper((string) $filters['status']));
        }

        // 2. Category Filter
        if (! empty($filters['category']) && $filters['category'] !== 'all') {
            $cat = strtolower((string) $filters['category']);
            match ($cat) {
                'production' => $query->where('overtime_items.hours_production', '>', 0),
                'tpm' => $query->where('overtime_items.hours_tpm', '>', 0),
                'project', 'capex' => $query->where('overtime_items.hours_project', '>', 0),
                'others' => $query->where('overtime_items.hours_others', '>', 0),
                default => null,
            };
        }

        // 3. Date Range Filter
        if (! empty($filters['date_from'])) {
            $query->whereDate('overtime_submissions.operational_date', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('overtime_submissions.operational_date', '<=', $filters['date_to']);
        }

        // If no explicit date range, check fiscal period or all_time
        if (empty($filters['date_from']) && empty($filters['date_to'])) {
            if (empty($filters['all_time']) && ! empty($filters['fiscal_year']) && ! empty($filters['fiscal_month'])) {
                $y = (int) $filters['fiscal_year'];
                $m = (int) $filters['fiscal_month'];
                $monthStart = Carbon::create($y, $m, 1, 0, 0, 0, 'Asia/Jakarta')->startOfMonth()->toDateString();
                $monthEnd = Carbon::create($y, $m, 1, 23, 59, 59, 'Asia/Jakarta')->endOfMonth()->toDateString();
                $query->whereBetween('overtime_submissions.operational_date', [$monthStart, $monthEnd]);
            }
        }

        // 4. Search Filter (Task description, RCA notes, RCA category, submission code)
        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);
            $query->where(function (Builder $b) use ($escaped): void {
                $b->where('overtime_items.task_description', 'LIKE', '%'.$escaped.'%')
                    ->orWhere('overtime_items.rca_notes', 'LIKE', '%'.$escaped.'%')
                    ->orWhere('overtime_items.rca_category', 'LIKE', '%'.$escaped.'%')
                    ->orWhere('overtime_submissions.submission_code', 'LIKE', '%'.$escaped.'%');
            });
        }

        // 5. Sorting
        $sortDir = strtolower((string) ($filters['sort_dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
        $sortBy = strtolower((string) ($filters['sort_by'] ?? 'operational_date'));

        if ($sortBy === 'total_hours') {
            $query->orderBy('overtime_items.total_hours', $sortDir)->orderBy('overtime_items.id', $sortDir);
        } elseif ($sortBy === 'status') {
            $query->orderBy('overtime_items.status', $sortDir)->orderBy('overtime_submissions.operational_date', 'desc');
        } else {
            $query->orderBy('overtime_submissions.operational_date', $sortDir)->orderBy('overtime_items.id', $sortDir);
        }

        return $query;
    }

    /**
     * Retrieve paginated timesheet ledger for an employee with filters.
     *
     * @param  array<string, mixed>  $filters
     * @return array{
     *     data: array<int, array<string, mixed>>,
     *     pagination: array{
     *         current_page: int,
     *         last_page: int,
     *         per_page: int,
     *         total: int,
     *         from: int|null,
     *         to: int|null
     *     },
     *     summary: array{
     *         total_items: int,
     *         total_hours: float,
     *         approved_hours: float,
     *         pending_hours: float,
     *         rejected_hours: float,
     *         total_cost: float
     *     }
     * }
     */
    public function getTimesheet(int $employeeId, array $filters = [], int $perPage = 25): array
    {
        $query = $this->buildTimesheetQuery($employeeId, $filters);

        // Compute summary aggregates across filtered dataset
        $summaryRow = (clone $query)->selectRaw("
            COUNT(overtime_items.id) as total_count,
            COALESCE(SUM(overtime_items.total_hours), 0) as total_hours,
            COALESCE(SUM(CASE WHEN overtime_items.status = 'APPROVED' THEN overtime_items.total_hours ELSE 0 END), 0) as approved_hours,
            COALESCE(SUM(CASE WHEN overtime_items.status = 'PENDING' THEN overtime_items.total_hours ELSE 0 END), 0) as pending_hours,
            COALESCE(SUM(CASE WHEN overtime_items.status = 'REJECTED' THEN overtime_items.total_hours ELSE 0 END), 0) as rejected_hours,
            COALESCE(SUM(overtime_items.total_cost_snapshot), 0) as total_cost
        ")->first();

        $paginated = $query
            ->with([
                'overtimeSubmission:id,submission_code,operational_date,day_type',
                'capexProject:id,project_code,name',
            ])
            ->paginate($perPage, ['overtime_items.*']);

        $daysMap = [
            1 => 'Senin',
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Kamis',
            5 => 'Jumat',
            6 => 'Sabtu',
            7 => 'Minggu',
        ];

        $monthsMap = [
            1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun',
            7 => 'Jul', 8 => 'Agu', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des',
        ];

        $items = $paginated->getCollection()->map(function (OvertimeItem $item) use ($daysMap, $monthsMap): array {
            $opDate = $item->overtimeSubmission ? Carbon::parse($item->overtimeSubmission->operational_date) : null;
            $dayName = $opDate ? ($daysMap[$opDate->dayOfWeekIso] ?? '-') : '-';
            $formattedDate = $opDate ? sprintf('%02d %s %d', $opDate->day, $monthsMap[$opDate->month] ?? $opDate->format('M'), $opDate->year) : '-';

            return [
                'id' => $item->id,
                'submission_id' => $item->overtime_submission_id,
                'submission_code' => $item->overtimeSubmission?->submission_code ?? '-',
                'operational_date' => $opDate?->toDateString(),
                'formatted_date' => $formattedDate,
                'day_name' => $dayName,
                'day_type' => $item->overtimeSubmission?->day_type ?? 'HKN',
                'hours_production' => (float) $item->hours_production,
                'hours_tpm' => (float) $item->hours_tpm,
                'hours_project' => (float) $item->hours_project,
                'hours_others' => (float) $item->hours_others,
                'total_hours' => (float) $item->total_hours,
                'hourly_rate' => (float) $item->hourly_rate_snapshot,
                'total_cost' => (float) $item->total_cost_snapshot,
                'status' => $item->status,
                'task_description' => $item->task_description,
                'rca_category' => $item->rca_category,
                'rca_notes' => $item->rca_notes,
                'rejection_reason' => $item->rejection_reason,
                'capex_project' => $item->capexProject ? [
                    'id' => $item->capexProject->id,
                    'project_code' => $item->capexProject->project_code,
                    'name' => $item->capexProject->name,
                ] : null,
                'is_capex' => (float) $item->hours_project > 0 || $item->capex_project_id !== null,
            ];
        })->values()->all();

        return [
            'data' => $items,
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'from' => $paginated->firstItem(),
                'to' => $paginated->lastItem(),
            ],
            'summary' => [
                'total_items' => (int) ($summaryRow->total_count ?? 0),
                'total_hours' => round((float) ($summaryRow->total_hours ?? 0.0), 2),
                'approved_hours' => round((float) ($summaryRow->approved_hours ?? 0.0), 2),
                'pending_hours' => round((float) ($summaryRow->pending_hours ?? 0.0), 2),
                'rejected_hours' => round((float) ($summaryRow->rejected_hours ?? 0.0), 2),
                'total_cost' => round((float) ($summaryRow->total_cost ?? 0.0), 2),
            ],
        ];
    }

    /**
     * Export filtered timesheet to a streamed CSV file.
     *
     * @param  array<string, mixed>  $filters
     */
    public function exportTimesheetCsv(User $user, string $npk, array $filters = []): StreamedResponse
    {
        $employee = Employee::query()
            ->where('npk', $npk)
            ->firstOrFail();

        $this->authorizeDossierAccess($user, $employee);

        $query = $this->buildTimesheetQuery($employee->id, $filters)
            ->with([
                'overtimeSubmission:id,submission_code,operational_date,day_type',
                'capexProject:id,project_code,name',
            ]);

        $now = Carbon::now('Asia/Jakarta');
        $dateStamp = $now->format('Ymd_His');
        $filename = "Timesheet-ISZ-{$employee->npk}-{$dateStamp}.csv";

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $headings = [
            'Kode Pengajuan',
            'Tanggal (WIB)',
            'Hari',
            'Jenis Hari',
            'Produksi (Jam)',
            'TPM (Jam)',
            'CapEx (Jam)',
            'Lainnya (Jam)',
            'Total Jam',
            'Tarif Snapshot (Rp)',
            'Total Biaya (Rp)',
            'Kode CapEx',
            'Nama Proyek CapEx',
            'Status',
            'Kategori RCA',
            'Catatan Tugas',
            'Catatan RCA',
            'Alasan Penolakan',
        ];

        return response()->streamDownload(function () use ($query, $headings): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            // Write UTF-8 BOM so Microsoft Excel correctly displays Indonesian characters and symbols
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            // Write Header
            fputcsv($handle, $headings);

            $daysMap = [
                1 => 'Senin',
                2 => 'Selasa',
                3 => 'Rabu',
                4 => 'Kamis',
                5 => 'Jumat',
                6 => 'Sabtu',
                7 => 'Minggu',
            ];

            // Stream items row-by-row with zero memory overhead
            foreach ($query->cursor() as $item) {
                /** @var OvertimeItem $item */
                $opDate = $item->overtimeSubmission ? Carbon::parse($item->overtimeSubmission->operational_date) : null;
                $dayName = $opDate ? ($daysMap[$opDate->dayOfWeekIso] ?? '-') : '-';

                $row = [
                    $item->overtimeSubmission?->submission_code ?? '-',
                    $opDate ? $opDate->format('Y-m-d') : '-',
                    $dayName,
                    $item->overtimeSubmission?->day_type ?? 'HKN',
                    number_format((float) $item->hours_production, 2, '.', ''),
                    number_format((float) $item->hours_tpm, 2, '.', ''),
                    number_format((float) $item->hours_project, 2, '.', ''),
                    number_format((float) $item->hours_others, 2, '.', ''),
                    number_format((float) $item->total_hours, 2, '.', ''),
                    number_format((float) $item->hourly_rate_snapshot, 2, '.', ''),
                    number_format((float) $item->total_cost_snapshot, 2, '.', ''),
                    $item->capexProject?->project_code ?? '-',
                    $item->capexProject?->name ?? '-',
                    $item->status,
                    $item->rca_category ?? '-',
                    $item->task_description ?? '-',
                    $item->rca_notes ?? '-',
                    $item->rejection_reason ?? '-',
                ];

                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename, $headers);
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
