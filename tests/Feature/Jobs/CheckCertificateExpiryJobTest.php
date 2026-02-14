<?php

namespace Tests\Feature\Jobs;

use App\Jobs\CheckCertificateExpiryJob;
use App\Jobs\SendExpiryNotificationJob;
use App\Models\AppleCertificate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CheckCertificateExpiryJobTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test job identifies certificates expiring in 30/7/0 day windows.
     */
    public function test_job_identifies_expiring_certificates(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-14 00:00:00'));
        Queue::fake();

        $user = User::factory()->approved()->create();

        $cert30 = AppleCertificate::factory()->for($user)->expiringIn(30)->create();
        $cert7 = AppleCertificate::factory()->for($user)->expiringIn(7)->create();
        $certExpired = AppleCertificate::factory()->for($user)->expired()->create();
        AppleCertificate::factory()->for($user)->expiringIn(90)->create();

        (new CheckCertificateExpiryJob)->handle();

        Queue::assertPushed(SendExpiryNotificationJob::class, function ($job) use ($cert30) {
            return $job->certificate->is($cert30) && $job->daysRemaining === 30;
        });

        Queue::assertPushed(SendExpiryNotificationJob::class, function ($job) use ($cert7) {
            return $job->certificate->is($cert7) && $job->daysRemaining === 7;
        });

        Queue::assertPushed(SendExpiryNotificationJob::class, function ($job) use ($certExpired) {
            return $job->certificate->is($certExpired) && $job->daysRemaining === 0;
        });

        Queue::assertPushed(SendExpiryNotificationJob::class, 3);
    }
}
