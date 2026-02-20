<?php

namespace Tests\Feature\Jobs;

use App\Models\User;
use App\Services\OnboardingStepTracker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnboardingStepTrackerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test steps are marked complete and allStepsComplete returns true.
     */
    public function test_marks_steps_complete(): void
    {
        $user = User::factory()->approved()->create();
        $tracker = app(OnboardingStepTracker::class);

        $this->assertFalse($tracker->allStepsComplete($user));

        $tracker->markStepComplete($user, 'email_verified');
        $tracker->markStepComplete($user, 'apple_setup');
        $tracker->markStepComplete($user, 'google_setup');
        $tracker->markStepComplete($user, 'user_profile');
        $tracker->markStepComplete($user, 'first_pass');

        $this->assertTrue($tracker->isStepComplete($user, 'first_pass'));
        $this->assertTrue($tracker->allStepsComplete($user));
    }
}
