<?php

namespace App\Jobs;

use App\Mail\CertificateExpiryMail;
use App\Models\AppleCertificate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendExpiryNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public AppleCertificate $certificate,
        public int $daysRemaining
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $user = $this->certificate->user;

        Mail::to($user->email)->send(new CertificateExpiryMail(
            $user,
            $this->certificate,
            $this->daysRemaining
        ));

        // Update notification flags
        if ($this->daysRemaining === 30) {
            $this->certificate->update(['expiry_notified_30_days' => true]);
        } elseif ($this->daysRemaining === 7) {
            $this->certificate->update(['expiry_notified_7_days' => true]);
        } else {
            $this->certificate->update(['expiry_notified_0_days' => true]);
        }
    }
}
