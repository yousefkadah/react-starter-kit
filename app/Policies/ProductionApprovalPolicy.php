<?php

namespace App\Policies;

use App\Models\User;

class ProductionApprovalPolicy
{
    /**
     * Check if admin can view production requests queue.
     */
    public function viewQueue(User $admin): bool
    {
        return $admin->is_admin;
    }

    /**
     * Check if admin can approve production request.
     */
    public function approve(User $admin, User $requestedUser): bool
    {
        return $admin->is_admin;
    }

    /**
     * Check if admin can reject production request.
     */
    public function reject(User $admin, User $requestedUser): bool
    {
        return $admin->is_admin;
    }
}
