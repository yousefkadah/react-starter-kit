<?php

namespace Tests\Feature\Scanner;

use App\Models\Pass;
use App\Models\ScannerLink;
use App\Models\User;
use App\Services\PassPayloadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ValidatePassTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private ScannerLink $scannerLink;

    private PassPayloadService $payloadService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->forRegionUS()->create();
        $this->scannerLink = ScannerLink::factory()->for($this->user)->create();
        $this->payloadService = app(PassPayloadService::class);
    }

    public function test_validates_active_pass_successfully(): void
    {
        $pass = Pass::factory()->for($this->user)->singleUse()->create([
            'status' => 'active',
        ]);

        $payload = $this->payloadService->generatePayload($pass);

        $response = $this->postJson('/api/scanner/validate', [
            'payload' => $payload,
        ], [
            'X-Scanner-Token' => $this->scannerLink->token,
        ]);

        $response->assertOk()
            ->assertJson([
                'valid' => true,
                'pass' => [
                    'id' => $pass->id,
                    'type' => 'single_use',
                    'status' => 'active',
                ],
            ]);
    }

    public function test_returns_error_for_invalid_signature(): void
    {
        $response = $this->postJson('/api/scanner/validate', [
            'payload' => base64_encode('999.invalid-signature-here'),
        ], [
            'X-Scanner-Token' => $this->scannerLink->token,
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'valid' => false,
                'error' => 'Invalid pass.',
            ]);
    }

    public function test_returns_error_for_malformed_payload(): void
    {
        $response = $this->postJson('/api/scanner/validate', [
            'payload' => 'not-valid-base64!!!',
        ], [
            'X-Scanner-Token' => $this->scannerLink->token,
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'valid' => false,
            ]);
    }

    public function test_returns_invalid_for_voided_pass(): void
    {
        $pass = Pass::factory()->for($this->user)->voided()->create();

        $payload = $this->payloadService->generatePayload($pass);

        $response = $this->postJson('/api/scanner/validate', [
            'payload' => $payload,
        ], [
            'X-Scanner-Token' => $this->scannerLink->token,
        ]);

        $response->assertOk()
            ->assertJson([
                'valid' => false,
                'pass' => [
                    'id' => $pass->id,
                    'status' => 'voided',
                ],
            ]);
    }

    public function test_returns_invalid_for_redeemed_pass(): void
    {
        $pass = Pass::factory()->for($this->user)->redeemed()->create();

        $payload = $this->payloadService->generatePayload($pass);

        $response = $this->postJson('/api/scanner/validate', [
            'payload' => $payload,
        ], [
            'X-Scanner-Token' => $this->scannerLink->token,
        ]);

        $response->assertOk()
            ->assertJson([
                'valid' => false,
                'pass' => [
                    'id' => $pass->id,
                    'status' => 'redeemed',
                ],
            ]);
    }

    public function test_returns_not_found_for_cross_tenant_pass(): void
    {
        $otherUser = User::factory()->forRegionUS()->create();
        $pass = Pass::factory()->for($otherUser)->create([
            'status' => 'active',
        ]);

        $payload = $this->payloadService->generatePayload($pass);

        $response = $this->postJson('/api/scanner/validate', [
            'payload' => $payload,
        ], [
            'X-Scanner-Token' => $this->scannerLink->token,
        ]);

        $response->assertNotFound()
            ->assertJson([
                'valid' => false,
                'error' => 'Invalid pass.',
            ]);
    }

    public function test_records_scan_event_on_successful_validation(): void
    {
        $pass = Pass::factory()->for($this->user)->create([
            'status' => 'active',
        ]);

        $payload = $this->payloadService->generatePayload($pass);

        $this->postJson('/api/scanner/validate', [
            'payload' => $payload,
        ], [
            'X-Scanner-Token' => $this->scannerLink->token,
        ]);

        $this->assertDatabaseHas('scan_events', [
            'pass_id' => $pass->id,
            'user_id' => $this->user->id,
            'scanner_link_id' => $this->scannerLink->id,
            'action' => 'scan',
            'result' => 'success',
        ]);
    }

    public function test_validation_requires_payload(): void
    {
        $response = $this->postJson('/api/scanner/validate', [], [
            'X-Scanner-Token' => $this->scannerLink->token,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['payload']);
    }

    public function test_returns_custom_redemption_message_for_active_pass(): void
    {
        $pass = Pass::factory()->for($this->user)->singleUse()->withRedemptionMessage('Give customer a free coffee')->create([
            'status' => 'active',
        ]);

        $payload = $this->payloadService->generatePayload($pass);

        $response = $this->postJson('/api/scanner/validate', [
            'payload' => $payload,
        ], [
            'X-Scanner-Token' => $this->scannerLink->token,
        ]);

        $response->assertOk()
            ->assertJson([
                'valid' => true,
                'pass' => [
                    'custom_redemption_message' => 'Give customer a free coffee',
                ],
            ]);
    }
}
