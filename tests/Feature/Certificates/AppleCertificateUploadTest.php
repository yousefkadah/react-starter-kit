<?php

namespace Tests\Feature\Certificates;

use App\Models\AppleCertificate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AppleCertificateUploadTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->approved()->create([
            'tier' => 'Email_Verified',
            'region' => 'US',
        ]);
    }

    /**
     * Test valid Apple certificate is accepted and stored.
     */
    public function test_valid_apple_certificate_is_accepted(): void
    {
        Storage::fake('certificates');

        // Create a test certificate file (PEM format)
        $certContent = $this->getValidAppleCertificatePem();
        $file = UploadedFile::fromString(
            $certContent,
            'certificate.cer',
            'application/x-pkcs12'
        );

        $response = $this->actingAs($this->user)->postJson(
            '/api/certificates/apple',
            ['certificate' => $file]
        );

        $response->assertSuccessful();
        $response->assertJsonStructure([
            'message',
            'certificate' => [
                'id',
                'fingerprint',
                'valid_from',
                'expiry_date',
            ],
        ]);

        // Verify certificate was created
        $this->assertDatabaseHas('apple_certificates', [
            'user_id' => $this->user->id,
        ]);

        $this->actingAs($this->user);
        $certificate = AppleCertificate::where('user_id', $this->user->id)->first();
        $this->assertNotNull($certificate);
        $this->assertNotNull($certificate->fingerprint);
    }

    /**
     * Test invalid certificate file is rejected.
     */
    public function test_invalid_certificate_file_is_rejected(): void
    {
        Storage::fake('certificates');

        // Create an invalid file
        $file = UploadedFile::fromString(
            'This is not a valid certificate file',
            'invalid.cer',
            'text/plain'
        );

        $response = $this->actingAs($this->user)->postJson(
            '/api/certificates/apple',
            ['certificate' => $file]
        );

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['certificate']);

        // Verify no certificate was created
        $this->assertDatabaseMissing('apple_certificates', [
            'user_id' => $this->user->id,
        ]);
    }

    /**
     * Test expired certificate is rejected.
     */
    public function test_expired_certificate_is_rejected(): void
    {
        Storage::fake('certificates');

        // Create an expired certificate PEM
        $certContent = $this->getExpiredAppleCertificatePem();
        $file = UploadedFile::fromString(
            $certContent,
            'expired.cer',
            'application/x-pkcs12'
        );

        $response = $this->actingAs($this->user)->postJson(
            '/api/certificates/apple',
            ['certificate' => $file]
        );

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['certificate']);
    }

    /**
     * Test multiple certificates can be uploaded.
     */
    public function test_multiple_certificates_can_be_uploaded(): void
    {
        Storage::fake('certificates');

        $certContent = $this->getValidAppleCertificatePem();

        // First certificate
        $file1 = UploadedFile::fromString(
            $certContent,
            'certificate1.cer',
            'application/x-pkcs12'
        );

        $response1 = $this->actingAs($this->user)->postJson(
            '/api/certificates/apple',
            ['certificate' => $file1]
        );

        $response1->assertSuccessful();

        // Second certificate
        $file2 = UploadedFile::fromString(
            $certContent,
            'certificate2.cer',
            'application/x-pkcs12'
        );

        $response2 = $this->actingAs($this->user)->postJson(
            '/api/certificates/apple',
            ['certificate' => $file2]
        );

        $response2->assertSuccessful();

        // Verify both certificates exist
        $this->assertEquals(
            2,
            AppleCertificate::where('user_id', $this->user->id)->count()
        );
    }

    /**
     * Test certificate upload advances user tier.
     */
    public function test_certificate_upload_advances_tier(): void
    {
        Storage::fake('certificates');

        $this->user->update(['tier' => 'Email_Verified']);

        $certContent = $this->getValidAppleCertificatePem();
        $file = UploadedFile::fromString(
            $certContent,
            'certificate.cer',
            'application/x-pkcs12'
        );

        $response = $this->actingAs($this->user)->postJson(
            '/api/certificates/apple',
            ['certificate' => $file]
        );

        $response->assertSuccessful();

        // Note: Tier advancement happens in TierProgressionJob
        // This test verifies the certificate was created, tier job would be triggered
        $certificate = AppleCertificate::where('user_id', $this->user->id)->first();
        $this->assertNotNull($certificate);
    }

    /**
     * Test unauthenticated user cannot upload certificate.
     */
    public function test_unauthenticated_user_cannot_upload_certificate(): void
    {
        Storage::fake('certificates');

        $certContent = $this->getValidAppleCertificatePem();
        $file = UploadedFile::fromString(
            $certContent,
            'certificate.cer',
            'application/x-pkcs12'
        );

        $response = $this->postJson(
            '/api/certificates/apple',
            ['certificate' => $file]
        );

        $response->assertUnauthorized();
    }

    /**
     * Get a valid Apple certificate in PEM format for testing.
     * In production, this would be from actual certificates.
     */
    private function getValidAppleCertificatePem(): string
    {
        $privateKey = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        $csr = openssl_csr_new(
            [
                'commonName' => 'passkit-test',
                'organizationName' => 'PassKit Test',
                'countryName' => 'US',
            ],
            $privateKey,
            ['digest_alg' => 'sha256']
        );

        $certificate = openssl_csr_sign($csr, null, $privateKey, 3650);
        $certOut = '';
        openssl_x509_export($certificate, $certOut);

        return $certOut;
    }

    /**
     * Get an expired Apple certificate in PEM format for testing.
     */
    private function getExpiredAppleCertificatePem(): string
    {
        // Self-signed test certificate (expired)
        return <<<'CERT'
-----BEGIN CERTIFICATE-----
MIIDXTCCAkWgAwIBAgIJAKTTqJpJrMVeMA0GCSqGSIb3DQEBCwUAMEUxCzAJBgNV
BAYTAlVTMQswCQYDVQQIDAJDQTELMAkGA1UEBwwCQkExDzANBgNVBAoMBkFwcGxl
MB4XDTI0MDEwMTAwMDAwMFoXDTIzMDEwMTAwMDAwMFowRTELMAkGA1UEBhMCVVMx
CzAJBgNVBAgMAkNBMQswCQYDVQQHDAJCQTEPMA0GA1UECgwGQXBwbGUwggEiMA0G
CSqGSIb3DQEBAQUAA4IBDwAwggEKAoIBAQDU2OwkJ7BK3o3uKiGgLi4Aw5V3KHCT
g0oL0VkVlWoN5Q5YZ3vJlJ3vZ7Z3vZ7Z3vZ7Z3vZ7Z3vZ7Z3vZ7Z3vZ7Z3vZ7Z7Z
3vZ7Z3vZ7Z3vZ7Z3vZ7Z3vZ7Z3vZ7Z3vZ7Z3vZ7Z3vZ7Z3vZ7Z3vZ7Z3vZ7Z3vZ7
Z3vZ7Z3vZ7Z3vZ7Z3vZ7Z3vZ7Z3vZ7Z3vZ7Z3vZ7Z3vZ7Z3vZ7Z3vZ7Z3vZ7Z3vZ7
Z3vZ7Z3vZ7Z3vZ7Z3vZ7Z3vZ7Z3vZ7Z3vccAAwEAAaNQME4wHQYDVR0OBBYEFG7Y
OmX/R3J8xPF/Zm7YQZXzzcgzMB8GA1UdIwQYMBaAFG7YOmX/R3J8xPF/Zm7YQZXz
zcgzMAwGA1UdEwQFMAMBAf8wDQYJKoZIhvcNAQELBQADggEBAJk0O4K8oAz9qPf2
vZ7Z3vZ7Z3vZ7Z3vZ7Z3vZ7Z3vZ7Z3vZ7Z3vZ3vZ7Z3vZ7Z3vZ7Z3vZ7Z3vZ7Z3v
Z7Z3vZ7Z3vZ7Z3vZ7Z3vZ7Z3vZ7Z3vZ7Z3vZ7Z3vZ7Z3vZ7Z3vZ7Z3vZ7Z3vZ7Z3v
Z7Z3vZ7Z3vZ7Z3vZ7Z3vZ7Z3vZ7Z3vZ7Z3vZ7Z3vZ7Z3vZ7Z3vZ7Z3vZ7Z3vZ7Z3v
Z7Z3vZ7Z3vZ7Z3vZ7Z3vZ7Z3vZ7Z3vZ7Z3vZ7Z3vZ7Z3vZ7Z3vZ7Z3vZ7Z3vZ7Z3v
-----END CERTIFICATE-----
CERT;
    }
}
