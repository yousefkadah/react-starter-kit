<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\OnboardingStepTracker;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class MarkOnboardingStepJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $userId,
        public string $stepKey
    ) {}

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
        $tracker->markStepComplete($user, $this->stepKey);
    }
}
