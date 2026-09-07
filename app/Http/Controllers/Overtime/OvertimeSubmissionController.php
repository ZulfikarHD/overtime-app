<?php

namespace App\Http\Controllers\Overtime;

use App\Actions\Overtime\SubmitOvertimeAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Overtime\StoreOvertimeSubmissionRequest;
use App\Models\CapexProject;
use App\Models\Department;
use App\Models\Employee;
use App\Models\OperationalCalendar;
use App\Models\OvertimeBudget;
use App\Models\OvertimeSubmission;
use App\Models\Section;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class OvertimeSubmissionController extends Controller
{
    public function __construct(
        public SubmitOvertimeAction $submitOvertimeAction,
    ) {}

    /**
     * Display the daily overtime batch submission form.
     */
    public function create(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        // 1. Resolve departments accessible to the user
        if ($user->isAdmin()) {
            $departments = Department::where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']);
        } elseif ($user->isManager() && $user->department_id) {
            $departments = Department::where('id', $user->department_id)->get(['id', 'code', 'name']);
        } elseif ($user->department_id) {
            $departments = Department::where('id', $user->department_id)->get(['id', 'code', 'name']);
        } else {
            $departments = Department::where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']);
        }

        $defaultDepartmentId = $user->department_id ?? $departments->first()?->id;

        // 2. Resolve sections accessible to the user
        $sectionsQuery = Section::where('is_active', true)->orderBy('name');
        if ($user->isTeamLeader() && $user->section_id) {
            $sectionsQuery->where('id', $user->section_id);
            $defaultDepartmentId = $user->department_id ?? $defaultDepartmentId;
            $defaultSectionId = $user->section_id;
        } elseif ($defaultDepartmentId) {
            $sectionsQuery->where('department_id', $defaultDepartmentId);
            $defaultSectionId = $sectionsQuery->first()?->id;
        } else {
            $defaultSectionId = null;
        }

        $sections = $sectionsQuery->get(['id', 'department_id', 'code', 'name']);

        // 3. Resolve active CapEx projects
        $activeCapexProjects = CapexProject::active()
            ->orderBy('name')
            ->get(['id', 'project_code', 'asset_code', 'name']);

        // 4. Resolve date & calendar day classification
        $todayWib = Carbon::now('Asia/Jakarta')->format('Y-m-d');
        $calendar = OperationalCalendar::find($todayWib)
            ?? OperationalCalendar::whereDate('calendar_date', $todayWib)->first();
        if (! $calendar) {
            $calendar = OperationalCalendar::create([
                'calendar_date' => $todayWib,
                'day_type' => Carbon::parse($todayWib)->isWeekend() ? 'HLR' : 'HKN',
                'is_holiday' => false,
            ]);
        }

        // 5. Calculate monthly budget burn indicator for default section
        $burnIndicator = $this->getSectionBurnIndicator($defaultSectionId);

        // 6. Preload roster for default section
        $roster = [];
        if ($defaultSectionId) {
            $roster = Employee::where('section_id', $defaultSectionId)
                ->where('is_active', true)
                ->orderBy('full_name')
                ->get(['id', 'npk', 'full_name', 'job_position', 'hourly_rate']);
        }

        return Inertia::render('overtime/Create', [
            'departments' => $departments,
            'sections' => $sections,
            'selected_department_id' => $defaultDepartmentId,
            'selected_section_id' => $defaultSectionId,
            'active_capex_projects' => $activeCapexProjects,
            'today' => $todayWib,
            'default_day_type' => $calendar->day_type,
            'burn_indicator' => $burnIndicator,
            'initial_roster' => $roster,
            'last_submission' => session('last_submission'),
        ]);
    }

    /**
     * Store a batch overtime submission.
     */
    public function store(StoreOvertimeSubmissionRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $submission = $this->submitOvertimeAction->execute($request->validated(), $user->id);

        $lastSubmissionData = [
            'submission_code' => $submission->submission_code,
            'crew_count' => $submission->items->count(),
            'total_hours' => (float) $submission->total_hours_cached,
            'total_cost' => (float) $submission->items->sum('total_cost_snapshot'),
            'due_date' => $submission->spklDocument?->due_date?->format('d/m/Y'),
            'operational_date' => $submission->operational_date->format('d/m/Y'),
            'section_name' => $submission->section->name,
        ];

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Pengajuan lembur berhasil dikirim: :code', ['code' => $submission->submission_code]),
        ]);

        return redirect()->route('overtime.submissions.create')
            ->with('success', __('Pengajuan lembur berhasil dikirim: :code', ['code' => $submission->submission_code]))
            ->with('last_submission', $lastSubmissionData);
    }

    /**
     * Get active employee roster for a section via API.
     */
    public function roster(Request $request, int $sectionId): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->canAccessSection($sectionId)) {
            return response()->json([
                'message' => __('Anda tidak memiliki akses ke seksi ini.'),
            ], 403);
        }

        $employees = Employee::where('section_id', $sectionId)
            ->where('is_active', true)
            ->orderBy('full_name')
            ->get(['id', 'npk', 'full_name', 'job_position', 'hourly_rate']);

        $burnIndicator = $this->getSectionBurnIndicator($sectionId);

        return response()->json([
            'employees' => $employees,
            'burn_indicator' => $burnIndicator,
        ]);
    }

    /**
     * Display a listing of overtime submissions (History Hub).
     */
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $query = OvertimeSubmission::query()
            ->with(['section:id,name,code', 'department:id,name,code', 'submittedBy:id,name,npk', 'spklDocument'])
            ->latest('operational_date');

        if ($user->isTeamLeader() && $user->section_id) {
            $query->where('section_id', $user->section_id);
        } elseif ($user->isManager() && $user->department_id) {
            $query->where('department_id', $user->department_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('operational_date', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('operational_date', '<=', $request->input('date_to'));
        }

        $submissions = $query->paginate(20)->withQueryString();

        return Inertia::render('overtime/Index', [
            'submissions' => $submissions,
            'filters' => [
                'status' => $request->input('status', ''),
                'date_from' => $request->input('date_from', ''),
                'date_to' => $request->input('date_to', ''),
            ],
        ]);
    }

    /**
     * Compute the section monthly budget burn indicator.
     *
     * @return array{
     *     planned_hours: float,
     *     actual_hours: float,
     *     burn_pct: float,
     *     burn_zone: 'safe'|'caution'|'danger'
     * }
     */
    protected function getSectionBurnIndicator(?int $sectionId): array
    {
        if (! $sectionId) {
            return [
                'planned_hours' => 0.0,
                'actual_hours' => 0.0,
                'burn_pct' => 0.0,
                'burn_zone' => 'safe',
            ];
        }

        $now = Carbon::now('Asia/Jakarta');
        $year = (int) $now->format('Y');
        $month = (int) $now->format('n');

        $budget = OvertimeBudget::where('section_id', $sectionId)
            ->where('fiscal_year', $year)
            ->where('fiscal_month', $month)
            ->first();

        $plannedHours = $budget ? (float) $budget->planned_hours : 0.0;

        $actualHours = (float) OvertimeSubmission::where('section_id', $sectionId)
            ->whereYear('operational_date', $year)
            ->whereMonth('operational_date', $month)
            ->whereIn('status', ['SUBMITTED', 'PARTIALLY_APPROVED', 'APPROVED'])
            ->sum('total_hours_cached');

        $burnPct = $plannedHours > 0 ? round(($actualHours / $plannedHours) * 100, 1) : 0.0;

        $burnZone = 'safe';
        if ($burnPct > 100.0) {
            $burnZone = 'danger';
        } elseif ($burnPct >= 85.0) {
            $burnZone = 'caution';
        }

        return [
            'planned_hours' => $plannedHours,
            'actual_hours' => $actualHours,
            'burn_pct' => $burnPct,
            'burn_zone' => $burnZone,
        ];
    }
}
