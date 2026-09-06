<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\UserAudit;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class UserService
{
    /**
     * Get paginated users with filtering and eager loading.
     *
     * @param  array<string, mixed>  $filters
     */
    public function getPaginatedUsers(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $query = User::query()
            ->with([
                'department:id,code,name',
                'section:id,code,name,department_id',
            ])
            ->withCount([
                'overtimeSubmissions',
                'reviewedOvertimeItems',
                'attachedSpklDocuments',
            ]);

        // Search by name, email, NPK, department, or section
        if (! empty($filters['search'])) {
            $term = '%'.trim((string) $filters['search']).'%';
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('npk', 'like', $term)
                    ->orWhereHas('department', function ($dq) use ($term) {
                        $dq->where('name', 'like', $term)
                            ->orWhere('code', 'like', $term);
                    })
                    ->orWhereHas('section', function ($sq) use ($term) {
                        $sq->where('name', 'like', $term)
                            ->orWhere('code', 'like', $term);
                    });
            });
        }

        // Filter by role
        if (! empty($filters['role']) && $filters['role'] !== 'all') {
            $query->where('role', $filters['role']);
        }

        // Filter by status
        if (! empty($filters['status']) && $filters['status'] !== 'all') {
            if ($filters['status'] === 'active') {
                $query->where('is_active', true);
            } elseif ($filters['status'] === 'inactive') {
                $query->where('is_active', false);
            }
        }

        // Filter by department
        if (! empty($filters['department_id']) && is_numeric($filters['department_id'])) {
            $query->where('department_id', (int) $filters['department_id']);
        }

        return $query
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Get statistical counters for user accounts.
     *
     * @return array<string, mixed>
     */
    public function getUserStats(): array
    {
        return [
            'total' => User::count(),
            'active' => User::where('is_active', true)->count(),
            'inactive' => User::where('is_active', false)->count(),
            'by_role' => [
                'admin' => User::where('role', UserRole::Admin)->count(),
                'manager' => User::where('role', UserRole::Manager)->count(),
                'team_leader' => User::where('role', UserRole::TeamLeader)->count(),
                'user' => User::where('role', UserRole::User)->count(),
            ],
        ];
    }

    /**
     * Create a new user account and log creation audit.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?User $actor = null): User
    {
        $role = $data['role'] instanceof UserRole ? $data['role'] : UserRole::from($data['role']);

        $user = User::create([
            'name' => trim((string) $data['name']),
            'email' => strtolower(trim((string) $data['email'])),
            'password' => Hash::make((string) $data['password']),
            'role' => $role,
            'department_id' => ! empty($data['department_id']) ? (int) $data['department_id'] : null,
            'section_id' => ! empty($data['section_id']) ? (int) $data['section_id'] : null,
            'npk' => ! empty($data['npk']) ? strtoupper(trim((string) $data['npk'])) : null,
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true,
        ]);

        UserAudit::create([
            'user_id' => $user->id,
            'actor_user_id' => $actor?->id,
            'action' => 'user_created',
            'new_role' => $role->value,
            'details' => [
                'name' => $user->name,
                'email' => $user->email,
                'department_id' => $user->department_id,
                'section_id' => $user->section_id,
            ],
            'ip_address' => request()?->ip(),
        ]);

        return $user;
    }

    /**
     * Update an existing user account and log changes.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function update(User $user, array $data, ?User $actor = null): User
    {
        // Guard against self-lockout
        if ($actor && $actor->id === $user->id) {
            if (array_key_exists('is_active', $data) && ! (bool) $data['is_active']) {
                throw ValidationException::withMessages([
                    'is_active' => [__('You cannot deactivate your own account.')],
                ]);
            }

            if (isset($data['role'])) {
                $checkRole = $data['role'] instanceof UserRole ? $data['role']->value : (string) $data['role'];
                if ($checkRole !== UserRole::Admin->value) {
                    throw ValidationException::withMessages([
                        'role' => [__('You cannot remove your own administrator privileges.')],
                    ]);
                }
            }
        }

        $previousRole = $user->role instanceof UserRole ? $user->role->value : (string) $user->role;
        $previousIsActive = $user->is_active;

        $updatePayload = [
            'name' => trim((string) ($data['name'] ?? $user->name)),
            'email' => strtolower(trim((string) ($data['email'] ?? $user->email))),
            'department_id' => array_key_exists('department_id', $data)
                ? (! empty($data['department_id']) ? (int) $data['department_id'] : null)
                : $user->department_id,
            'section_id' => array_key_exists('section_id', $data)
                ? (! empty($data['section_id']) ? (int) $data['section_id'] : null)
                : $user->section_id,
            'npk' => array_key_exists('npk', $data)
                ? (! empty($data['npk']) ? strtoupper(trim((string) $data['npk'])) : null)
                : $user->npk,
        ];

        if (isset($data['role'])) {
            $updatePayload['role'] = $data['role'] instanceof UserRole ? $data['role'] : UserRole::from($data['role']);
        }

        if (array_key_exists('is_active', $data)) {
            $updatePayload['is_active'] = (bool) $data['is_active'];
        }

        // Only update password if non-empty string is provided
        if (! empty($data['password'])) {
            $updatePayload['password'] = Hash::make((string) $data['password']);
        }

        $user->update($updatePayload);
        $user->refresh();

        $newRole = $user->role instanceof UserRole ? $user->role->value : (string) $user->role;

        // Role change audit
        if ($previousRole !== $newRole) {
            UserAudit::create([
                'user_id' => $user->id,
                'actor_user_id' => $actor?->id,
                'action' => 'role_change',
                'previous_role' => $previousRole,
                'new_role' => $newRole,
                'details' => [
                    'changed_by' => $actor?->name ?? 'System',
                ],
                'ip_address' => request()?->ip(),
            ]);
        } elseif ($previousIsActive !== $user->is_active) {
            UserAudit::create([
                'user_id' => $user->id,
                'actor_user_id' => $actor?->id,
                'action' => 'status_toggled',
                'details' => [
                    'is_active' => $user->is_active,
                ],
                'ip_address' => request()?->ip(),
            ]);
        } else {
            UserAudit::create([
                'user_id' => $user->id,
                'actor_user_id' => $actor?->id,
                'action' => 'user_updated',
                'details' => [
                    'updated_fields' => array_keys($updatePayload),
                ],
                'ip_address' => request()?->ip(),
            ]);
        }

        return $user;
    }

    /**
     * Delete a user account with strict data integrity guards.
     *
     * @throws ValidationException
     */
    public function delete(User $user, ?User $actor = null): bool
    {
        if ($actor && $actor->id === $user->id) {
            throw ValidationException::withMessages([
                'user' => [__('Cannot delete your own account.')],
            ]);
        }

        if (
            $user->overtimeSubmissions()->exists()
            || $user->reviewedOvertimeItems()->exists()
            || $user->attachedSpklDocuments()->exists()
        ) {
            throw ValidationException::withMessages([
                'user' => [__('Cannot delete user with existing overtime submissions, reviews, or attached documents. Please deactivate the user instead.')],
            ]);
        }

        return (bool) $user->delete();
    }

    /**
     * Send a password reset link to the user's email address.
     *
     * @throws ValidationException
     */
    public function sendResetLink(User $user, ?User $actor = null): string
    {
        $status = Password::broker()->sendResetLink(['email' => $user->email]);

        if ($status !== Password::RESET_LINK_SENT) {
            throw ValidationException::withMessages([
                'email' => [__('Unable to send password reset link: :status', ['status' => __($status)])],
            ]);
        }

        UserAudit::create([
            'user_id' => $user->id,
            'actor_user_id' => $actor?->id,
            'action' => 'password_reset_sent',
            'details' => [
                'recipient_email' => $user->email,
                'requested_by' => $actor?->name ?? 'System',
            ],
            'ip_address' => request()?->ip(),
        ]);

        return __($status);
    }
}
