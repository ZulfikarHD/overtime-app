<?php

namespace Database\Factories;

use App\Models\MlModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MlModel>
 */
class MlModelFactory extends Factory
{
    protected $model = MlModel::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = fake()->randomElement([
            'DEMAND_FORECAST',
            'BURN_TRAJECTORY',
            'CAPEX_FORECAST',
            'ANOMALY_DETECTION',
        ]);

        return [
            'model_key' => strtolower($type).'_'.fake()->unique()->numerify('v###'),
            'model_type' => $type,
            'version' => '1.'.fake()->numberBetween(0, 9).'.0',
            'algorithm_name' => fake()->randomElement([
                'IsolationForest',
                'Prophet',
                'XGBoostRegressor',
                'RandomForestClassifier',
            ]),
            'hyperparameters' => [
                'n_estimators' => 100,
                'contamination' => 0.05,
            ],
            'metrics' => [
                'precision' => 0.86,
                'recall' => 0.79,
                'f1' => 0.82,
            ],
            'is_active' => false,
            'trained_at' => now('Asia/Jakarta')->subDays(fake()->numberBetween(1, 30)),
        ];
    }

    /**
     * Mark model as the active deployment.
     */
    public function active(): static
    {
        return $this->state(fn () => [
            'is_active' => true,
        ]);
    }

    /**
     * Anomaly detection model type.
     */
    public function anomalyDetection(): static
    {
        return $this->state(fn () => [
            'model_key' => 'anomaly_detection_'.fake()->unique()->numerify('v###'),
            'model_type' => 'ANOMALY_DETECTION',
            'algorithm_name' => 'IsolationForest',
        ]);
    }
}
