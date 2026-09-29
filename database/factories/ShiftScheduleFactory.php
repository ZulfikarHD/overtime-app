<?php

namespace Database\Factories;

use App\Models\ShiftSchedule;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShiftSchedule>
 */
class ShiftScheduleFactory extends Factory
{
    protected $model = ShiftSchedule::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Standard Shift',
            'effective_from' => now('Asia/Jakarta')->startOfYear()->toDateString(),
            'effective_until' => null,
            'shift_1_start' => '07:00',
            'shift_1_end' => '15:00',
            'shift_2_start' => '15:00',
            'shift_2_end' => '23:00',
            'shift_3_start' => '23:00',
            'shift_3_end' => '07:00',
            'created_by' => null,
        ];
    }

    public function createdBy(User|int $user): static
    {
        return $this->state(fn (array $attributes) => [
            'created_by' => $user instanceof User ? $user->id : $user,
        ]);
    }

    /**
     * Closed period ending before today.
     */
    public function historical(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Previous Period',
            'effective_from' => now('Asia/Jakarta')->subYear()->startOfYear()->toDateString(),
            'effective_until' => now('Asia/Jakarta')->subYear()->endOfYear()->toDateString(),
        ]);
    }
}
