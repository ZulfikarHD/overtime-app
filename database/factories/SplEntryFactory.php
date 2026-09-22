<?php

namespace Database\Factories;

use App\Models\SplEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SplEntry>
 */
class SplEntryFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $start = fake()->randomElement(['07:30', '16:30', '05:08']);
        $end = fake()->randomElement(['15:45', '20:00', '07:08']);

        return [
            'employee_id' => null,
            'npk_snapshot' => fake()->numerify('#####'),
            'employee_name_snapshot' => strtoupper(fake()->name()),
            'section_id' => null,
            'department_id' => null,
            'realization_date' => fake()->dateTimeBetween('first day of this month', 'last day of this month')->format('Y-m-d'),
            'day_type' => fake()->randomElement(['HKN', 'HLR']),
            'start_time' => $start,
            'end_time' => $end,
            'total_hours' => fake()->randomFloat(2, 1.0, 8.0),
            'jenis_pekerjaan' => fake()->sentence(4),
            'type_ot_code' => fake()->randomElement([61, 62, 65, 66, 67, 68, 63]),
            'keterangan_lembur' => fake()->optional()->sentence(),
            'description' => null,
            'action' => null,
            'imported_by_user_id' => User::factory(),
        ];
    }
}
