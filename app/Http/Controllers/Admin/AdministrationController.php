<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Services\PolicyThresholdService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdministrationController extends Controller
{
    public function __construct(
        public PolicyThresholdService $policyService,
    ) {}

    /**
     * Display the unified Administration Hub.
     */
    public function index(Request $request): Response
    {
        $tab = $request->query('tab', 'policies');

        $plantDefault = $this->policyService->getPlantDefault();
        $departmentsWithStatus = $this->policyService->getDepartmentsWithStatus();

        $activeDepartments = Department::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        $overridesCount = $departmentsWithStatus->where('has_override', true)->count();
        $inheritedCount = $departmentsWithStatus->where('has_override', false)->count();

        return Inertia::render('admin/Administration', [
            'activeTab' => $tab,
            'plantDefault' => [
                'id' => $plantDefault->id,
                'weekly_soft_limit_hours' => (float) $plantDefault->weekly_soft_limit_hours,
                'consecutive_weeks_alert' => (int) $plantDefault->consecutive_weeks_alert,
                'spkl_grace_period_days' => (int) $plantDefault->spkl_grace_period_days,
                'burn_warning_pct' => (float) $plantDefault->burn_warning_pct,
                'burn_danger_pct' => (float) $plantDefault->burn_danger_pct,
                'updated_at' => $plantDefault->updated_at?->toIso8601String(),
            ],
            'departments' => $departmentsWithStatus->values(),
            'availableDepartments' => $activeDepartments,
            'policyStats' => [
                'total_departments' => $departmentsWithStatus->count(),
                'overrides_count' => $overridesCount,
                'inherited_count' => $inheritedCount,
            ],
        ]);
    }
}
