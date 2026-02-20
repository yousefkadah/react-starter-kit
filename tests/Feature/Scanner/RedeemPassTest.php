<?php

namespace Tests\Feature\Scanner;

use App\Models\Pass;
use App\Models\ScannerLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RedeemPassTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private ScannerLink $scannerLink;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->forRegionUS()->create();
        $this->scannerLink = ScannerLink::factory()->for($this->user)->create();
    }

    // -------------------------------------------------------
    // US2: Single-Use Coupon Redemption
    // -------------------------------------------------------

    public function test_redeems_active_single_use_pass_successfully(): void
    {
        $pass = Pass::factory()->for($this->user)->singleUse()->create([
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/scanner/redeem', [
            'pass_id' => $pass->id,
        ], [
            'X-Scanner-Token' => $this->scannerLink->token,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Pass redeemed successfully.',
                'pass' => [
                    'id' => $pass->id,
                    'status' => 'redeemed',
                ],
            ]);

        $this->assertDatabaseHas('passes', [
            'id' => $pass->id,
            'status' => 'redeemed',
        ]);

        $this->assertNotNull($pass->fresh()->redeemed_at);
    }

    public function test_prevents_double_redemption_of_single_use_pass(): void
    {
        $pass = Pass::factory()->for($this->user)->redeemed()->create();

        $response = $this->postJson('/api/scanner/redeem', [
            'pass_id' => $pass->id,
        ], [
            'X-Scanner-Token' => $this->scannerLink->token,
        ]);

        $response->assertStatus(409)
            ->assertJson([
                'success' => false,
                'error' => 'This pass has already been redeemed.',
            ]);
    }

    public function test_prevents_redemption_of_voided_pass(): void
    {
        $pass = Pass::factory()->for($this->user)->voided()->create();

        $response = $this->postJson('/api/scanner/redeem', [
            'pass_id' => $pass->id,
        ], [
            'X-Scanner-Token' => $this->scannerLink->token,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error' => 'This pass is voided and cannot be redeemed.',
            ]);
    }

    public function test_prevents_redemption_of_expired_pass(): void
    {
        $pass = Pass::factory()->for($this->user)->create([
            'status' => 'active',
            'usage_type' => 'single_use',
            'pass_data' => ['expiry_date' => now()->subDay()->toDateString()],
        ]);

        $response = $this->postJson('/api/scanner/redeem', [
            'pass_id' => $pass->id,
        ], [
            'X-Scanner-Token' => $this->scannerLink->token,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error' => 'This pass has expired and cannot be redeemed.',
            ]);
    }

    public function test_prevents_cross_tenant_redemption(): void
    {
        $otherUser = User::factory()->forRegionUS()->create();
        $pass = Pass::factory()->for($otherUser)->singleUse()->create([
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/scanner/redeem', [
            'pass_id' => $pass->id,
        ], [
            'X-Scanner-Token' => $this->scannerLink->token,
        ]);

        $response->assertNotFound()
            ->assertJson([
                'success' => false,
                'error' => 'Pass not found.',
            ]);
    }

    public function test_records_redeem_scan_event(): void
    {
        $pass = Pass::factory()->for($this->user)->singleUse()->create([
            'status' => 'active',
        ]);

        $this->postJson('/api/scanner/redeem', [
            'pass_id' => $pass->id,
        ], [
            'X-Scanner-Token' => $this->scannerLink->token,
        ]);

        $this->assertDatabaseHas('scan_events', [
            'pass_id' => $pass->id,
            'user_id' => $this->user->id,
            'scanner_link_id' => $this->scannerLink->id,
            'action' => 'redeem',
            'result' => 'success',
        ]);
    }

    public function test_records_failed_redeem_scan_event(): void
    {
        $pass = Pass::factory()->for($this->user)->redeemed()->create();

        $this->postJson('/api/scanner/redeem', [
            'pass_id' => $pass->id,
        ], [
            'X-Scanner-Token' => $this->scannerLink->token,
        ]);

        $this->assertDatabaseHas('scan_events', [
            'pass_id' => $pass->id,
            'user_id' => $this->user->id,
            'scanner_link_id' => $this->scannerLink->id,
            'action' => 'redeem',
            'result' => 'already_redeemed',
        ]);
    }

    public function test_redemption_requires_pass_id(): void
    {
        $response = $this->postJson('/api/scanner/redeem', [], [
            'X-Scanner-Token' => $this->scannerLink->token,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['pass_id']);
    }

    public function test_returns_custom_redemption_message_on_redeem(): void
    {
        $pass = Pass::factory()->for($this->user)->singleUse()->withRedemptionMessage('Hand customer the free item')->create([
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/scanner/redeem', [
            'pass_id' => $pass->id,
        ], [
            'X-Scanner-Token' => $this->scannerLink->token,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'pass' => [
                    'custom_redemption_message' => 'Hand customer the free item',
                ],
            ]);
    }

    // -------------------------------------------------------
    // US3: Multi-Use Loyalty Scanning
    // -------------------------------------------------------

    public function test_logs_visit_for_active_multi_use_pass(): void
    {
        $pass = Pass::factory()->for($this->user)->multiUse()->create([
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/scanner/redeem', [
            'pass_id' => $pass->id,
        ], [
            'X-Scanner-Token' => $this->scannerLink->token,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Visit logged successfully.',
                'pass' => [
                    'id' => $pass->id,
                    'status' => 'active',
                ],
            ]);

        // Multi-use pass should NOT be redeemed
        $this->assertDatabaseHas('passes', [
            'id' => $pass->id,
            'status' => 'active',
        ]);

        // Should log the scan event
        $this->assertDatabaseHas('scan_events', [
            'pass_id' => $pass->id,
            'action' => 'visit',
            'result' => 'success',
        ]);
    }

    public function test_multi_use_pass_can_be_scanned_multiple_times(): void
    {
        $pass = Pass::factory()->for($this->user)->multiUse()->create([
            'status' => 'active',
        ]);

        // First visit
        $this->postJson('/api/scanner/redeem', [
            'pass_id' => $pass->id,
        ], [
            'X-Scanner-Token' => $this->scannerLink->token,
        ])->assertOk()->assertJson(['success' => true]);

        // Second visit
        $this->postJson('/api/scanner/redeem', [
            'pass_id' => $pass->id,
        ], [
            'X-Scanner-Token' => $this->scannerLink->token,
        ])->assertOk()->assertJson(['success' => true]);

        // Both should be logged
        $this->assertDatabaseCount('scan_events', 2);

        // Pass should still be active
        $this->assertDatabaseHas('passes', [
            'id' => $pass->id,
            'status' => 'active',
        ]);
    }

    public function test_prevents_visit_for_voided_multi_use_pass(): void
    {
        $pass = Pass::factory()->for($this->user)->multiUse()->voided()->create();

        $response = $this->postJson('/api/scanner/redeem', [
            'pass_id' => $pass->id,
        ], [
            'X-Scanner-Token' => $this->scannerLink->token,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error' => 'This pass is voided and cannot be redeemed.',
            ]);
    }
}
