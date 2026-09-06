<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Manager = 'manager';
    case TeamLeader = 'team_leader';
    case User = 'user';

    /**
     * Get the human-friendly label for the role.
     */
    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrator',
            self::Manager => 'Manager',
            self::TeamLeader => 'Team Leader',
            self::User => 'Operator / User',
        };
    }

    /**
     * Get the badge color variant used across UI components.
     */
    public function badgeColor(): string
    {
        return match ($this) {
            self::Admin => 'purple',
            self::Manager => 'blue',
            self::TeamLeader => 'green',
            self::User => 'gray',
        };
    }
}
