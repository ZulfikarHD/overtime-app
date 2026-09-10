<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\User;
use App\Services\Analytics\AnalyticsExportService;
use App\Services\Analytics\CorrelationAnalysisService;
use App\Services\Analytics\CostAnalysisService;
use App\Services\Analytics\PredictiveAnalyticsService;
use App\Services\Analytics\ScenarioCalculatorService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class AnalyticsController extends Controller
{
    /**
     * Valid client-side analytics tabs.
     *
     * @var list<string>
     */
    public const VALID_TABS = [
        'predictive',
        'cost',
        'correlation',
        'scenario',
        'insights',
        'comparison',
    ];

    public function __construct(
        public AnalyticsExportService $exportService,
        public PredictiveAnalyticsService $predictiveService,
        public CostAnalysisService $costService,
        public CorrelationAnalysisService $correlationService,
        public ScenarioCalculatorService $scenarioService,
    ) {}

    /**
     * Display the Analytics & Decision Intelligence Hub (E09-06).
     */
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($user && ($user->isAdmin() || $user->isManager()), 403);

        $tab = $request->query('tab', 'predictive');
        if (! in_array($tab, self::VALID_TABS, true)) {
            $tab = 'predictive';
        }

        $now = Carbon::now('Asia/Jakarta');
        $defaultStartDate = $now->copy()->startOfMonth()->toDateString();
        $defaultEndDate = $now->copy()->endOfMonth()->toDateString();

        $startDate = $request->filled('start_date')
            ? (string) $request->input('start_date')
            : $defaultStartDate;

        $endDate = $request->filled('end_date')
            ? (string) $request->input('end_date')
            : $defaultEndDate;

        $departments = Department::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        // Determine department selection based on user role and request
        if ($user->isManager()) {
            $selectedDepartmentId = $user->department_id ? (string) $user->department_id : 'all';
        } else {
            $rawDept = $request->input('department_id');
            if ($rawDept !== null && $rawDept !== '') {
                $selectedDepartmentId = ($rawDept === 'all' || (int) $rawDept === 0) ? 'all' : (string) ((int) $rawDept);
            } else {
                $selectedDepartmentId = 'all';
            }
        }

        $departmentIdInt = $selectedDepartmentId === 'all' ? null : (int) $selectedDepartmentId;
        $predictiveData = $tab === 'predictive'
            ? $this->predictiveService->getPredictiveData($user, $departmentIdInt, $startDate, $endDate)
            : null;

        $costData = $tab === 'cost'
            ? $this->costService->getCostData($user, $departmentIdInt, $startDate, $endDate)
            : null;

        $correlationData = $tab === 'correlation'
            ? $this->correlationService->getCorrelationData($user, $departmentIdInt, $startDate, $endDate)
            : null;

        $scenarioData = $tab === 'scenario'
            ? $this->scenarioService->getScenarioData($user, $departmentIdInt, $startDate, $endDate)
            : null;

        return Inertia::render('Analytics/Index', [
            'currentTab' => $tab,
            'departments' => $departments,
            'filters' => [
                'department_id' => $selectedDepartmentId,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'predictiveData' => $predictiveData,
            'costData' => $costData,
            'correlationData' => $correlationData,
            'scenarioData' => $scenarioData,
            'userRole' => is_string($user->role) ? $user->role : $user->role->value,
            'userDepartmentId' => $user->department_id,
        ]);
    }

    /**
     * Return JSON endpoint for predictive analytics data (E09-07).
     */
    public function predictive(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($user && ($user->isAdmin() || $user->isManager()), 403);

        $rawDept = $request->input('department_id');
        $departmentId = null;
        if ($request->has('department_id') && $rawDept !== '' && $rawDept !== 'all') {
            $departmentId = (int) $rawDept;
        }

        $startDate = $request->filled('start_date') ? (string) $request->input('start_date') : null;
        $endDate = $request->filled('end_date') ? (string) $request->input('end_date') : null;

        $data = $this->predictiveService->getPredictiveData($user, $departmentId, $startDate, $endDate);

        return response()->json($data);
    }

    /**
     * Return JSON endpoint for cost analysis data (E09-08).
     */
    public function costAnalysis(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($user && ($user->isAdmin() || $user->isManager()), 403);

        $rawDept = $request->input('department_id');
        $departmentId = null;
        if ($request->has('department_id') && $rawDept !== '' && $rawDept !== 'all') {
            $departmentId = (int) $rawDept;
        }

        $startDate = $request->filled('start_date') ? (string) $request->input('start_date') : null;
        $endDate = $request->filled('end_date') ? (string) $request->input('end_date') : null;

        $data = $this->costService->getCostData($user, $departmentId, $startDate, $endDate);

        return response()->json($data);
    }

    /**
     * Return JSON endpoint for correlation analysis data (E09-09).
     */
    public function correlation(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($user && ($user->isAdmin() || $user->isManager()), 403);

        $rawDept = $request->input('department_id');
        $departmentId = null;
        if ($request->has('department_id') && $rawDept !== '' && $rawDept !== 'all') {
            $departmentId = (int) $rawDept;
        }

        $startDate = $request->filled('start_date') ? (string) $request->input('start_date') : null;
        $endDate = $request->filled('end_date') ? (string) $request->input('end_date') : null;

        $data = $this->correlationService->getCorrelationData($user, $departmentId, $startDate, $endDate);

        return response()->json($data);
    }

    /**
     * Export analytics summary report as PDF or CSV.
     */
    public function export(Request $request): SymfonyResponse
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($user && ($user->isAdmin() || $user->isManager()), 403);

        // Security check: Managers cannot export foreign department records
        if ($user->isManager() && $request->filled('department_id')) {
            $reqDeptId = $request->input('department_id');
            if ($reqDeptId !== 'all' && (int) $reqDeptId !== (int) $user->department_id) {
                abort(403, __('Anda tidak memiliki akses untuk mengekspor data departemen lain.'));
            }
        }

        $format = strtolower((string) $request->input('format', 'pdf'));
        if (! in_array($format, ['pdf', 'csv'], true)) {
            $format = 'pdf';
        }

        return $this->exportService->export($user, $request->all(), $format);
    }
}
