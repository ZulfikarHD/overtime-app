<?php

namespace Database\Factories;

use App\Models\ExportLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExportLog>
 */
class ExportLogFactory extends Factory
{
    protected $model = ExportLog::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $format = fake()->randomElement(['xlsx', 'csv', 'pdf']);
        $resource = fake()->randomElement([
            'overtime_approvals',
            'capex_labor',
            'employee_report',
            'budget_burn',
        ]);

        return [
            'actor_user_id' => User::factory(),
            'resource_type' => $resource,
            'format' => $format,
            'filename' => sprintf('%s_export_%s.%s', $resource, now('Asia/Jakarta')->format('Ymd_His'), $format),
            'record_count' => fake()->numberBetween(5, 500),
            'filters' => [
                'fiscal_year' => 2026,
                'fiscal_month' => 9,
            ],
            'ip_address' => fake()->ipv4(),
            'created_at' => now('Asia/Jakarta'),
        ];
    }
}
