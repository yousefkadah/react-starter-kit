<?php

namespace Tests\Feature\Jobs;

use App\Jobs\SendExpiryNotificationJob;
use App\Mail\CertificateExpiryMail;
use App\Models\AppleCertificate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendExpiryNotificationJobTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test 30-day notification email and flag update.
     */
    public function test_sends30_day_notification(): void
    {
        Mail::fake();

        $user = User::factory()->approved()->create();
        $certificate = AppleCertificate::factory()->for($user)->expiringIn(30)->create([
            'expiry_notified_30_days' => false,
        ]);

        (new SendExpiryNotificationJob($certificate, 30))->handle();

        Mail::assertSent(CertificateExpiryMail::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email);
        });

        $certificate->refresh();
        $this->assertTrue($certificate->expiry_notified_30_days);
    }

    /**
     * Test 7-day notification email and flag update.
     */
    public function test_sends7_day_notification(): void
    {
        Mail::fake();

        $user = User::factory()->approved()->create();
        $certificate = AppleCertificate::factory()->for($user)->expiringIn(7)->create([
            'expiry_notified_7_days' => false,
        ]);

        (new SendExpiryNotificationJob($certificate, 7))->handle();

        Mail::assertSent(CertificateExpiryMail::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email);
        });

        $certificate->refresh();
        $this->assertTrue($certificate->expiry_notified_7_days);
    }

    /**
     * Test expired notification email and flag update.
     */
    public function test_sends_expired_notification(): void
    {
        Mail::fake();

        $user = User::factory()->approved()->create();
        $certificate = AppleCertificate::factory()->for($user)->expired()->create([
            'expiry_notified_0_days' => false,
        ]);

        (new SendExpiryNotificationJob($certificate, 0))->handle();

        Mail::assertSent(CertificateExpiryMail::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email);
        });

        $certificate->refresh();
        $this->assertTrue($certificate->expiry_notified_0_days);
    }
}
