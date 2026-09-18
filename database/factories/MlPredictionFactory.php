<?php

namespace Database\Factories;

use App\Models\MlModel;
use App\Models\MlPrediction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MlPrediction>
 */
class MlPredictionFactory extends Factory
{
    protected $model = MlPrediction::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $predicted = fake()->randomFloat(2, 80, 250);
        $margin = $predicted * 0.1;

        return [
            'ml_model_id' => MlModel::factory(),
            'target_type' => fake()->randomElement(['section', 'department', 'capex_project']),
            'target_id' => fake()->numberBetween(1, 50),
            'prediction_horizon' => fake()->randomElement(['7d', '14d', '30d', 'EOMonth']),
            'predicted_value' => $predicted,
            'confidence_interval_lower' => round($predicted - $margin, 2),
            'confidence_interval_upper' => round($predicted + $margin, 2),
            'risk_score' => fake()->randomFloat(4, 0.1, 0.95),
            'risk_level' => fake()->randomElement(['LOW', 'MEDIUM', 'HIGH']),
            'feature_impact_json' => [
                'recent_burn_velocity' => 0.42,
                'weekend_ot_ratio' => 0.18,
                'capex_share' => 0.12,
            ],
            'fallback_used' => false,
            'created_at' => now('Asia/Jakarta'),
        ];
    }

    /**
     * High-risk prediction.
     */
    public function highRisk(): static
    {
        return $this->state(fn () => [
            'risk_score' => 0.9200,
            'risk_level' => 'HIGH',
        ]);
    }
}
