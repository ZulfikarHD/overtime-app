<?php

namespace Database\Factories;

use App\Models\CapexProject;
use App\Models\Employee;
use App\Models\OvertimeItem;
use App\Models\OvertimeSubmission;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OvertimeItem>
 */
class OvertimeItemFactory extends Factory
{
    protected $model = OvertimeItem::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $hoursProduction = fake()->randomElement([1.0, 1.5, 2.0, 2.5, 3.0]);
        $hoursTpm = fake()->randomElement([0.0, 0.5, 1.0]);
        $hoursOthers = fake()->randomElement([0.0, 0.5]);
        $hourlyRate = fake()->randomElement([35000.00, 42000.00, 45000.00, 50000.00]);
        $totalHours = $hoursProduction + $hoursTpm + $hoursOthers;

        return [
            'overtime_submission_id' => OvertimeSubmission::factory(),
            'employee_id' => Employee::factory(),
            'npk_snapshot' => function (array $attributes) {
                $employee = Employee::find($attributes['employee_id']);

                return $employee?->npk ?? 'EMP-'.fake()->numerify('#####');
            },
            'capex_project_id' => null,
            'hours_production' => $hoursProduction,
            'hours_tpm' => $hoursTpm,
            'hours_project' => 0.00,
            'hours_others' => $hoursOthers,
            'hourly_rate_snapshot' => $hourlyRate,
            'total_cost_snapshot' => round($totalHours * $hourlyRate, 2),
            'rca_category' => fake()->optional(0.6)->randomElement([
                'MACHINE_BREAKDOWN',
                'SUPPLIER_DELAY',
                'QUALITY_REWORK',
                'CUSTOMER_RUSH',
                'TRIAL_MODEL',
                'FACILITY_MAINTENANCE',
                'OTHER',
            ]),
            'rca_notes' => fake()->optional(0.3)->sentence(),
            'task_description' => fake()->optional(0.7)->sentence(),
            'status' => 'PENDING',
            'reviewed_by_user_id' => null,
            'reviewed_at' => null,
            'rejection_reason' => null,
            'lock_version' => 1,
        ];
    }

    /**
     * Pending approval.
     */
    public function pending(): static
    {
        return $this->state(fn () => [
            'status' => 'PENDING',
            'reviewed_by_user_id' => null,
            'reviewed_at' => null,
            'rejection_reason' => null,
        ]);
    }

    /**
     * Approved by a reviewer.
     */
    public function approved(?User $reviewer = null): static
    {
        return $this->state(fn () => [
            'status' => 'APPROVED',
            'reviewed_by_user_id' => $reviewer?->id ?? User::factory(),
            'reviewed_at' => now('Asia/Jakarta'),
            'rejection_reason' => null,
        ]);
    }

    /**
     * Rejected by a reviewer.
     */
    public function rejected(?User $reviewer = null, ?string $reason = null): static
    {
        return $this->state(fn () => [
            'status' => 'REJECTED',
            'reviewed_by_user_id' => $reviewer?->id ?? User::factory(),
            'reviewed_at' => now('Asia/Jakarta'),
            'rejection_reason' => $reason ?? 'Tidak sesuai rencana produksi.',
        ]);
    }

    /**
     * Attribute CapEx project hours (satisfies chk_capex_attribution).
     */
    public function withCapex(?CapexProject $project = null, float $hours = 2.0): static
    {
        return $this->state(function () use ($project, $hours) {
            $hoursProduction = 0.0;
            $hoursTpm = 0.0;
            $hoursOthers = 0.0;
            $hoursProject = max(0.5, $hours);
            $hourlyRate = fake()->randomElement([42000.00, 45000.00, 50000.00]);
            $totalHours = $hoursProject;

            return [
                'capex_project_id' => $project?->id ?? CapexProject::factory(),
                'hours_production' => $hoursProduction,
                'hours_tpm' => $hoursTpm,
                'hours_project' => $hoursProject,
                'hours_others' => $hoursOthers,
                'hourly_rate_snapshot' => $hourlyRate,
                'total_cost_snapshot' => round($totalHours * $hourlyRate, 2),
                'rca_category' => 'TRIAL_MODEL',
                'task_description' => 'Pekerjaan CapEx / instalasi proyek',
            ];
        });
    }

    /**
     * Bind item to a known employee (keeps NPK snapshot in sync).
     */
    public function forEmployee(Employee $employee): static
    {
        $rate = (float) ($employee->hourly_rate ?? 45000);

        return $this->state(fn (array $attributes) => [
            'employee_id' => $employee->id,
            'npk_snapshot' => $employee->npk,
            'hourly_rate_snapshot' => $rate,
            'total_cost_snapshot' => round(
                (
                    (float) ($attributes['hours_production'] ?? 0)
                    + (float) ($attributes['hours_tpm'] ?? 0)
                    + (float) ($attributes['hours_project'] ?? 0)
                    + (float) ($attributes['hours_others'] ?? 0)
                ) * $rate,
                2,
            ),
        ]);
    }
}
