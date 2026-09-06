<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\PolicyThreshold;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PolicyThreshold>
 */
class PolicyThresholdFactory extends Factory
{
    protected $model = PolicyThreshold::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'department_id' => null,
            'weekly_soft_limit_hours' => 20.0,
            'consecutive_weeks_alert' => 3,
            'spkl_grace_period_days' => 2,
            'burn_warning_pct' => 100.00,
            'burn_danger_pct' => 115.00,
        ];
    }

    /**
     * Indicate that the threshold is plant-level default.
     */
    public function plantDefault(): static
    {
        return $this->state(fn (array $attributes) => [
            'department_id' => null,
        ]);
    }

    /**
     * Indicate that the threshold is for a specific department.
     */
    public function forDepartment(Department|int $department): static
    {
        return $this->state(fn (array $attributes) => [
            'department_id' => $department instanceof Department ? $department->id : $department,
        ]);
    }
}
