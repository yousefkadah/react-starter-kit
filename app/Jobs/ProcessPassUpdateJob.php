<?php

namespace App\Jobs;

use App\Models\Pass;
use App\Models\User;
use App\Services\PassUpdateService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessPassUpdateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @param  array<string, mixed>  $fields
     * @param  array<string, string>  $changeMessages
     */
    public function __construct(
        public int $passId,
        public array $fields,
        public ?int $initiatorId = null,
        public string $source = 'dashboard',
        public array $changeMessages = [],
    ) {
        $this->onQueue('push-notifications');
    }

    /**
     * Execute the job.
     */
    public function handle(PassUpdateService $passUpdateService): void
    {
        $pass = Pass::query()->with(['user', 'deviceRegistrations'])->find($this->passId);

        if ($pass === null) {
            return;
        }

        $initiator = $this->initiatorId !== null ? User::query()->find($this->initiatorId) : null;

        $passUpdate = $passUpdateService->updatePassFields(
            pass: $pass,
            fields: $this->fields,
            initiator: $initiator,
            source: $this->source,
            changeMessages: $this->changeMessages,
        );

        $passUpdate->refresh();
    }
}
