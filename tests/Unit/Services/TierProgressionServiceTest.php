<?php

namespace Tests\Unit\Services;

use App\Mail\AdminProductionRequestMail;
use App\Mail\LiveTierMail;
use App\Mail\ProductionApprovedMail;
use App\Mail\ProductionRequestMail;
use App\Mail\TierAdvancedMail;
use App\Models\AppleCertificate;
use App\Models\GoogleCredential;
use App\Models\Pass;
use App\Models\User;
use App\Services\TierProgressionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class TierProgressionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected TierProgressionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(TierProgressionService::class);
    }

    public function test_evaluate_and_advance_tier_when_configured(): void
    {
        Mail::fake();

        $user = User::factory()->approved()->create([
            'tier' => 'Email_Verified',
            'approval_status' => 'approved',
        ]);

        AppleCertificate::factory()->valid()->create(['user_id' => $user->id]);
        GoogleCredential::factory()->create(['user_id' => $user->id]);

        $this->service->evaluateAndAdvanceTier($user);

        $user->refresh();
        $this->assertEquals('Verified_And_Configured', $user->tier);

        Mail::assertSent(TierAdvancedMail::class);
    }

    public function test_submit_production_request_sends_emails(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create();
        $user = User::factory()->approved()->create([
            'tier' => 'Verified_And_Configured',
            'approval_status' => 'approved',
        ]);

        AppleCertificate::factory()->valid()->create(['user_id' => $user->id]);
        GoogleCredential::factory()->create(['user_id' => $user->id]);

        $this->service->submitProductionRequest($user);

        $user->refresh();
        $this->assertNotNull($user->production_requested_at);

        Mail::assertSent(ProductionRequestMail::class);
        Mail::assertSent(AdminProductionRequestMail::class, function ($mail) use ($admin) {
            return $mail->hasTo($admin->email);
        });
    }

    public function test_approve_production_sets_tier_and_notifies_user(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create();
        $user = User::factory()->approved()->create([
            'tier' => 'Verified_And_Configured',
            'approval_status' => 'approved',
        ]);

        $this->service->approveProduction($user, $admin);

        $user->refresh();
        $this->assertEquals('Production', $user->tier);
        $this->assertNotNull($user->production_approved_at);

        Mail::assertSent(ProductionApprovedMail::class);
    }

    public function test_advance_to_live_requires_checklist_and_sends_mail(): void
    {
        Mail::fake();

        $user = User::factory()->approved()->create([
            'tier' => 'Production',
            'approval_status' => 'approved',
            'name' => 'Acme Corp',
            'business_name' => 'Acme Corp',
            'pre_launch_checklist' => ['tested_on_device' => true],
        ]);

        AppleCertificate::factory()->valid()->create(['user_id' => $user->id]);
        GoogleCredential::factory()->create(['user_id' => $user->id]);
        Pass::factory()->for($user)->create();

        $this->service->advanceToLive($user);

        $user->refresh();
        $this->assertEquals('Live', $user->tier);
        $this->assertNotNull($user->live_approved_at);

        Mail::assertSent(LiveTierMail::class);
    }
}
