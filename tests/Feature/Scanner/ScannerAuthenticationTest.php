<?php

namespace Tests\Feature\Scanner;

use App\Models\ScannerLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScannerAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_scanner_page_loads_with_valid_token(): void
    {
        $user = User::factory()->forRegionUS()->create();
        $scannerLink = ScannerLink::factory()->for($user)->create();

        $response = $this->get(route('scanner.show', $scannerLink->token));

        $response->assertOk();
    }

    public function test_scanner_page_returns_404_with_invalid_token(): void
    {
        $response = $this->get(route('scanner.show', 'invalid-token-12345'));

        $response->assertNotFound();
    }

    public function test_scanner_page_returns_404_with_inactive_token(): void
    {
        $user = User::factory()->forRegionUS()->create();
        $scannerLink = ScannerLink::factory()->for($user)->inactive()->create();

        $response = $this->get(route('scanner.show', $scannerLink->token));

        $response->assertNotFound();
    }

    public function test_scanner_api_returns_401_without_token_header(): void
    {
        $response = $this->postJson('/api/scanner/validate', [
            'payload' => 'some-payload',
        ]);

        $response->assertUnauthorized();
    }

    public function test_scanner_api_returns_401_with_invalid_token_header(): void
    {
        $response = $this->postJson('/api/scanner/validate', [
            'payload' => 'some-payload',
        ], [
            'X-Scanner-Token' => 'invalid-token',
        ]);

        $response->assertUnauthorized();
    }

    public function test_scanner_api_returns_401_with_inactive_token_header(): void
    {
        $user = User::factory()->forRegionUS()->create();
        $scannerLink = ScannerLink::factory()->for($user)->inactive()->create();

        $response = $this->postJson('/api/scanner/validate', [
            'payload' => 'some-payload',
        ], [
            'X-Scanner-Token' => $scannerLink->token,
        ]);

        $response->assertUnauthorized();
    }

    public function test_scanner_page_updates_last_used_at_on_access(): void
    {
        $user = User::factory()->forRegionUS()->create();
        $scannerLink = ScannerLink::factory()->for($user)->create([
            'last_used_at' => null,
        ]);

        $this->assertNull($scannerLink->last_used_at);

        $this->get(route('scanner.show', $scannerLink->token));

        $scannerLink->refresh();
        $this->assertNotNull($scannerLink->last_used_at);
    }
}
