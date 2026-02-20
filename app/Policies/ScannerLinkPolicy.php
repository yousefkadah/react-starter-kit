<?php

namespace App\Policies;

use App\Models\ScannerLink;
use App\Models\User;

class ScannerLinkPolicy
{
    /**
     * Determine if the user can view any scanner links.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine if the user can view the scanner link.
     */
    public function view(User $user, ScannerLink $scannerLink): bool
    {
        return $user->id === $scannerLink->user_id;
    }

    /**
     * Determine if the user can create scanner links.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine if the user can update the scanner link.
     */
    public function update(User $user, ScannerLink $scannerLink): bool
    {
        return $user->id === $scannerLink->user_id;
    }

    /**
     * Determine if the user can delete the scanner link.
     */
    public function delete(User $user, ScannerLink $scannerLink): bool
    {
        return $user->id === $scannerLink->user_id;
    }
}
