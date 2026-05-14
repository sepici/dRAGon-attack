<?php

namespace App\Enums;

/**
 * The three roles a user can have. One role per user, no mixing.
 * Each role has a distinct post-login experience.
 */
enum UserRole: string
{
    case Admin = 'admin';
    case User = 'user';
    case Viewer = 'viewer';

    /**
     * Human-readable label for forms and tables.
     */
    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::User => 'User',
            self::Viewer => 'Viewer',
        };
    }

    /**
     * The route name this role lands on after login.
     */
    public function landingRoute(): string
    {
        return match ($this) {
            self::Admin => 'admin.users.index',
            self::User => 'dashboard',
            self::Viewer => 'viewer.dashboard',
        };
    }
}
