<?php

namespace App\Services;

use App\Models\OnboardingStep;
use App\Models\User;

class OnboardingStepTracker
{
    /**
     * Mark a step complete for the given user.
     */
    public function markStepComplete(User $user, string $step): void
    {
        $record = OnboardingStep::firstOrCreate([
            'user_id' => $user->id,
            'step_key' => $step,
        ]);

        if (! $record->completed_at) {
            $record->markComplete();
        }
    }

    /**
     * Check if a step is complete.
     */
    public function isStepComplete(User $user, string $step): bool
    {
        return OnboardingStep::where('user_id', $user->id)
            ->where('step_key', $step)
            ->whereNotNull('completed_at')
            ->exists();
    }

    /**
     * Check if all steps are complete.
     */
    public function allStepsComplete(User $user): bool
    {
        $required = ['email_verified', 'apple_setup', 'google_setup', 'user_profile', 'first_pass'];

        $completed = OnboardingStep::where('user_id', $user->id)
            ->whereIn('step_key', $required)
            ->whereNotNull('completed_at')
            ->count();

        return $completed === count($required);
    }
}
