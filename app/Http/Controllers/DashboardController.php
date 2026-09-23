<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Section;
use App\Models\User;
use App\Services\Analytics\DashboardKpiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        public DashboardKpiService $kpiService,
    ) {}

    /**
     * Display the main operational dashboard or redirect line operators to their self-service dashboard.
     */
    public function index(Request $request): Response|RedirectResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user?->isUser()) {
            return redirect()->route('my.dashboard');
        }

        $date = $request->filled('date') ? $request->string('date')->value() : null;

        $rawDept = $request->input('department_id');
        $departmentId = null;
        if ($request->has('department_id') && $rawDept !== '') {
            $departmentId = ($rawDept === 'all' || (int) $rawDept === 0) ? 0 : (int) $rawDept;
        }

        $sectionId = $request->filled('section_id') ? $request->integer('section_id') : null;
        $sectionId = $this->sanitizeSectionId($user, $departmentId, $sectionId);

        $kpiCards = $this->kpiService->getKpiCards($user, $date, $departmentId, $sectionId);
        $weeklyPlanningVsActual = $this->kpiService->getWeeklyPlanningVsActual($user, $date, $departmentId, $sectionId);
        $dailyBurnChart = $this->kpiService->getDailyBurnChart($user, $date, $departmentId, $sectionId);
        $dailyBurnUpIndex = $this->kpiService->getDailyBurnUpIndex($user, $date, $departmentId, $sectionId);
        $sectionBurnComparison = $this->kpiService->getSectionBurnComparison($user, $date, $departmentId);
        $leaderboard = $this->kpiService->getOvertimeLeaderboard($user, $date, $departmentId, $sectionId);
        $categoryDistribution = $this->kpiService->getCategoryDistribution($user, $date, $departmentId, $sectionId);
        $trendWorkingTime = $this->kpiService->getTrendWorkingTime($user, $date, $departmentId, $sectionId);
        $ytdOvertimeIndex = $this->kpiService->getYtdOvertimeIndex($user, $date, $departmentId, $sectionId);
        $dailyIndexTrend = $this->kpiService->getDailyIndexTrend($user, $date, $departmentId, $sectionId);
        $dayTypeBreakdown = $this->kpiService->getDayTypeBreakdown($user, $date, $departmentId, $sectionId);
        $employeeSummary = $this->kpiService->getEmployeeSummaryTable($user, $date, $departmentId, $sectionId);

        $tab = $request->query('tab', 'pacing');
        if (! in_array($tab, ['pacing', 'distribution', 'employees'], true)) {
            $tab = 'pacing';
        }

        $departments = Department::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        $sections = $this->availableSectionsForFilter($user, $departmentId);

        return Inertia::render('Dashboard', [
            'currentTab' => $tab,
            'kpiCards' => $kpiCards,
            'weeklyPlanningVsActual' => $weeklyPlanningVsActual,
            'dailyBurnChart' => $dailyBurnChart,
            'dailyBurnUpIndex' => $dailyBurnUpIndex,
            'sectionBurnComparison' => $sectionBurnComparison,
            'leaderboard' => $leaderboard,
            'categoryDistribution' => $categoryDistribution,
            'trendWorkingTime' => $trendWorkingTime,
            'ytdOvertimeIndex' => $ytdOvertimeIndex,
            'dailyIndexTrend' => $dailyIndexTrend,
            'dayTypeBreakdown' => $dayTypeBreakdown,
            'employeeSummary' => $employeeSummary,
            'departments' => $departments,
            'sections' => $sections,
            'selectedDepartmentId' => $kpiCards['scope']['department_id'],
            'selectedSectionId' => $dailyBurnChart['scope']['section_id'],
            'selectedDate' => $kpiCards['scope']['selected_date'],
        ]);
    }

    /**
     * Return JSON endpoint for asynchronous KPI card refresh or partial reloads.
     */
    public function kpiCards(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user?->isUser()) {
            return response()->json(['error' => 'Unauthorized for operational dashboard'], 403);
        }

        [$date, $departmentId, $sectionId] = $this->extractFilterParams($request, $user);

        $data = $this->kpiService->getKpiCards($user, $date, $departmentId, $sectionId);

        return response()->json($data);
    }

    /**
     * Return JSON endpoint for daily cumulative burn line chart (E09-02).
     */
    public function dailyBurnChart(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user?->isUser()) {
            return response()->json(['error' => 'Unauthorized for operational dashboard'], 403);
        }

        [$date, $departmentId, $sectionId] = $this->extractFilterParams($request, $user);

        $data = $this->kpiService->getDailyBurnChart($user, $date, $departmentId, $sectionId);

        return response()->json($data);
    }

    /**
     * Return JSON endpoint for section burn comparison bar chart (E09-03).
     */
    public function sectionBurnComparison(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user?->isUser()) {
            return response()->json(['error' => 'Unauthorized for operational dashboard'], 403);
        }

        [$date, $departmentId] = $this->extractFilterParams($request, $user);

        $data = $this->kpiService->getSectionBurnComparison($user, $date, $departmentId);

        return response()->json($data);
    }

    /**
     * Return JSON endpoint for overtime leaderboard chart (E09-04).
     */
    public function leaderboard(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user?->isUser()) {
            return response()->json(['error' => 'Unauthorized for operational dashboard'], 403);
        }

        [$date, $departmentId, $sectionId] = $this->extractFilterParams($request, $user);

        $data = $this->kpiService->getOvertimeLeaderboard($user, $date, $departmentId, $sectionId);

        return response()->json($data);
    }

    /**
     * Return JSON endpoint for category distribution donut chart (E09-04).
     */
    public function categoryDistribution(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user?->isUser()) {
            return response()->json(['error' => 'Unauthorized for operational dashboard'], 403);
        }

        [$date, $departmentId, $sectionId] = $this->extractFilterParams($request, $user);

        $data = $this->kpiService->getCategoryDistribution($user, $date, $departmentId, $sectionId);

        return response()->json($data);
    }

    /**
     * Return JSON endpoint for 12-month working time trend chart (E09-04).
     */
    public function trendWorkingTime(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user?->isUser()) {
            return response()->json(['error' => 'Unauthorized for operational dashboard'], 403);
        }

        [$date, $departmentId, $sectionId] = $this->extractFilterParams($request, $user);

        $data = $this->kpiService->getTrendWorkingTime($user, $date, $departmentId, $sectionId);

        return response()->json($data);
    }

    /**
     * Return JSON endpoint for daily burn index contribution trend chart (E09-04).
     */
    public function dailyIndexTrend(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user?->isUser()) {
            return response()->json(['error' => 'Unauthorized for operational dashboard'], 403);
        }

        [$date, $departmentId, $sectionId] = $this->extractFilterParams($request, $user);

        $data = $this->kpiService->getDailyIndexTrend($user, $date, $departmentId, $sectionId);

        return response()->json($data);
    }

    /**
     * Return JSON endpoint for weekly day type breakdown chart (E09-04).
     */
    public function dayTypeBreakdown(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user?->isUser()) {
            return response()->json(['error' => 'Unauthorized for operational dashboard'], 403);
        }

        [$date, $departmentId, $sectionId] = $this->extractFilterParams($request, $user);

        $data = $this->kpiService->getDayTypeBreakdown($user, $date, $departmentId, $sectionId);

        return response()->json($data);
    }

    /**
     * Return JSON endpoint for employee overtime summary table (E09-05).
     */
    public function employeeSummaryTable(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user?->isUser()) {
            return response()->json(['error' => 'Unauthorized for operational dashboard'], 403);
        }

        [$date, $departmentId, $sectionId] = $this->extractFilterParams($request, $user);

        $data = $this->kpiService->getEmployeeSummaryTable($user, $date, $departmentId, $sectionId);

        return response()->json($data);
    }

    /**
     * Extract filter parameters from request.
     *
     * @return array{0: ?string, 1: ?int, 2: ?int}
     */
    protected function extractFilterParams(Request $request, ?User $user = null): array
    {
        $date = $request->filled('date') ? $request->string('date')->value() : null;

        $rawDept = $request->input('department_id');
        $departmentId = null;
        if ($request->has('department_id') && $rawDept !== '') {
            $departmentId = ($rawDept === 'all' || (int) $rawDept === 0) ? 0 : (int) $rawDept;
        }

        $sectionId = $request->filled('section_id') ? $request->integer('section_id') : null;
        $sectionId = $this->sanitizeSectionId($user, $departmentId, $sectionId);

        return [$date, $departmentId, $sectionId];
    }

    /**
     * Drop section filters that do not belong to the active department scope.
     */
    protected function sanitizeSectionId(?User $user, ?int $departmentId, ?int $sectionId): ?int
    {
        if ($sectionId === null || ! $user) {
            return null;
        }

        if ($user->isTeamLeader() || (! $user->isAdmin() && ! $user->isManager())) {
            return $user->section_id ? (int) $user->section_id : null;
        }

        $section = Section::query()
            ->where('id', $sectionId)
            ->where('is_active', true)
            ->first(['id', 'department_id']);

        if (! $section) {
            return null;
        }

        $effectiveDepartmentId = $departmentId;
        if ($user->isManager()) {
            $effectiveDepartmentId = $user->department_id ? (int) $user->department_id : null;
        }

        if ($effectiveDepartmentId && $effectiveDepartmentId !== 0
            && (int) $section->department_id !== $effectiveDepartmentId) {
            return null;
        }

        return (int) $section->id;
    }

    /**
     * Sections available for the filter dropdown, scoped by role and department.
     *
     * @return Collection<int, array{id: int, department_id: int, code: string, name: string}>
     */
    protected function availableSectionsForFilter(?User $user, ?int $departmentId): Collection
    {
        if (! $user) {
            return collect();
        }

        $query = Section::query()
            ->where('is_active', true)
            ->orderBy('name');

        if ($user->isTeamLeader()) {
            if ($user->section_id) {
                $query->where('id', (int) $user->section_id);
            } elseif ($user->department_id) {
                $query->where('department_id', (int) $user->department_id);
            }
        } elseif ($user->isManager()) {
            if ($user->department_id) {
                $query->where('department_id', (int) $user->department_id);
            }
        } elseif ($user->isAdmin()) {
            if ($departmentId && $departmentId !== 0) {
                $query->where('department_id', $departmentId);
            }
        } elseif ($user->department_id) {
            $query->where('department_id', (int) $user->department_id);
        }

        return $query->get(['id', 'department_id', 'code', 'name'])
            ->map(fn (Section $section): array => [
                'id' => (int) $section->id,
                'department_id' => (int) $section->department_id,
                'code' => $section->code,
                'name' => $section->name,
            ])
            ->values();
    }
}
