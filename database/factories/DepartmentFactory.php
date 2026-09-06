<?php

namespace Database\Factories;

use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Department>
 */
class DepartmentFactory extends Factory
{
    protected $model = Department::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'DEPT_'.fake()->unique()->regexify('[A-Z]{3,6}'),
            'name' => fake()->company().' Department',
            'cost_center_code' => 'CC-'.fake()->unique()->numerify('###'),
            'default_hourly_rate' => fake()->randomElement([35000.00, 42000.00, 50000.00, 55000.00]),
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the department is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
