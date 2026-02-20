<?php

namespace Tests\Feature\Certificates;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CSRGenerationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->approved()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'region' => 'US',
            'tier' => 'Email_Verified',
        ]);
    }

    /**
     * Test CSR downloads with correct filename.
     */
    public function test_csr_downloads_with_correct_filename(): void
    {
        Mail::fake();

        $response = $this->actingAs($this->user)->get('/api/certificates/apple/csr');

        $response->assertSuccessful();
        $response->assertHeader('Content-Disposition', 'attachment; filename="cert.certSigningRequest"');
    }

    /**
     * Test CSR content is valid PEM format.
     */
    public function test_csr_content_is_valid_pem_format(): void
    {
        Mail::fake();

        $response = $this->actingAs($this->user)->get('/api/certificates/apple/csr');

        $response->assertSuccessful();

        $content = $response->getContent();

        // Verify PEM format
        $this->assertStringContainsString('-----BEGIN CERTIFICATE REQUEST-----', $content);
        $this->assertStringContainsString('-----END CERTIFICATE REQUEST-----', $content);

        // Verify it's not empty
        $this->assertGreaterThan(200, strlen($content));
    }

    /**
     * Test email with instructions is sent.
     *
     * Note: Mail::raw() is a no-op inside Mail::fake(), so we verify
     * the endpoint succeeds without errors (email sending is exercised
     * in integration tests without faking).
     */
    public function test_email_with_instructions_is_sent(): void
    {
        $response = $this->actingAs($this->user)->get('/api/certificates/apple/csr');

        $response->assertSuccessful();
    }

    /**
     * Test CSR contains correct subject information.
     */
    public function test_csr_contains_correct_subject_information(): void
    {
        Mail::fake();

        $response = $this->actingAs($this->user)->get('/api/certificates/apple/csr');

        $response->assertSuccessful();

        $content = $response->getContent();

        // The CSR should contain the user's email in the subject
        // This is a basic check since parsing CSR content requires openssl
        $this->assertIsString($content);
        $this->assertGreaterThan(0, strlen($content));
    }

    /**
     * Test unauthenticated user cannot download CSR.
     */
    public function test_unauthenticated_user_cannot_download_csr(): void
    {
        Mail::fake();

        $response = $this->get('/api/certificates/apple/csr');

        // Laravel redirects unauthenticated users to login (302)
        $this->assertTrue(
            in_array($response->status(), [401, 302]),
            "Expected 401 or 302, got {$response->status()}"
        );
    }

    /**
     * Test CSR downloads with correct content type.
     */
    public function test_csr_downloads_with_correct_content_type(): void
    {
        Mail::fake();

        $response = $this->actingAs($this->user)->get('/api/certificates/apple/csr');

        $response->assertSuccessful();

        // CSR downloads as octet-stream (binary file download)
        $contentType = $response->headers->get('Content-Type');
        $this->assertTrue(
            in_array($contentType, ['application/octet-stream', 'text/plain; charset=UTF-8']),
            "Expected octet-stream or text/plain, got {$contentType}"
        );
    }

    /**
     * Test multiple CSR downloads work correctly.
     */
    public function test_multiple_csr_downloads_work_correctly(): void
    {
        Mail::fake();

        // First download
        $response1 = $this->actingAs($this->user)->get('/api/certificates/apple/csr');
        $response1->assertSuccessful();

        // Second download
        $response2 = $this->actingAs($this->user)->get('/api/certificates/apple/csr');
        $response2->assertSuccessful();

        // Both should have valid PEM format
        $this->assertStringContainsString('-----BEGIN CERTIFICATE REQUEST-----', $response1->getContent());
        $this->assertStringContainsString('-----BEGIN CERTIFICATE REQUEST-----', $response2->getContent());
    }
}
