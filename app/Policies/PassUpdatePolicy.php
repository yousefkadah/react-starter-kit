<?php

namespace App\Policies;

use App\Models\Pass;
use App\Models\User;

class PassUpdatePolicy
{
    /**
     * Determine whether the user can update the given pass.
     */
    public function update(User $user, Pass $pass): bool
    {
        return (int) $pass->user_id === (int) $user->id;
    }

    /**
     * Determine whether the user can view update history for the given pass.
     */
    public function viewHistory(User $user, Pass $pass): bool
    {
        return (int) $pass->user_id === (int) $user->id;
    }
}
