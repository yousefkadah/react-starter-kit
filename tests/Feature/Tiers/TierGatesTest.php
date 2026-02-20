<?php

namespace Tests\Feature\Tiers;

use App\Models\AppleCertificate;
use App\Models\GoogleCredential;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class TierGatesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test user cannot request production unless requirements are met.
     */
    public function test_user_cannot_request_production_without_requirements(): void
    {
        $user = User::factory()->approved()->create([
            'tier' => 'Email_Verified',
            'region' => 'US',
        ]);

        $response = $this->actingAs($user)->postJson('/api/tier/request-production');

        $response->assertStatus(422);
    }

    /**
     * Test unapproved users cannot access account settings.
     */
    public function test_unapproved_user_cannot_access_account_settings(): void
    {
        $user = User::factory()->pending()->create([
            'tier' => 'Email_Verified',
            'region' => 'US',
        ]);

        $response = $this->actingAs($user)->getJson('/api/account');

        $response->assertForbidden();
    }

    /**
     * Test user can request production only when both certs exist.
     */
    public function test_user_can_request_production_when_configured(): void
    {
        $user = User::factory()->approved()->create([
            'tier' => 'Verified_And_Configured',
            'region' => 'US',
        ]);

        $this->actingAs($user);

        AppleCertificate::factory()->create(['user_id' => $user->id]);
        GoogleCredential::factory()->create(['user_id' => $user->id]);

        // Fake mail to avoid missing view errors in test environment
        Mail::fake();

        $response = $this->postJson('/api/tier/request-production');

        $response->assertSuccessful();
    }

    /**
     * Test user cannot go live unless in Production tier.
     */
    public function test_user_cannot_go_live_when_not_production(): void
    {
        $user = User::factory()->approved()->create([
            'tier' => 'Verified_And_Configured',
            'region' => 'US',
        ]);

        $response = $this->actingAs($user)->postJson('/api/tier/go-live');

        $response->assertStatus(422);
    }
}
