<?php

namespace Tests\Feature\Tiers;

use App\Mail\TierAdvancedMail;
use App\Models\AppleCertificate;
use App\Models\GoogleCredential;
use App\Models\User;
use App\Services\TierProgressionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class TierProgressionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test user auto-advances to Verified_And_Configured when both certs exist.
     */
    public function test_user_auto_advances_when_both_certs_exist(): void
    {
        Mail::fake();

        $user = User::factory()->approved()->create([
            'tier' => 'Email_Verified',
        ]);

        AppleCertificate::factory()->create(['user_id' => $user->id]);
        GoogleCredential::factory()->create(['user_id' => $user->id]);

        $service = app(TierProgressionService::class);
        $service->evaluateAndAdvanceTier($user);

        $user->refresh();

        $this->assertEquals('Verified_And_Configured', $user->tier);

        Mail::assertSent(TierAdvancedMail::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email);
        });
    }

    /**
     * Test user does not auto-advance to Production tier.
     */
    public function test_user_does_not_auto_advance_to_production(): void
    {
        $user = User::factory()->approved()->create([
            'tier' => 'Verified_And_Configured',
        ]);

        AppleCertificate::factory()->create(['user_id' => $user->id]);
        GoogleCredential::factory()->create(['user_id' => $user->id]);

        $service = app(TierProgressionService::class);
        $service->evaluateAndAdvanceTier($user);

        $user->refresh();
        $this->assertEquals('Verified_And_Configured', $user->tier);
    }

    /**
     * Test unapproved user does not advance tiers.
     */
    public function test_unapproved_user_does_not_advance(): void
    {
        $user = User::factory()->pending()->create([
            'tier' => 'Email_Verified',
        ]);

        AppleCertificate::factory()->create(['user_id' => $user->id]);
        GoogleCredential::factory()->create(['user_id' => $user->id]);

        $service = app(TierProgressionService::class);
        $service->evaluateAndAdvanceTier($user);

        $user->refresh();
        $this->assertEquals('Email_Verified', $user->tier);
    }
}
