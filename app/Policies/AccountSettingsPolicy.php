<?php

namespace App\Policies;

use App\Models\User;

class AccountSettingsPolicy
{
    /**
     * Determine if a user can access account settings.
     */
    public function access(User $user): bool
    {
        return $user->is_admin || $user->approval_status === 'approved';
    }
}
