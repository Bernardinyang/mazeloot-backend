<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Determine if the user can create admin users.
     * Only super admins can create admin users.
     */
    public function createAdmin(User $user): bool
    {
        return $user->canManageAdmins();
    }

    /**
     * Determine if the user can update a user's role to admin.
     * Only super admins can promote users to admin.
     */
    public function promoteToAdmin(User $user, User $model): bool
    {
        // Cannot promote yourself
        if ($user->uuid === $model->uuid) {
            return false;
        }

        // Only super admins can promote to admin
        return $user->canManageAdmins();
    }

    /**
     * Determine if the user can update a user's role.
     * Only super admins can change roles.
     */
    public function updateRole(User $user, User $model): bool
    {
        // Cannot change your own role
        if ($user->uuid === $model->uuid) {
            return false;
        }

        // Only super admins can change roles
        return $user->canManageAdmins();
    }

    /**
     * Determine if the user can view any users.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the user can view the user.
     */
    public function view(User $user, User $model): bool
    {
        // Users can view themselves, admins can view anyone
        return $user->uuid === $model->uuid || $user->isAdmin();
    }

    /**
     * Determine if the user can update the user.
     */
    public function update(User $user, User $model): bool
    {
        // Users can update themselves, admins can update anyone
        return $user->uuid === $model->uuid || $user->isAdmin();
    }

    /**
     * Determine if the user can delete the user.
     */
    public function delete(User $user, User $model): bool
    {
        // Cannot delete yourself
        if ($user->uuid === $model->uuid) {
            return false;
        }

        // Only super admins can delete users
        return $user->isSuperAdmin();
    }
}
