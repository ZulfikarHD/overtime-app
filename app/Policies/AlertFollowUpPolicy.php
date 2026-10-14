<?php

namespace App\Policies;

use App\Models\AlertFollowUp;
use App\Models\User;

class AlertFollowUpPolicy
{
    /**
     * Admin, Manager and Team Leader roles may work with alert follow-ups.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isManager() || $user->isTeamLeader();
    }

    /**
     * Admins see every follow-up; others only those of their own department.
     */
    public function view(User $user, AlertFollowUp $alertFollowUp): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        return $user->department_id !== null
            && (int) $user->department_id === (int) $alertFollowUp->department_id;
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    /**
     * A sent follow-up is read-only for everyone.
     */
    public function update(User $user, AlertFollowUp $alertFollowUp): bool
    {
        return ! $alertFollowUp->isSent() && $this->view($user, $alertFollowUp);
    }
}
