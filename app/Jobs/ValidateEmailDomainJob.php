<?php

namespace App\Jobs;

use App\Events\UserApprovalPendingEvent;
use App\Events\UserApprovedEvent;
use App\Mail\UserApprovedMail;
use App\Mail\UserPendingApprovalMail;
use App\Models\User;
use App\Services\EmailDomainService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class ValidateEmailDomainJob implements ShouldQueue
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
        $service = app(EmailDomainService::class);

        // Check if email domain is whitelisted
        if ($service->isBusinessDomain($this->user->email)) {
            // Auto-approve business domain accounts
            $service->approveAccount($this->user, User::where('is_admin', true)->first());

            // Dispatch event
            event(new UserApprovedEvent($this->user));

            // Send approval email
            Mail::to($this->user->email)->send(new UserApprovedMail($this->user));
        } else {
            // Queue for manual approval
            $service->queueForApproval($this->user);

            // Dispatch event
            event(new UserApprovalPendingEvent($this->user));

            // Send pending approval email
            Mail::to($this->user->email)->send(new UserPendingApprovalMail($this->user));
        }
    }
}
