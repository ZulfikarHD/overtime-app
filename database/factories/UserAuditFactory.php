<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\UserAudit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserAudit>
 */
class UserAuditFactory extends Factory
{
    protected $model = UserAudit::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'actor_user_id' => User::factory()->admin(),
            'action' => fake()->randomElement([
                'CREATED',
                'UPDATED',
                'ROLE_CHANGED',
                'ACTIVATED',
                'DEACTIVATED',
                'PASSWORD_RESET',
            ]),
            'previous_role' => null,
            'new_role' => UserRole::User->value,
            'details' => ['source' => 'factory'],
            'ip_address' => fake()->ipv4(),
            'created_at' => now('Asia/Jakarta'),
        ];
    }

    /**
     * Role-change audit entry.
     */
    public function roleChanged(UserRole $from, UserRole $to): static
    {
        return $this->state(fn () => [
            'action' => 'ROLE_CHANGED',
            'previous_role' => $from->value,
            'new_role' => $to->value,
            'details' => [
                'from' => $from->value,
                'to' => $to->value,
            ],
        ]);
    }
}
