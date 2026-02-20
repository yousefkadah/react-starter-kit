<?php

namespace App\Jobs;

use App\Models\BulkUpdate;
use App\Models\Pass;
use App\Services\PassUpdateService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\RateLimiter;

class BulkPassUpdateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [30, 120, 600];

    public function __construct(public int $bulkUpdateId)
    {
        $this->onQueue('push-notifications');
    }

    public function handle(PassUpdateService $passUpdateService): void
    {
        $bulkUpdate = BulkUpdate::query()->with(['user', 'passTemplate'])->find($this->bulkUpdateId);

        if ($bulkUpdate === null) {
            return;
        }

        $bulkUpdate->forceFill([
            'status' => 'processing',
            'started_at' => now(),
        ])->save();

        $query = Pass::query()
            ->with(['user', 'template', 'deviceRegistrations'])
            ->where('pass_template_id', $bulkUpdate->pass_template_id)
            ->where('user_id', $bulkUpdate->user_id);

        $filters = is_array($bulkUpdate->filters) ? $bulkUpdate->filters : [];

        if (($filters['status'] ?? null) === 'active') {
            $query->where('status', 'active');
        }

        if (($filters['platform'] ?? null) === 'apple') {
            $query->whereJsonContains('platforms', 'apple');
        }

        if (($filters['platform'] ?? null) === 'google') {
            $query->whereJsonContains('platforms', 'google');
        }

        $passes = $query->get();

        if ($bulkUpdate->total_count !== $passes->count()) {
            $bulkUpdate->forceFill([
                'total_count' => $passes->count(),
            ])->save();
        }

        foreach ($passes as $pass) {
            if (! $pass instanceof Pass) {
                continue;
            }

            $limiterKey = 'push-notifications:'.$bulkUpdate->user_id;

            while (! RateLimiter::attempt($limiterKey, 50, static function (): void {}, 1)) {
                usleep(100000);
            }

            try {
                $passUpdateService->updatePassFields(
                    pass: $pass,
                    fields: [
                        (string) $bulkUpdate->field_key => $bulkUpdate->field_value,
                    ],
                    initiator: $bulkUpdate->user,
                    source: 'bulk',
                    changeMessages: [],
                    bulkUpdateId: $bulkUpdate->id,
                );

                $bulkUpdate->increment('processed_count');
            } catch (\Throwable $exception) {
                $bulkUpdate->increment('failed_count');
            }
        }

        $bulkUpdate->forceFill([
            'status' => 'completed',
            'completed_at' => now(),
        ])->save();
    }
}
