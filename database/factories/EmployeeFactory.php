<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Section;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'npk' => 'EMP-'.fake()->unique()->numerify('#####'),
            'department_id' => Department::factory(),
            'section_id' => function (array $attributes) {
                return Section::factory()->create([
                    'department_id' => $attributes['department_id'],
                ])->id;
            },
            'full_name' => fake()->name(),
            'job_position' => fake()->randomElement([
                'Line Operator',
                'Senior Line Operator',
                'Team Leader',
                'Die & Mold Specialist',
                'Automation Technician',
                'Quality Inspector',
            ]),
            'hourly_rate' => fake()->randomElement([null, 35000.00, 42000.00, 48000.00, 52000.00]),
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the employee is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Set explicit department and section.
     */
    public function forDepartmentAndSection(Department|int $department, Section|int $section): static
    {
        $deptId = $department instanceof Department ? $department->id : $department;
        $secId = $section instanceof Section ? $section->id : $section;

        return $this->state(fn (array $attributes) => [
            'department_id' => $deptId,
            'section_id' => $secId,
        ]);
    }
}
