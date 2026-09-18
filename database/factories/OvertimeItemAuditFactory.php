<?php

namespace Database\Factories;

use App\Models\OvertimeItem;
use App\Models\OvertimeItemAudit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OvertimeItemAudit>
 */
class OvertimeItemAuditFactory extends Factory
{
    protected $model = OvertimeItemAudit::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'overtime_item_id' => OvertimeItem::factory(),
            'action' => fake()->randomElement(['CREATED', 'UPDATED', 'APPROVED', 'REJECTED']),
            'actor_user_id' => User::factory(),
            'previous_state' => null,
            'new_state' => [
                'status' => 'PENDING',
                'total_hours' => 2.00,
            ],
            'notes' => fake()->optional(0.4)->sentence(),
            'ip_address' => fake()->ipv4(),
            'created_at' => now('Asia/Jakarta'),
        ];
    }

    /**
     * Audit for item creation.
     */
    public function createdAction(): static
    {
        return $this->state(fn () => [
            'action' => 'CREATED',
            'previous_state' => null,
            'new_state' => ['status' => 'PENDING'],
            'notes' => 'Item lembur dibuat',
        ]);
    }

    /**
     * Audit for approval.
     */
    public function approvedAction(): static
    {
        return $this->state(fn () => [
            'action' => 'APPROVED',
            'previous_state' => ['status' => 'PENDING'],
            'new_state' => ['status' => 'APPROVED'],
            'notes' => 'Item lembur disetujui',
        ]);
    }

    /**
     * Audit for rejection.
     */
    public function rejectedAction(): static
    {
        return $this->state(fn () => [
            'action' => 'REJECTED',
            'previous_state' => ['status' => 'PENDING'],
            'new_state' => ['status' => 'REJECTED'],
            'notes' => 'Item lembur ditolak',
        ]);
    }
}
