<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Analytics\ScenarioCalculatorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyticsScenarioController extends Controller
{
    public function __construct(
        public ScenarioCalculatorService $scenarioService,
    ) {}

    /**
     * Retrieve scenario simulation initial dataset (Story E09-10).
     */
    public function index(Request $request): JsonResponse
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

        $data = $this->scenarioService->getScenarioData($user, $departmentId, $startDate, $endDate);

        return response()->json($data);
    }

    /**
     * Run scenario simulation computation (Production Planning or Scenario Builder).
     */
    public function calculate(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($user && ($user->isAdmin() || $user->isManager()), 403);

        $type = $request->input('type', 'builder');

        if ($type === 'planning') {
            $validated = $request->validate([
                'type' => ['required', 'string', 'in:planning,builder'],
                'target_volume' => ['required', 'numeric', 'min:1', 'max:50000'],
                'period' => ['required', 'string', 'in:weekly,monthly,quarterly'],
                'section_id' => ['required', 'integer'],
            ]);

            $result = $this->scenarioService->calculateProductionPlanning($validated, $user);

            return response()->json([
                'status' => 'success',
                'type' => 'planning',
                'data' => $result,
            ]);
        }

        // Scenario Builder calculation
        $validated = $request->validate([
            'type' => ['sometimes', 'string', 'in:planning,builder'],
            'overtime_change_pct' => ['required', 'numeric', 'min:-50', 'max:50'],
            'budget_allocation' => ['nullable', 'numeric', 'min:0'],
            'department_id' => ['nullable'],
        ]);

        $result = $this->scenarioService->calculateScenarioBuilder($validated, $user);

        return response()->json([
            'status' => 'success',
            'type' => 'builder',
            'data' => $result,
        ]);
    }

    /**
     * Save a scenario configuration to user preferences.
     */
    public function save(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($user && ($user->isAdmin() || $user->isManager()), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'department_id' => ['nullable'],
            'overtime_change_pct' => ['required', 'numeric', 'min:-50', 'max:50'],
            'budget_allocation' => ['nullable', 'numeric', 'min:0'],
            'projected_hours' => ['required', 'numeric', 'min:0'],
            'projected_cost' => ['required', 'numeric', 'min:0'],
            'projected_burn_index' => ['required', 'numeric'],
            'burn_zone' => ['nullable', 'string', 'in:safe,on_track,warning,danger'],
            'safety_risk_score' => ['required', 'numeric', 'min:0', 'max:100'],
            'production_volume_impact_pct' => ['required', 'numeric'],
        ]);

        $saved = $this->scenarioService->saveScenario($user, $validated);

        return response()->json([
            'status' => 'success',
            'message' => __('Skenario berhasil disimpan.'),
            'saved_scenarios' => $saved,
        ]);
    }

    /**
     * Delete a saved scenario from user preferences.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($user && ($user->isAdmin() || $user->isManager()), 403);

        $saved = $this->scenarioService->deleteScenario($user, $id);

        return response()->json([
            'status' => 'success',
            'message' => __('Skenario berhasil dihapus.'),
            'saved_scenarios' => $saved,
        ]);
    }
}
