<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\User;
use App\Services\Analytics\DashboardKpiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        $kpiCards = $this->kpiService->getKpiCards($user, $date, $departmentId);
        $dailyBurnChart = $this->kpiService->getDailyBurnChart($user, $date, $departmentId, $sectionId);
        $sectionBurnComparison = $this->kpiService->getSectionBurnComparison($user, $date, $departmentId);

        $departments = Department::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        return Inertia::render('Dashboard', [
            'kpiCards' => $kpiCards,
            'dailyBurnChart' => $dailyBurnChart,
            'sectionBurnComparison' => $sectionBurnComparison,
            'departments' => $departments,
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

        $date = $request->filled('date') ? $request->string('date')->value() : null;

        $rawDept = $request->input('department_id');
        $departmentId = null;
        if ($request->has('department_id') && $rawDept !== '') {
            $departmentId = ($rawDept === 'all' || (int) $rawDept === 0) ? 0 : (int) $rawDept;
        }

        $data = $this->kpiService->getKpiCards($user, $date, $departmentId);

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

        $date = $request->filled('date') ? $request->string('date')->value() : null;

        $rawDept = $request->input('department_id');
        $departmentId = null;
        if ($request->has('department_id') && $rawDept !== '') {
            $departmentId = ($rawDept === 'all' || (int) $rawDept === 0) ? 0 : (int) $rawDept;
        }

        $sectionId = $request->filled('section_id') ? $request->integer('section_id') : null;

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

        $date = $request->filled('date') ? $request->string('date')->value() : null;

        $rawDept = $request->input('department_id');
        $departmentId = null;
        if ($request->has('department_id') && $rawDept !== '') {
            $departmentId = ($rawDept === 'all' || (int) $rawDept === 0) ? 0 : (int) $rawDept;
        }

        $data = $this->kpiService->getSectionBurnComparison($user, $date, $departmentId);

        return response()->json($data);
    }
}
