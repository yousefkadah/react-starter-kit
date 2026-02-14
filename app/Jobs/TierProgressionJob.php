<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\TierProgressionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class TierProgressionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(public User $user) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $service = app(TierProgressionService::class);

        // Evaluate and advance tier if criteria are met
        $service->evaluateAndAdvanceTier($this->user);
    }
}
