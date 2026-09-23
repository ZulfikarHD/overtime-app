<?php

namespace Database\Factories;

use App\Models\MlForecastInput;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MlForecastInput>
 */
class MlForecastInputFactory extends Factory
{
    public function definition(): array
    {
        static $sequence = 0;
        $sequence++;

        // Use a far-future year range so forecast rows don't clash with training data year-months
        $baseYear = 2030;
        $offset = ($sequence - 1) % 24;
        $year = $baseYear + intdiv($offset, 12);
        $month = ($offset % 12) + 1;

        return [
            'year' => $year,
            'month' => $month,
            'working_days' => $this->faker->numberBetween(16, 23),
            'production_volume' => $this->faker->numberBetween(1800, 4000),
            'man_power' => $this->faker->numberBetween(650, 900),
            'actual_overtime_index' => null,
        ];
    }
}
