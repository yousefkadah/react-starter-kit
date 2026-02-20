<?php

namespace App\Policies;

use App\Models\Pass;
use App\Models\PassDistributionLink;
use App\Models\User;

class PassPolicy
{
    /**
     * Determine if the user can view any pass.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine if the user can view the pass.
     */
    public function view(User $user, Pass $pass): bool
    {
        return $user->id === $pass->user_id;
    }

    /**
     * Determine if the user can update the pass.
     */
    public function update(User $user, Pass $pass): bool
    {
        return $user->id === $pass->user_id;
    }

    /**
     * Determine if the user can delete the pass.
     */
    public function delete(User $user, Pass $pass): bool
    {
        return $user->id === $pass->user_id;
    }

    /**
     * Determine if the user can create a distribution link for the pass.
     */
    public function createDistributionLink(User $user, Pass $pass): bool
    {
        return $user->id === $pass->user_id;
    }

    /**
     * Determine if the user can view distribution links for the pass.
     */
    public function viewDistributionLinks(User $user, Pass $pass): bool
    {
        return $user->id === $pass->user_id;
    }

    /**
     * Determine if the user can update a distribution link for the pass.
     */
    public function updateDistributionLink(User $user, Pass $pass, PassDistributionLink $link): bool
    {
        // Verify user owns the pass and the link belongs to that pass
        return $user->id === $pass->user_id && $link->pass_id === $pass->id;
    }
}
