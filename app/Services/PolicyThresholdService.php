<?php

namespace App\Services;

use App\Models\Department;
use App\Models\PolicyThreshold;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class PolicyThresholdService
{
    /**
     * Retrieve the plant-wide default policy threshold.
     * If not found, initializes and returns standard defaults.
     */
    public function getPlantDefault(): PolicyThreshold
    {
        return PolicyThreshold::firstOrCreate(
            ['department_id' => null],
            [
                'weekly_soft_limit_hours' => 20.0,
                'consecutive_weeks_alert' => 3,
                'spkl_grace_period_days' => 2,
                'burn_warning_pct' => 100.00,
                'burn_danger_pct' => 115.00,
            ]
        );
    }

    /**
     * Retrieve the effective policy threshold for a given department.
     * Fallback logic: returns department-specific override if present,
     * otherwise falls back transparently to plant-wide default.
     */
    public function getForDepartment(?int $departmentId): PolicyThreshold
    {
        if ($departmentId !== null) {
            $override = PolicyThreshold::forDepartment($departmentId)->first();
            if ($override !== null) {
                return $override;
            }
        }

        return $this->getPlantDefault();
    }

    /**
     * Get summary of all departments with their current effective threshold
     * and override status (Standar Pabrik vs Kustom / Override Aktif).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function getDepartmentsWithStatus(): Collection
    {
        $plantDefault = $this->getPlantDefault();
        $departments = Department::query()->orderBy('name')->get();
        $overrides = PolicyThreshold::whereNotNull('department_id')
            ->get()
            ->keyBy('department_id');

        return $departments->map(function (Department $dept) use ($plantDefault, $overrides) {
            $hasOverride = $overrides->has($dept->id);
            /** @var PolicyThreshold $effectiveThreshold */
            $effectiveThreshold = $hasOverride ? $overrides->get($dept->id) : $plantDefault;

            return [
                'department_id' => $dept->id,
                'department_code' => $dept->code,
                'department_name' => $dept->name,
                'is_active' => $dept->is_active,
                'has_override' => $hasOverride,
                'override_id' => $hasOverride ? $effectiveThreshold->id : null,
                'weekly_soft_limit_hours' => (float) $effectiveThreshold->weekly_soft_limit_hours,
                'consecutive_weeks_alert' => (int) $effectiveThreshold->consecutive_weeks_alert,
                'spkl_grace_period_days' => (int) $effectiveThreshold->spkl_grace_period_days,
                'burn_warning_pct' => (float) $effectiveThreshold->burn_warning_pct,
                'burn_danger_pct' => (float) $effectiveThreshold->burn_danger_pct,
                'updated_at' => $effectiveThreshold->updated_at?->toIso8601String(),
            ];
        });
    }

    /**
     * Update or create the plant-wide default policy threshold.
     *
     * @param  array<string, mixed>  $data
     */
    public function updatePlantDefault(array $data): PolicyThreshold
    {
        $plantDefault = $this->getPlantDefault();

        $plantDefault->update([
            'weekly_soft_limit_hours' => $data['weekly_soft_limit_hours'],
            'consecutive_weeks_alert' => $data['consecutive_weeks_alert'],
            'spkl_grace_period_days' => $data['spkl_grace_period_days'],
            'burn_warning_pct' => $data['burn_warning_pct'],
            'burn_danger_pct' => $data['burn_danger_pct'],
        ]);

        return $plantDefault;
    }

    /**
     * Save or update a department-specific threshold override.
     *
     * @param  array<string, mixed>  $data
     */
    public function saveOverride(int $departmentId, array $data): PolicyThreshold
    {
        return PolicyThreshold::updateOrCreate(
            ['department_id' => $departmentId],
            [
                'weekly_soft_limit_hours' => $data['weekly_soft_limit_hours'],
                'consecutive_weeks_alert' => $data['consecutive_weeks_alert'],
                'spkl_grace_period_days' => $data['spkl_grace_period_days'],
                'burn_warning_pct' => $data['burn_warning_pct'],
                'burn_danger_pct' => $data['burn_danger_pct'],
            ]
        );
    }

    /**
     * Delete a department override. The plant-wide default cannot be deleted.
     *
     * @throws ValidationException
     */
    public function deleteOverride(PolicyThreshold $threshold): void
    {
        if ($threshold->department_id === null) {
            throw ValidationException::withMessages([
                'policy_threshold' => [__('The plant-wide default policy threshold cannot be deleted.')],
            ]);
        }

        $threshold->delete();
    }
}
