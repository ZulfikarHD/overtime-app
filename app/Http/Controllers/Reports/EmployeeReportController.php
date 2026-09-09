<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Section;
use App\Models\User;
use App\Services\EmployeeReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeeReportController extends Controller
{
    public function __construct(
        public EmployeeReportService $employeeReportService,
    ) {}

    /**
     * Display the Employee Dossier Hub roster and lookup view.
     */
    public function index(Request $request): Response|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->isUser()) {
            if ($user->npk) {
                return redirect()->route('reports.employees.show', ['npk' => $user->npk]);
            }

            return redirect()->route('dashboard');
        }

        $filters = [
            'department_id' => $request->filled('department_id') ? $request->input('department_id') : null,
            'section_id' => $request->filled('section_id') ? $request->input('section_id') : null,
            'search' => $request->filled('search') ? $request->string('search')->value() : null,
        ];

        $roster = $this->employeeReportService->getRoster($user, $filters);

        $departments = [];
        $sections = [];

        if ($user->isAdmin()) {
            $departments = Department::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'code', 'name'])
                ->toArray();

            $sectionsQuery = Section::query()->where('is_active', true)->orderBy('name');
            if (! empty($filters['department_id'])) {
                $sectionsQuery->where('department_id', (int) $filters['department_id']);
            }
            $sections = $sectionsQuery->get(['id', 'department_id', 'code', 'name'])->toArray();
        } elseif ($user->isManager() && $user->department_id) {
            $sections = Section::query()
                ->where('department_id', $user->department_id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'department_id', 'code', 'name'])
                ->toArray();
        }

        $now = Carbon::now('Asia/Jakarta');

        return Inertia::render('reports/EmployeeDossier', [
            'employee' => null,
            'summary' => null,
            'peer_comparison' => null,
            'welfare_status' => null,
            'roster' => $roster,
            'filters' => $filters,
            'departments' => $departments,
            'sections' => $sections,
            'fiscal_year' => (int) $now->format('Y'),
            'fiscal_month' => (int) $now->format('n'),
            'current_tab' => 'overview',
            'timesheet' => null,
            'timesheet_filters' => [],
        ]);
    }

    /**
     * Live search endpoint for debounced employee lookup dropdown.
     */
    public function search(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $q = $request->string('q', '')->value();
        $results = $this->employeeReportService->search($user, $q);

        return response()->json([
            'data' => $results,
        ]);
    }

    /**
     * Display the complete dossier for an individual employee.
     */
    public function show(Request $request, string $npk): Response
    {
        /** @var User $user */
        $user = $request->user();

        $employee = $this->employeeReportService->getEmployeeDossier($user, $npk);

        $now = Carbon::now('Asia/Jakarta');
        $fiscalYear = $request->integer('year', (int) $now->format('Y'));
        $fiscalMonth = $request->integer('month', (int) $now->format('n'));
        $currentTab = $request->string('tab', 'overview')->value();

        $summary = $this->employeeReportService->getSummary($employee['id'], $fiscalYear, $fiscalMonth);

        $sectionId = (int) ($employee['section']['id'] ?? 0);
        $peerComparison = $this->employeeReportService->getPeerComparison(
            $employee['id'],
            $sectionId,
            $fiscalYear,
            $fiscalMonth,
            $user->isUser(),
        );

        $welfareStatus = $this->employeeReportService->getWelfareStatus(
            $employee['id'],
            $fiscalYear,
            $fiscalMonth,
        );

        $timesheetFilters = [
            'status' => $request->string('status', 'all')->value(),
            'category' => $request->string('category', 'all')->value(),
            'date_from' => $request->filled('date_from') ? $request->string('date_from')->value() : null,
            'date_to' => $request->filled('date_to') ? $request->string('date_to')->value() : null,
            'all_time' => $request->boolean('all_time', false),
            'search' => $request->filled('search') ? $request->string('search')->value() : null,
            'sort_by' => $request->string('sort_by', 'operational_date')->value(),
            'sort_dir' => $request->string('sort_dir', 'desc')->value(),
            'fiscal_year' => $fiscalYear,
            'fiscal_month' => $fiscalMonth,
        ];

        $timesheet = $this->employeeReportService->getTimesheet(
            $employee['id'],
            $timesheetFilters,
            25,
        );

        return Inertia::render('reports/EmployeeDossier', [
            'employee' => $employee,
            'summary' => $summary,
            'peer_comparison' => $peerComparison,
            'welfare_status' => $welfareStatus,
            'timesheet' => $timesheet,
            'timesheet_filters' => $timesheetFilters,
            'roster' => [],
            'filters' => [],
            'departments' => [],
            'sections' => [],
            'fiscal_year' => $fiscalYear,
            'fiscal_month' => $fiscalMonth,
            'current_tab' => $currentTab,
            'is_own_dossier' => $user->npk === $employee['npk'],
        ]);
    }

    /**
     * Timesheet tab shortcut redirect to unified dossier hub.
     */
    public function timesheet(Request $request, string $npk): RedirectResponse
    {
        return redirect()->route('reports.employees.show', array_merge(
            $request->query(),
            [
                'npk' => $npk,
                'tab' => 'timesheet',
            ]
        ));
    }

    /**
     * Streamed personal timesheet CSV export for an individual employee.
     */
    public function exportTimesheet(Request $request, string $npk): StreamedResponse
    {
        /** @var User $user */
        $user = $request->user();

        $now = Carbon::now('Asia/Jakarta');
        $fiscalYear = $request->integer('year', (int) $now->format('Y'));
        $fiscalMonth = $request->integer('month', (int) $now->format('n'));

        $filters = [
            'status' => $request->string('status', 'all')->value(),
            'category' => $request->string('category', 'all')->value(),
            'date_from' => $request->filled('date_from') ? $request->string('date_from')->value() : null,
            'date_to' => $request->filled('date_to') ? $request->string('date_to')->value() : null,
            'all_time' => $request->boolean('all_time', false),
            'search' => $request->filled('search') ? $request->string('search')->value() : null,
            'sort_by' => $request->string('sort_by', 'operational_date')->value(),
            'sort_dir' => $request->string('sort_dir', 'desc')->value(),
            'fiscal_year' => $fiscalYear,
            'fiscal_month' => $fiscalMonth,
        ];

        return $this->employeeReportService->exportTimesheetCsv($user, $npk, $filters);
    }
}
