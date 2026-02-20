<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Determine whether the user can view another user's profile.
     */
    public function view(User $user, User $target): bool
    {
        // Users can view their own profile
        if ($user->id === $target->id) {
            return true;
        }

        // Admins can view any user
        return $user->is_admin;
    }

    /**
     * Determine whether the user can update another user's profile.
     */
    public function update(User $user, User $target): bool
    {
        // Users can only update their own profile
        if ($user->id === $target->id) {
            return true;
        }

        // Admins can update any user
        return $user->is_admin;
    }

    /**
     * Determine whether the user can delete another user.
     */
    public function delete(User $user, User $target): bool
    {
        // Users cannot be deleted by themselves
        if ($user->id === $target->id) {
            return false;
        }

        // Only admins can delete users
        return $user->is_admin;
    }
}
