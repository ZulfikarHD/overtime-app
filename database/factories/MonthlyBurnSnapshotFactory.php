<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\MonthlyBurnSnapshot;
use App\Models\Section;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<MonthlyBurnSnapshot>
 */
class MonthlyBurnSnapshotFactory extends Factory
{
    protected $model = MonthlyBurnSnapshot::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $now = Carbon::now('Asia/Jakarta');
        $planned = 200.00;
        $actual = fake()->randomFloat(2, 40, 180);
        $capex = fake()->randomFloat(2, 0, min(40, $actual));
        $opex = round($actual - $capex, 2);
        $burnIndex = $planned > 0 ? round(($actual / $planned) * 100, 2) : 0.00;
        $velocity = round($actual / max(1, (int) $now->weekOfMonth), 2);

        return [
            'department_id' => Department::factory(),
            'section_id' => function (array $attributes) {
                return Section::factory()->create([
                    'department_id' => $attributes['department_id'],
                ])->id;
            },
            'fiscal_year' => (int) $now->year,
            'fiscal_month' => (int) $now->month,
            'planned_budget_hours' => $planned,
            'cumulative_actual_hours' => $actual,
            'cumulative_opex_hours' => $opex,
            'cumulative_capex_hours' => $capex,
            'burn_index_pct' => $burnIndex,
            'burn_velocity' => $velocity,
            'burn_zone' => $this->resolveBurnZone($burnIndex),
            'warned_at' => $burnIndex >= 100 ? $now->copy()->subDays(2) : null,
            'danger_at' => $burnIndex >= 115 ? $now->copy()->subDay() : null,
            'last_recalculated_at' => $now,
        ];
    }

    /**
     * Bind snapshot to an existing section.
     */
    public function forSection(Section $section): static
    {
        return $this->state(fn () => [
            'department_id' => $section->department_id,
            'section_id' => $section->id,
        ]);
    }

    /**
     * Force a warning burn zone.
     */
    public function warning(): static
    {
        return $this->state(fn () => [
            'planned_budget_hours' => 200.00,
            'cumulative_actual_hours' => 210.00,
            'cumulative_opex_hours' => 180.00,
            'cumulative_capex_hours' => 30.00,
            'burn_index_pct' => 105.00,
            'burn_velocity' => 52.50,
            'burn_zone' => 'ZONE_3_WARNING',
            'warned_at' => now('Asia/Jakarta')->subDays(1),
            'danger_at' => null,
        ]);
    }

    /**
     * Force a danger / poor burn zone.
     */
    public function danger(): static
    {
        return $this->state(fn () => [
            'planned_budget_hours' => 200.00,
            'cumulative_actual_hours' => 240.00,
            'cumulative_opex_hours' => 200.00,
            'cumulative_capex_hours' => 40.00,
            'burn_index_pct' => 120.00,
            'burn_velocity' => 60.00,
            'burn_zone' => 'ZONE_4_POOR',
            'warned_at' => now('Asia/Jakarta')->subDays(3),
            'danger_at' => now('Asia/Jakarta')->subDay(),
        ]);
    }

    protected function resolveBurnZone(float $burnIndex): string
    {
        return match (true) {
            $burnIndex < 80 => 'ZONE_1_EXCELLENT',
            $burnIndex < 100 => 'ZONE_2_GOOD',
            $burnIndex < 115 => 'ZONE_3_WARNING',
            default => 'ZONE_4_POOR',
        };
    }
}
