<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\OvertimeBudget;
use App\Models\Section;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OvertimeBudget>
 */
class OvertimeBudgetFactory extends Factory
{
    protected $model = OvertimeBudget::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $plannedHours = 120.00;
        $weekly = round($plannedHours / 4.3, 2);

        // Realistic production-floor category distribution (A=Produksi, B=TPM, C=Project, D=Others)
        $prodHours = round($plannedHours * 0.55, 2);
        $tpmHours = round($plannedHours * 0.05, 2);
        $projectHours = round($plannedHours * 0.25, 2);
        $othersHours = round($plannedHours - $prodHours - $tpmHours - $projectHours, 2);

        return [
            'department_id' => Department::factory(),
            'section_id' => null,
            'fiscal_year' => 2026,
            'fiscal_month' => 9,
            'planned_hours' => $plannedHours,
            'planned_cost_idr' => round($plannedHours * 25000, 2),
            'planned_production_hours' => $prodHours,
            'planned_tpm_hours' => $tpmHours,
            'planned_project_hours' => $projectHours,
            'planned_others_hours' => $othersHours,
            'week1_planned_hours' => $weekly,
            'week2_planned_hours' => $weekly,
            'week3_planned_hours' => $weekly,
            'week4_planned_hours' => $weekly,
            'week5_planned_hours' => round($plannedHours - ($weekly * 4), 2),
        ];
    }

    /**
     * Indicate budget is for a specific section.
     */
    public function forSection(Section $section): static
    {
        return $this->state(fn (array $attributes) => [
            'department_id' => $section->department_id,
            'section_id' => $section->id,
        ]);
    }
}
