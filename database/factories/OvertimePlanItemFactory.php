<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\OvertimePlan;
use App\Models\OvertimePlanItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OvertimePlanItem>
 */
class OvertimePlanItemFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $employee = Employee::factory()->make();

        return [
            'overtime_plan_id' => OvertimePlan::factory(),
            'employee_id' => Employee::factory(),
            'npk_snapshot' => fake()->numerify('#####'),
            'plan_date' => fake()->dateTimeBetween('first day of this month', 'last day of this month')->format('Y-m-d'),
            'day_type' => fake()->randomElement(['HKN', 'HLR']),
            'hours_production' => fake()->randomFloat(2, 0, 4),
            'hours_tpm' => 0.00,
            'hours_project' => 0.00,
            'hours_others' => fake()->randomFloat(2, 0, 2),
        ];
    }
}
