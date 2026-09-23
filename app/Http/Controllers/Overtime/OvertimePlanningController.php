<?php

namespace App\Http\Controllers\Overtime;

use App\Http\Controllers\Controller;
use App\Http\Requests\Overtime\StorePlanningRequest;
use App\Http\Requests\Overtime\UpdatePlanningRequest;
use App\Models\Department;
use App\Models\Employee;
use App\Models\OperationalCalendar;
use App\Models\OvertimeItem;
use App\Models\OvertimePlan;
use App\Models\OvertimePlanItem;
use App\Models\Section;
use App\Models\User;
use App\Services\Analytics\DashboardKpiService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class OvertimePlanningController extends Controller
{
    /**
     * List planning documents for the current user scope.
     */
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $query = OvertimePlan::query()
            ->with(['section:id,name,code', 'department:id,name,code', 'submittedBy:id,name'])
            ->withCount('items')
            ->latest('fiscal_year')
            ->orderByDesc('fiscal_month');

        if ($user->isTeamLeader() && $user->section_id) {
            $query->where('section_id', $user->section_id);
        } elseif ($user->isManager() && $user->department_id) {
            $query->where('department_id', $user->department_id);
        }

        if ($request->filled('section_id')) {
            $sectionId = (int) $request->input('section_id');
            if ($user->canAccessSection($sectionId)) {
                $query->where('section_id', $sectionId);
            }
        }

        if ($request->filled('fiscal_year')) {
            $query->where('fiscal_year', (int) $request->input('fiscal_year'));
        }

        if ($request->filled('fiscal_month')) {
            $query->where('fiscal_month', (int) $request->input('fiscal_month'));
        }

        $plans = $query->paginate(20)->withQueryString();

        // Accessible sections for filter
        $sectionsQuery = Section::where('is_active', true)->orderBy('name');
        if ($user->isTeamLeader() && $user->section_id) {
            $sectionsQuery->where('id', $user->section_id);
        } elseif ($user->isManager() && $user->department_id) {
            $sectionsQuery->where('department_id', $user->department_id);
        }

        return Inertia::render('overtime/PlanningIndex', [
            'plans' => $plans,
            'sections' => $sectionsQuery->get(['id', 'code', 'name', 'department_id']),
            'filters' => $request->only(['section_id', 'fiscal_year', 'fiscal_month']),
        ]);
    }

    /**
     * Show the monthly planning grid form.
     */
    public function create(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        [$departments, $sections, $defaultDepartmentId, $defaultSectionId] = $this->resolveScope($user);

        $now = Carbon::now('Asia/Jakarta');
        $fiscalYear = (int) ($request->input('fiscal_year') ?? $now->year);
        $fiscalMonth = (int) ($request->input('fiscal_month') ?? $now->month);
        $sectionId = (int) ($request->input('section_id') ?? $defaultSectionId ?? 0);

        // Check for existing plan for this section/period
        $existingPlan = OvertimePlan::forSectionPeriod($sectionId, $fiscalYear, $fiscalMonth)
            ->with([
                'items.employee:id,npk,full_name',
            ])
            ->first();

        // Resolve the calendar for the selected month (day types)
        $calendarDays = $this->getCalendarDays($fiscalYear, $fiscalMonth);

        // Roster for initial section
        $roster = $sectionId
            ? Employee::where('section_id', $sectionId)
                ->where('is_active', true)
                ->orderBy('full_name')
                ->get(['id', 'npk', 'full_name', 'job_position'])
            : collect();

        $resolvedSectionId = $sectionId ?: ($defaultSectionId ?? 0);

        return Inertia::render('overtime/Planning', [
            'departments' => $departments,
            'sections' => $sections,
            'selected_department_id' => $defaultDepartmentId,
            'selected_section_id' => $resolvedSectionId ?: null,
            'fiscal_year' => $fiscalYear,
            'fiscal_month' => $fiscalMonth,
            'calendar_days' => $calendarDays,
            'initial_roster' => $roster,
            'existing_plan' => $existingPlan,
            'actuals' => $this->getSectionActuals((int) $resolvedSectionId, $fiscalYear, $fiscalMonth),
        ]);
    }

    /**
     * Show plan for editing.
     */
    public function edit(Request $request, OvertimePlan $overtimePlan): Response
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->canAccessSection($overtimePlan->section_id)) {
            abort(403, __('Anda tidak memiliki akses ke data seksi ini.'));
        }

        $overtimePlan->load([
            'items.employee:id,npk,full_name',
            'section:id,name,code,department_id',
            'department:id,name,code',
        ]);

        [$departments, $sections] = $this->resolveScope($user);
        $calendarDays = $this->getCalendarDays($overtimePlan->fiscal_year, $overtimePlan->fiscal_month);

        $roster = Employee::where('section_id', $overtimePlan->section_id)
            ->where('is_active', true)
            ->orderBy('full_name')
            ->get(['id', 'npk', 'full_name', 'job_position']);

        return Inertia::render('overtime/Planning', [
            'departments' => $departments,
            'sections' => $sections,
            'selected_department_id' => $overtimePlan->department_id,
            'selected_section_id' => $overtimePlan->section_id,
            'fiscal_year' => $overtimePlan->fiscal_year,
            'fiscal_month' => $overtimePlan->fiscal_month,
            'calendar_days' => $calendarDays,
            'initial_roster' => $roster,
            'existing_plan' => $overtimePlan,
            'actuals' => $this->getSectionActuals(
                $overtimePlan->section_id,
                $overtimePlan->fiscal_year,
                $overtimePlan->fiscal_month,
            ),
        ]);
    }

    /**
     * Store or upsert a planning document.
     */
    public function store(StorePlanningRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $validated = $request->validated();

        if (! $user->canAccessSection((int) $validated['section_id'])) {
            abort(403, __('Anda tidak memiliki akses ke data seksi ini.'));
        }

        $plan = DB::transaction(function () use ($validated, $user): OvertimePlan {
            /** @var OvertimePlan $plan */
            $plan = OvertimePlan::updateOrCreate(
                [
                    'section_id' => $validated['section_id'],
                    'fiscal_year' => $validated['fiscal_year'],
                    'fiscal_month' => $validated['fiscal_month'],
                ],
                [
                    'department_id' => $validated['department_id'],
                    'plan_code' => $this->generatePlanCode(
                        (int) $validated['section_id'],
                        (int) $validated['fiscal_year'],
                        (int) $validated['fiscal_month'],
                    ),
                    'status' => 'DRAFT',
                    'submitted_by_user_id' => $user->id,
                    'notes' => $validated['notes'] ?? null,
                ],
            );

            // Upsert each item
            foreach ($validated['items'] as $item) {
                $employee = Employee::findOrFail((int) $item['employee_id']);
                // Use startOfDay() so the datetime comparison works on both
                // MySQL (DATE column) and SQLite (stores as Y-m-d H:i:s text).
                $planDate = Carbon::parse($item['plan_date'])->startOfDay();

                OvertimePlanItem::updateOrCreate(
                    [
                        'overtime_plan_id' => $plan->id,
                        'employee_id' => $item['employee_id'],
                        'plan_date' => $planDate,
                    ],
                    [
                        'npk_snapshot' => $employee->npk,
                        'day_type' => $this->getDayType($item['plan_date']),
                        'hours_production' => $item['hours_production'],
                        'hours_tpm' => $item['hours_tpm'],
                        'hours_project' => $item['hours_project'],
                        'hours_others' => $item['hours_others'],
                    ],
                );
            }

            return $plan;
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Planning lembur :code berhasil disimpan.', ['code' => $plan->plan_code]),
        ]);

        return redirect()->route('overtime.planning.edit', $plan)
            ->with('success', __('Planning lembur :code berhasil disimpan.', ['code' => $plan->plan_code]));
    }

    /**
     * Update an existing plan (items + status).
     */
    public function update(UpdatePlanningRequest $request, OvertimePlan $overtimePlan): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->canAccessSection($overtimePlan->section_id)) {
            abort(403, __('Anda tidak memiliki akses ke data seksi ini.'));
        }

        $validated = $request->validated();

        DB::transaction(function () use ($overtimePlan, $validated): void {
            $overtimePlan->update([
                'notes' => $validated['notes'] ?? $overtimePlan->notes,
                'status' => $validated['status'] ?? $overtimePlan->status,
            ]);

            foreach ($validated['items'] as $item) {
                $employee = Employee::findOrFail((int) $item['employee_id']);
                $planDate = Carbon::parse($item['plan_date'])->startOfDay();

                OvertimePlanItem::updateOrCreate(
                    [
                        'overtime_plan_id' => $overtimePlan->id,
                        'employee_id' => $item['employee_id'],
                        'plan_date' => $planDate,
                    ],
                    [
                        'npk_snapshot' => $employee->npk,
                        'day_type' => $this->getDayType($item['plan_date']),
                        'hours_production' => $item['hours_production'],
                        'hours_tpm' => $item['hours_tpm'],
                        'hours_project' => $item['hours_project'],
                        'hours_others' => $item['hours_others'],
                    ],
                );
            }
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Planning lembur :code berhasil diperbarui.', ['code' => $overtimePlan->plan_code]),
        ]);

        return redirect()->route('overtime.planning.edit', $overtimePlan)
            ->with('success', __('Planning lembur :code berhasil diperbarui.', ['code' => $overtimePlan->plan_code]));
    }

    /**
     * Publish a draft plan.
     */
    public function publish(Request $request, OvertimePlan $overtimePlan): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->canAccessSection($overtimePlan->section_id)) {
            abort(403, __('Anda tidak memiliki akses ke data seksi ini.'));
        }

        $overtimePlan->update(['status' => 'PUBLISHED']);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Planning lembur :code telah dipublikasikan.', ['code' => $overtimePlan->plan_code]),
        ]);

        return back();
    }

    /**
     * Delete a draft plan.
     */
    public function destroy(Request $request, OvertimePlan $overtimePlan): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->canAccessSection($overtimePlan->section_id)) {
            abort(403, __('Anda tidak memiliki akses ke data seksi ini.'));
        }

        if ($overtimePlan->status === 'PUBLISHED') {
            abort(422, __('Planning yang sudah dipublikasikan tidak dapat dihapus.'));
        }

        $overtimePlan->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Planning lembur berhasil dihapus.'),
        ]);

        return redirect()->route('overtime.planning.index');
    }

    /**
     * Get employee roster for a section (API).
     */
    public function roster(Request $request, int $sectionId): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->canAccessSection($sectionId)) {
            return response()->json(['message' => __('Anda tidak memiliki akses ke seksi ini.')], 403);
        }

        $roster = Employee::where('section_id', $sectionId)
            ->where('is_active', true)
            ->orderBy('full_name')
            ->get(['id', 'npk', 'full_name', 'job_position']);

        return response()->json(['employees' => $roster]);
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    /**
     * @return array{0: Collection, 1: Collection, 2: int|null, 3: int|null}
     */
    private function resolveScope(User $user): array
    {
        if ($user->isAdmin()) {
            $departments = Department::where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']);
        } elseif ($user->isManager() && $user->department_id) {
            $departments = Department::where('id', $user->department_id)->get(['id', 'code', 'name']);
        } else {
            $departments = Department::where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']);
        }

        $defaultDeptId = $user->department_id ?? $departments->first()?->id;

        $sectionsQuery = Section::where('is_active', true)->orderBy('name');
        if ($user->isTeamLeader() && $user->section_id) {
            $sectionsQuery->where('id', $user->section_id);
            $defaultSectionId = $user->section_id;
        } elseif ($defaultDeptId) {
            $sectionsQuery->where('department_id', $defaultDeptId);
            $defaultSectionId = $sectionsQuery->clone()->value('id');
        } else {
            $defaultSectionId = null;
        }

        $sections = $sectionsQuery->get(['id', 'department_id', 'code', 'name']);

        return [$departments, $sections, $defaultDeptId, $defaultSectionId];
    }

    /**
     * Build calendar day metadata for a given month.
     *
     * @return array<int, array{day: int, date: string, day_type: string, day_name: string}>
     */
    private function getCalendarDays(int $year, int $month): array
    {
        $daysInMonth = Carbon::createFromDate($year, $month, 1)->daysInMonth;
        $days = [];

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $date = Carbon::createFromDate($year, $month, $day);
            $dateStr = $date->format('Y-m-d');

            $calendar = OperationalCalendar::find($dateStr);
            $dayType = $calendar?->day_type ?? ($date->isWeekend() ? 'HLR' : 'HKN');

            $days[] = [
                'day' => $day,
                'date' => $dateStr,
                'day_type' => $dayType,
                'day_name' => $date->locale('id')->isoFormat('ddd'),
            ];
        }

        return $days;
    }

    private function getDayType(string $date): string
    {
        $calendar = OperationalCalendar::find($date);

        return $calendar?->day_type ?? (Carbon::parse($date)->isWeekend() ? 'HLR' : 'HKN');
    }

    /**
     * Aggregate approved realized overtime for Plan vs Actual monitoring.
     *
     * Week buckets match MonthlySnapshotService: days 1–7 → W1 … 29+ → W5.
     * Index uses DashboardKpiService multipliers (HKN × 1.5, HLR × 2.0).
     *
     * @return array<int, array{weeks: array<int, array{hours_production: float, hours_tpm: float, hours_project: float, hours_others: float, index_total: float}>}>
     */
    private function getSectionActuals(int $sectionId, int $year, int $month): array
    {
        if ($sectionId <= 0) {
            return [];
        }

        $start = Carbon::create($year, $month, 1, 0, 0, 0, 'Asia/Jakarta')->startOfDay();
        $end = $start->copy()->endOfMonth();

        $items = OvertimeItem::query()
            ->join('overtime_submissions', 'overtime_items.overtime_submission_id', '=', 'overtime_submissions.id')
            ->where('overtime_submissions.section_id', $sectionId)
            ->whereBetween('overtime_submissions.operational_date', [
                $start->toDateString(),
                $end->toDateString(),
            ])
            ->where('overtime_items.status', 'APPROVED')
            ->select([
                'overtime_items.employee_id',
                'overtime_items.hours_production',
                'overtime_items.hours_tpm',
                'overtime_items.hours_project',
                'overtime_items.hours_others',
                'overtime_submissions.operational_date',
                'overtime_submissions.day_type',
            ])
            ->get();

        $emptyWeek = static fn (): array => [
            'hours_production' => 0.0,
            'hours_tpm' => 0.0,
            'hours_project' => 0.0,
            'hours_others' => 0.0,
            'index_total' => 0.0,
        ];

        $actuals = [];

        foreach ($items as $item) {
            $employeeId = (int) $item->employee_id;
            $day = Carbon::parse($item->operational_date)->day;
            $week = match (true) {
                $day <= 7 => 1,
                $day <= 14 => 2,
                $day <= 21 => 3,
                $day <= 28 => 4,
                default => 5,
            };

            if (! isset($actuals[$employeeId])) {
                $actuals[$employeeId] = [
                    'weeks' => [
                        1 => $emptyWeek(),
                        2 => $emptyWeek(),
                        3 => $emptyWeek(),
                        4 => $emptyWeek(),
                        5 => $emptyWeek(),
                    ],
                ];
            }

            $prod = (float) $item->hours_production;
            $tpm = (float) $item->hours_tpm;
            $proj = (float) $item->hours_project;
            $others = (float) $item->hours_others;
            $totalHours = $prod + $tpm + $proj + $others;
            $multiplier = $item->day_type === 'HLR'
                ? DashboardKpiService::HLR_MULTIPLIER
                : DashboardKpiService::HKN_MULTIPLIER;

            $actuals[$employeeId]['weeks'][$week]['hours_production'] += $prod;
            $actuals[$employeeId]['weeks'][$week]['hours_tpm'] += $tpm;
            $actuals[$employeeId]['weeks'][$week]['hours_project'] += $proj;
            $actuals[$employeeId]['weeks'][$week]['hours_others'] += $others;
            $actuals[$employeeId]['weeks'][$week]['index_total'] += $totalHours * $multiplier;
        }

        // Round for stable JSON / UI display
        foreach ($actuals as $employeeId => $payload) {
            foreach ($payload['weeks'] as $week => $bucket) {
                foreach ($bucket as $key => $value) {
                    $actuals[$employeeId]['weeks'][$week][$key] = round((float) $value, 2);
                }
            }
        }

        return $actuals;
    }

    private function generatePlanCode(int $sectionId, int $year, int $month): string
    {
        $section = Section::find($sectionId);
        $code = $section ? strtoupper(substr($section->code, 0, 5)) : 'SECT';

        return sprintf('PLN-%s-%04d%02d', $code, $year, $month);
    }
}
