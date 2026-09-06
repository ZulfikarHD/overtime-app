<?php

namespace App\Services;

use App\Models\Section;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class SectionService
{
    /**
     * Create a new section under a department.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Section
    {
        $data['code'] = strtoupper(trim((string) $data['code']));

        return Section::create([
            'department_id' => (int) $data['department_id'],
            'code' => $data['code'],
            'name' => trim((string) $data['name']),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);
    }

    /**
     * Update an existing section.
     * Note: 'code' and 'department_id' are immutable per system integrity rules.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Section $section, array $data): Section
    {
        $isActive = array_key_exists('is_active', $data) ? (bool) $data['is_active'] : $section->is_active;

        $section->update([
            'name' => trim((string) $data['name']),
            'is_active' => $isActive,
        ]);

        return $section;
    }

    /**
     * Determine whether the section can be safely deleted.
     *
     * @return array{allowed: bool, reason: string|null}
     */
    public function canDelete(Section $section): array
    {
        $employeesCount = $section->employees()->count();
        if ($employeesCount > 0) {
            return [
                'allowed' => false,
                'reason' => __('Cannot delete section because it still has :count registered employee(s). Reassign employees first.', ['count' => $employeesCount]),
            ];
        }

        $submissionsCount = $section->overtimeSubmissions()->count();
        if ($submissionsCount > 0) {
            return [
                'allowed' => false,
                'reason' => __('Cannot delete section because it has :count linked overtime submission record(s). Deactivate instead to preserve historical integrity.', ['count' => $submissionsCount]),
            ];
        }

        $budgetsCount = $section->overtimeBudgets()->count();
        if ($budgetsCount > 0) {
            return [
                'allowed' => false,
                'reason' => __('Cannot delete section because it has :count linked budget planning record(s). Deactivate instead.', ['count' => $budgetsCount]),
            ];
        }

        $usersCount = User::where('section_id', $section->id)->count();
        if ($usersCount > 0) {
            return [
                'allowed' => false,
                'reason' => __('Cannot delete section because it is assigned to :count user account(s).', ['count' => $usersCount]),
            ];
        }

        return [
            'allowed' => true,
            'reason' => null,
        ];
    }

    /**
     * Delete the section if integrity constraints allow.
     *
     * @throws ValidationException
     */
    public function delete(Section $section): void
    {
        $check = $this->canDelete($section);

        if (! $check['allowed']) {
            throw ValidationException::withMessages([
                'delete' => [$check['reason'] ?? __('Section cannot be deleted.')],
            ]);
        }

        $section->delete();
    }
}
