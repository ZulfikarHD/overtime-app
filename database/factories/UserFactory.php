<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\Department;
use App\Models\Section;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'role' => UserRole::User,
            'npk' => 'NPK-'.fake()->unique()->numerify('#####'),
            'department_id' => null,
            'section_id' => null,
            'is_active' => true,
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ];
    }

    /**
     * Indicate that the user is an Administrator.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Admin,
        ]);
    }

    /**
     * Indicate that the user is a Manager.
     */
    public function manager(?int $departmentId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Manager,
            'department_id' => $departmentId,
        ]);
    }

    /**
     * Indicate that the user is a Team Leader.
     */
    public function teamLeader(?int $sectionId = null, ?int $departmentId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::TeamLeader,
            'department_id' => $departmentId,
            'section_id' => $sectionId,
        ]);
    }

    /**
     * Indicate that the user is a regular operator / user.
     */
    public function user(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::User,
        ]);
    }

    /**
     * Indicate that the user is deactivated.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Assign a department to the user.
     */
    public function withDepartment(?int $departmentId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'department_id' => $departmentId,
        ]);
    }

    /**
     * Assign a section and optionally department to the user.
     */
    public function withSection(?int $sectionId = null, ?int $departmentId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'section_id' => $sectionId,
            'department_id' => $departmentId ?? $attributes['department_id'],
        ]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the model has two-factor authentication configured.
     */
    public function withTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => encrypt('secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
            'two_factor_confirmed_at' => now(),
        ]);
    }
}
