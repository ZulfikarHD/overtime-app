<?php

namespace Database\Factories;

use App\Models\MlTrainingData;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MlTrainingData>
 */
class MlTrainingDataFactory extends Factory
{
    public function definition(): array
    {
        static $sequence = 0;
        $sequence++;

        // Spread rows across different year-month to avoid unique constraint hits by default
        $baseYear = 2024;
        $offset = ($sequence - 1) % 24; // up to 24 distinct months
        $year = $baseYear + intdiv($offset, 12);
        $month = ($offset % 12) + 1;

        return [
            'year' => $year,
            'month' => $month,
            'working_days' => $this->faker->numberBetween(16, 23),
            'production_volume' => $this->faker->numberBetween(1800, 4000),
            'man_power' => $this->faker->numberBetween(650, 900),
            'overtime_index' => $this->faker->numberBetween(25000, 110000),
        ];
    }
}
