<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Services\AppleCSRService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppleCSRServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_csr_returns_pem(): void
    {
        $user = User::factory()->create([
            'name' => 'Acme Corp',
            'email' => 'ops@acme.com',
            'region' => 'US',
        ]);

        $service = app(AppleCSRService::class);
        $csr = $service->generateCSR($user);

        $this->assertStringContainsString('-----BEGIN CERTIFICATE REQUEST-----', $csr);
        $this->assertStringContainsString('-----END CERTIFICATE REQUEST-----', $csr);
    }

    public function test_download_csr_returns_attachment(): void
    {
        $service = app(AppleCSRService::class);
        $response = $service->downloadCSR('test-csr-content');

        $this->assertEquals('application/octet-stream', $response->headers->get('Content-Type'));
        $this->assertEquals('attachment; filename="cert.certSigningRequest"', $response->headers->get('Content-Disposition'));
    }

    public function test_instructions_contain_apple_portal_steps(): void
    {
        $service = app(AppleCSRService::class);
        $instructions = $service->getAppleInstructions();

        $this->assertStringContainsString('Apple Developer Portal', $instructions);
        $this->assertStringContainsString('cert.certSigningRequest', $instructions);
    }
}
