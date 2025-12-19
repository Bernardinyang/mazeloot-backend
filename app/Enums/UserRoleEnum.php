<?php

namespace App\Enums;

enum UserRoleEnum: string
{
    case USER = 'user';
    case ADMIN = 'admin';
    case SUPER_ADMIN = 'super_admin';

    /**
     * Get all role values as an array.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get the label for a role.
     *
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::USER => 'User',
            self::ADMIN => 'Admin',
            self::SUPER_ADMIN => 'Super Admin',
        };
    }

    /**
     * Check if the role can manage admins.
     *
     * @return bool
     */
    public function canManageAdmins(): bool
    {
        return $this === self::SUPER_ADMIN;
    }

    /**
     * Check if the role is an admin or super admin.
     *
     * @return bool
     */
    public function isAdmin(): bool
    {
        return in_array($this, [self::ADMIN, self::SUPER_ADMIN]);
    }
}

