<?php

namespace App\Policies;

use App\Models\User;

class AdminApprovalPolicy
{
    /**
     * Determine whether the user can view the approval queue.
     */
    public function viewQueue(User $user): bool
    {
        return $user->is_admin;
    }

    /**
     * Determine whether the user can approve accounts.
     */
    public function approve(User $user, User $target): bool
    {
        return $user->is_admin;
    }

    /**
     * Determine whether the user can reject accounts.
     */
    public function reject(User $user, User $target): bool
    {
        return $user->is_admin;
    }

    /**
     * Determine whether the user can view approved accounts.
     */
    public function viewApproved(User $user): bool
    {
        return $user->is_admin;
    }

    /**
     * Determine whether the user can view rejected accounts.
     */
    public function viewRejected(User $user): bool
    {
        return $user->is_admin;
    }
}
