<?php

namespace App\Services;

use App\Models\Department;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class DepartmentService
{
    /**
     * Create a new department.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Department
    {
        $data['code'] = strtoupper(trim((string) $data['code']));

        return Department::create([
            'code' => $data['code'],
            'name' => trim((string) $data['name']),
            'cost_center_code' => strtoupper(trim((string) $data['cost_center_code'])),
            'default_hourly_rate' => $data['default_hourly_rate'] ?? 0.00,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);
    }

    /**
     * Update an existing department.
     * Note: 'code' is immutable per Business Rule BR-03 and cannot be updated.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function update(Department $department, array $data): Department
    {
        $isActive = array_key_exists('is_active', $data) ? (bool) $data['is_active'] : $department->is_active;

        if ($department->is_active && ! $isActive) {
            $deactivateCheck = $this->canDeactivate($department);
            if (! $deactivateCheck['allowed']) {
                throw ValidationException::withMessages([
                    'is_active' => [$deactivateCheck['reason'] ?? __('Department cannot be deactivated.')],
                ]);
            }
        }

        $department->update([
            'name' => trim((string) $data['name']),
            'cost_center_code' => strtoupper(trim((string) $data['cost_center_code'])),
            'default_hourly_rate' => $data['default_hourly_rate'] ?? $department->default_hourly_rate,
            'is_active' => $isActive,
        ]);

        return $department;
    }

    /**
     * Determine whether the department can be deactivated.
     *
     * @return array{allowed: bool, reason: string|null}
     */
    public function canDeactivate(Department $department): array
    {
        $activeSectionsCount = $department->sections()->where('is_active', true)->count();
        if ($activeSectionsCount > 0) {
            return [
                'allowed' => false,
                'reason' => __('Cannot deactivate department because it still has :count active section(s). Deactivate child sections first.', ['count' => $activeSectionsCount]),
            ];
        }

        return [
            'allowed' => true,
            'reason' => null,
        ];
    }

    /**
     * Determine whether the department can be safely deleted.
     *
     * @return array{allowed: bool, reason: string|null}
     */
    public function canDelete(Department $department): array
    {
        $sectionsCount = $department->sections()->count();
        if ($sectionsCount > 0) {
            return [
                'allowed' => false,
                'reason' => __('Cannot delete department because it contains :count section(s). Remove or reassign sections first.', ['count' => $sectionsCount]),
            ];
        }

        $employeesCount = $department->employees()->count();
        if ($employeesCount > 0) {
            return [
                'allowed' => false,
                'reason' => __('Cannot delete department because it still has :count registered employee(s). Reassign employees first.', ['count' => $employeesCount]),
            ];
        }

        $submissionsCount = $department->overtimeSubmissions()->count();
        if ($submissionsCount > 0) {
            return [
                'allowed' => false,
                'reason' => __('Cannot delete department because it has :count linked overtime submission record(s). Deactivate instead to preserve historical integrity.', ['count' => $submissionsCount]),
            ];
        }

        $budgetsCount = $department->overtimeBudgets()->count();
        if ($budgetsCount > 0) {
            return [
                'allowed' => false,
                'reason' => __('Cannot delete department because it has :count linked budget planning record(s). Deactivate instead.', ['count' => $budgetsCount]),
            ];
        }

        $usersCount = User::where('department_id', $department->id)->count();
        if ($usersCount > 0) {
            return [
                'allowed' => false,
                'reason' => __('Cannot delete department because it is assigned to :count user account(s).', ['count' => $usersCount]),
            ];
        }

        return [
            'allowed' => true,
            'reason' => null,
        ];
    }

    /**
     * Delete the department if integrity constraints allow.
     *
     * @throws ValidationException
     */
    public function delete(Department $department): void
    {
        $check = $this->canDelete($department);

        if (! $check['allowed']) {
            throw ValidationException::withMessages([
                'delete' => [$check['reason'] ?? __('Department cannot be deleted.')],
            ]);
        }

        $department->delete();
    }
}
