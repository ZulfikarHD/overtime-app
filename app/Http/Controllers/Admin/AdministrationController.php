<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\User;
use App\Services\PolicyThresholdService;
use App\Services\UserService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdministrationController extends Controller
{
    public function __construct(
        public PolicyThresholdService $policyService,
        public UserService $userService,
    ) {}

    /**
     * Display the unified Administration Hub.
     */
    public function index(Request $request): Response
    {
        $tab = $request->query('tab', 'policies');

        // Policy Thresholds tab data
        $plantDefault = $this->policyService->getPlantDefault();
        $departmentsWithStatus = $this->policyService->getDepartmentsWithStatus();

        $activeDepartments = Department::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        $departmentsWithSections = Department::query()
            ->where('is_active', true)
            ->with(['sections' => function ($q) {
                $q->where('is_active', true)->orderBy('name');
            }])
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        $overridesCount = $departmentsWithStatus->where('has_override', true)->count();
        $inheritedCount = $departmentsWithStatus->where('has_override', false)->count();

        // User Accounts tab data
        $userSearch = $request->query('user_search');
        $userRole = $request->query('user_role', 'all');
        $userStatus = $request->query('user_status', 'all');
        $userDept = $request->query('user_department_id');

        $users = $this->userService->getPaginatedUsers([
            'search' => $userSearch,
            'role' => $userRole,
            'status' => $userStatus,
            'department_id' => $userDept,
        ], 25)->through(function (User $u) use ($request) {
            $isSelf = $request->user()?->id === $u->id;
            $hasRelations = ($u->overtime_submissions_count ?? 0) > 0
                || ($u->reviewed_overtime_items_count ?? 0) > 0
                || ($u->attached_spkl_documents_count ?? 0) > 0;

            $roleEnum = $u->role instanceof UserRole ? $u->role : UserRole::from((string) $u->role);

            return [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $roleEnum->value,
                'role_label' => $roleEnum->label(),
                'role_badge_color' => $roleEnum->badgeColor(),
                'npk' => $u->npk,
                'department_id' => $u->department_id,
                'section_id' => $u->section_id,
                'is_active' => (bool) $u->is_active,
                'last_login_at' => $u->last_login_at?->timezone('Asia/Jakarta')->format('d/m/Y H:i').' WIB',
                'last_login_raw' => $u->last_login_at?->toIso8601String(),
                'department' => $u->department ? [
                    'id' => $u->department->id,
                    'code' => $u->department->code,
                    'name' => $u->department->name,
                ] : null,
                'section' => $u->section ? [
                    'id' => $u->section->id,
                    'code' => $u->section->code,
                    'name' => $u->section->name,
                    'department_id' => $u->section->department_id,
                ] : null,
                'is_self' => $isSelf,
                'can_delete' => ! $isSelf && ! $hasRelations,
                'has_relations' => $hasRelations,
            ];
        });

        $userStats = $this->userService->getUserStats();

        $availableRoles = collect(UserRole::cases())->map(fn (UserRole $role) => [
            'value' => $role->value,
            'label' => $role->label(),
            'badgeColor' => $role->badgeColor(),
        ]);

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
            'departmentsWithSections' => $departmentsWithSections,
            'policyStats' => [
                'total_departments' => $departmentsWithStatus->count(),
                'overrides_count' => $overridesCount,
                'inherited_count' => $inheritedCount,
            ],
            'users' => $users,
            'userStats' => $userStats,
            'userFilters' => [
                'user_search' => $userSearch ?? '',
                'user_role' => $userRole,
                'user_status' => $userStatus,
                'user_department_id' => $userDept ? (int) $userDept : null,
            ],
            'availableRoles' => $availableRoles,
        ]);
    }
}
