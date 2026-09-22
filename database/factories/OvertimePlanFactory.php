<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\OvertimePlan;
use App\Models\Section;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<OvertimePlan>
 */
class OvertimePlanFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $now = Carbon::now('Asia/Jakarta');

        return [
            'section_id' => Section::factory(),
            'department_id' => Department::factory(),
            'fiscal_year' => $now->year,
            'fiscal_month' => $now->month,
            'plan_code' => 'PLN-'.strtoupper(fake()->bothify('??##-####')),
            'status' => 'DRAFT',
            'submitted_by_user_id' => User::factory(),
            'notes' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(['status' => 'PUBLISHED']);
    }
}
