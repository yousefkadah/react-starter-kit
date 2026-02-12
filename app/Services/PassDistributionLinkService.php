<?php

namespace App\Services;

use App\Models\Pass;
use App\Models\PassDistributionLink;

class PassDistributionLinkService
{
    /**
     * Create a new distribution link for a pass.
     */
    public function create(Pass $pass): PassDistributionLink
    {
        return PassDistributionLink::create([
            'pass_id' => $pass->id,
            'status' => 'active',
            'accessed_count' => 0,
        ]);
    }

    /**
     * Disable a distribution link.
     */
    public function disable(PassDistributionLink $link): void
    {
        $link->update(['status' => 'disabled']);
    }

    /**
     * Enable a distribution link.
     */
    public function enable(PassDistributionLink $link): void
    {
        $link->update(['status' => 'active']);
    }
}
