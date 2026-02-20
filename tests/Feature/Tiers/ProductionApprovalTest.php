<?php

namespace Tests\Feature\Tiers;

use App\Mail\AdminProductionRequestMail;
use App\Mail\ProductionApprovedMail;
use App\Mail\ProductionRejectedMail;
use App\Mail\ProductionRequestMail;
use App\Models\AppleCertificate;
use App\Models\GoogleCredential;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ProductionApprovalTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test user can request production tier.
     */
    public function test_user_can_request_production(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create();
        $user = User::factory()->approved()->create([
            'tier' => 'Verified_And_Configured',
            'region' => 'US',
        ]);

        AppleCertificate::factory()->create(['user_id' => $user->id]);
        GoogleCredential::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->postJson('/api/tier/request-production');

        $response->assertSuccessful();

        $user->refresh();
        $this->assertNotNull($user->production_requested_at);

        Mail::assertSent(ProductionRequestMail::class);
        Mail::assertSent(AdminProductionRequestMail::class);
    }

    /**
     * Test admin can approve production tier request.
     */
    public function test_admin_can_approve_production_request(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create();
        $user = User::factory()->approved()->create([
            'tier' => 'Verified_And_Configured',
            'region' => 'US',
            'production_requested_at' => now(),
        ]);

        $response = $this->actingAs($admin)->post(
            "/admin/production-requests/{$user->id}/approve"
        );

        $response->assertSuccessful();

        $user->refresh();
        $this->assertEquals('Production', $user->tier);
        $this->assertNotNull($user->production_approved_at);

        Mail::assertSent(ProductionApprovedMail::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email);
        });
    }

    /**
     * Test admin can reject production tier request.
     */
    public function test_admin_can_reject_production_request(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create();
        $user = User::factory()->approved()->create([
            'tier' => 'Verified_And_Configured',
            'region' => 'US',
            'production_requested_at' => now(),
        ]);

        $response = $this->actingAs($admin)->post(
            "/admin/production-requests/{$user->id}/reject",
            ['reason' => 'Missing test passes']
        );

        $response->assertSuccessful();

        $user->refresh();
        $this->assertNotNull($user->production_rejected_at);
        $this->assertEquals('Missing test passes', $user->production_rejected_reason);
        $this->assertEquals('Verified_And_Configured', $user->tier);

        Mail::assertSent(ProductionRejectedMail::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email);
        });
    }
}
