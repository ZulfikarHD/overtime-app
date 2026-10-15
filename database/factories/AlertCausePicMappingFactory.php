<?php

namespace Database\Factories;

use App\Enums\AlertCause;
use App\Models\AlertCausePicMapping;
use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AlertCausePicMapping>
 */
class AlertCausePicMappingFactory extends Factory
{
    protected $model = AlertCausePicMapping::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cause' => AlertCause::CategoryDominant,
            'pic_department_id' => Department::factory(),
            'is_active' => true,
        ];
    }

    /**
     * Map the given cause (one row per cause: the column is unique).
     */
    public function forCause(AlertCause $cause): static
    {
        return $this->state(fn (array $attributes) => ['cause' => $cause]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
