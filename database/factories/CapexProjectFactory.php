<?php

namespace Database\Factories;

use App\Models\CapexProject;
use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<CapexProject>
 */
class CapexProjectFactory extends Factory
{
    protected $model = CapexProject::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $year = (int) Carbon::now('Asia/Jakarta')->format('Y');
        $randomSeq = fake()->unique()->numberBetween(100, 999);
        $randomDept = fake()->randomElement(['ASSY', 'WELD', 'STP', 'ENG', 'PAINT']);

        return [
            'project_code' => "CPX-{$year}-{$randomDept}-{$randomSeq}",
            'asset_code' => 'AST-'.fake()->numerify('####'),
            'name' => fake()->randomElement([
                'Pemasangan Lini Robot Welding',
                'Otomasi Feeder Robot Assy',
                'Instalasi Jig Stamping Press 500T',
                'Retrofit Overhead Crane Workshop',
                'Upgrading Sealer Robot Line 2',
            ]).' '.$randomSeq,
            'department_id' => Department::factory(),
            'allocated_labor_hours' => fake()->randomElement([200.0, 350.0, 500.0, 800.0]),
            'allocated_labor_budget_idr' => fake()->randomElement([15000000.0, 25000000.0, 35000000.0, 50000000.0]),
            'physical_progress_pct' => 0.00,
            'status' => 'ACTIVE',
            'start_date' => Carbon::now('Asia/Jakarta')->startOfMonth()->toDateString(),
            'target_end_date' => Carbon::now('Asia/Jakarta')->addMonths(3)->endOfMonth()->toDateString(),
        ];
    }

    /**
     * Set status to PLANNING.
     */
    public function planning(): static
    {
        return $this->state(fn () => [
            'status' => 'PLANNING',
            'physical_progress_pct' => 0.00,
        ]);
    }

    /**
     * Set status to ACTIVE.
     */
    public function active(): static
    {
        return $this->state(fn () => [
            'status' => 'ACTIVE',
        ]);
    }

    /**
     * Set status to ON_HOLD.
     */
    public function onHold(): static
    {
        return $this->state(fn () => [
            'status' => 'ON_HOLD',
        ]);
    }

    /**
     * Set status to COMPLETED.
     */
    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => 'COMPLETED',
            'physical_progress_pct' => 100.00,
        ]);
    }

    /**
     * Set status to CLOSED.
     */
    public function closed(): static
    {
        return $this->state(fn () => [
            'status' => 'CLOSED',
        ]);
    }
}
