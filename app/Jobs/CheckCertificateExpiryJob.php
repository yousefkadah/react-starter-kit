<?php

namespace App\Jobs;

use App\Models\AppleCertificate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CheckCertificateExpiryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $now = now();

        // 30-day notifications (8-30 day window)
        $thirtyDay = $now->copy()->addDays(30);
        $eightDay = $now->copy()->addDays(8);
        AppleCertificate::where('expiry_date', '>', $now)
            ->where('expiry_date', '>=', $eightDay)
            ->where('expiry_date', '<=', $thirtyDay)
            ->where('expiry_notified_30_days', false)
            ->each(function (AppleCertificate $certificate) {
                SendExpiryNotificationJob::dispatch($certificate, 30);
            });

        // 7-day notifications (1-7 day window)
        $sevenDay = $now->copy()->addDays(7);
        AppleCertificate::where('expiry_date', '>', $now)
            ->where('expiry_date', '<=', $sevenDay)
            ->where('expiry_notified_7_days', false)
            ->each(function (AppleCertificate $certificate) {
                SendExpiryNotificationJob::dispatch($certificate, 7);
            });

        // Expired notifications (0 days)
        AppleCertificate::where('expiry_date', '<=', $now)
            ->where('expiry_notified_0_days', false)
            ->each(function (AppleCertificate $certificate) {
                SendExpiryNotificationJob::dispatch($certificate, 0);
            });
    }
}
