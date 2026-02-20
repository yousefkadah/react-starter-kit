<?php

namespace App\Jobs;

use App\Models\PassUpdate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PrunePassUpdateHistoryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        $this->onQueue('push-notifications');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        PassUpdate::query()
            ->where('created_at', '<', now()->subDays(90))
            ->delete();
    }
}
