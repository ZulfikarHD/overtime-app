<?php

namespace Database\Factories;

use App\Models\MlAnomalyLog;
use App\Models\MlModel;
use App\Models\OvertimeItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MlAnomalyLog>
 */
class MlAnomalyLogFactory extends Factory
{
    protected $model = MlAnomalyLog::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'overtime_item_id' => OvertimeItem::factory(),
            'ml_model_id' => MlModel::factory()->anomalyDetection(),
            'anomaly_score' => fake()->randomFloat(4, 0.55, 0.99),
            'anomaly_reasons' => [
                'hours_spike' => 'Jam lembur jauh di atas rata-rata 14 hari',
                'unusual_rca' => 'Kombinasi RCA jarang terjadi di section ini',
            ],
            'is_dismissed' => false,
            'dismissed_by_user_id' => null,
            'dismissed_at' => null,
            'dismissal_note' => null,
            'created_at' => now('Asia/Jakarta'),
        ];
    }

    /**
     * Dismissed anomaly.
     */
    public function dismissed(?User $actor = null): static
    {
        return $this->state(fn () => [
            'is_dismissed' => true,
            'dismissed_by_user_id' => $actor?->id ?? User::factory(),
            'dismissed_at' => now('Asia/Jakarta'),
            'dismissal_note' => 'Sudah diverifikasi supervisor — false positive.',
        ]);
    }

    /**
     * High anomaly score (≥ 0.8).
     */
    public function highAnomaly(): static
    {
        return $this->state(fn () => [
            'anomaly_score' => fake()->randomFloat(4, 0.80, 0.99),
        ]);
    }
}
