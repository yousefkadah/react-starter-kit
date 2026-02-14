<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\OnboardingStepTracker;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class MarkOnboardingStepsForWalletJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(public int $userId) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $user = User::find($this->userId);
        if (! $user) {
            return;
        }

        $tracker = app(OnboardingStepTracker::class);

        $hasApple = $user->appleCertificates()->whereNull('deleted_at')->exists();
        $hasGoogle = $user->googleCredentials()->whereNull('deleted_at')->exists();

        if ($hasApple) {
            $tracker->markStepComplete($user, 'apple_setup');
        }

        if ($hasGoogle) {
            $tracker->markStepComplete($user, 'google_setup');
        }
    }
}
