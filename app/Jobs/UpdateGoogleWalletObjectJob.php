<?php

namespace App\Jobs;

use App\Models\Pass;
use App\Models\PassUpdate;
use App\Services\GooglePassService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class UpdateGoogleWalletObjectJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $passId,
        public int $passUpdateId,
    ) {
        $this->onQueue('push-notifications');
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        $configured = config('passkit.push.retry_backoff', [30, 120, 600]);

        if (! is_array($configured)) {
            return [30, 120, 600];
        }

        return array_values(array_map(static fn ($value): int => (int) $value, $configured));
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $pass = Pass::query()->with('user')->find($this->passId);
        $passUpdate = PassUpdate::query()->find($this->passUpdateId);

        if ($pass === null || $passUpdate === null) {
            return;
        }

        if (! is_string($pass->google_object_id) || $pass->google_object_id === '') {
            $passUpdate->forceFill([
                'google_delivery_status' => 'skipped',
            ])->save();

            return;
        }

        GooglePassService::forUser($pass->user)->patchObject($pass->google_object_id, []);

        $passUpdate->forceFill([
            'google_delivery_status' => 'delivered',
            'google_updated' => true,
            'error_message' => null,
        ])->save();
    }

    public function failed(Throwable $exception): void
    {
        $passUpdate = PassUpdate::query()->find($this->passUpdateId);

        if ($passUpdate === null) {
            return;
        }

        $passUpdate->forceFill([
            'google_delivery_status' => 'failed',
            'error_message' => $exception->getMessage(),
        ])->save();
    }
}
