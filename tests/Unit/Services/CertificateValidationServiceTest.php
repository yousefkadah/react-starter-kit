<?php

namespace Tests\Unit\Services;

use App\Services\CertificateValidationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class CertificateValidationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected CertificateValidationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(CertificateValidationService::class);
    }

    public function test_valid_apple_certificate_returns_valid_result(): void
    {
        $certContent = $this->generateCertificatePem(3650);
        $file = UploadedFile::fake()->createWithContent('certificate.cer', $certContent);

        $result = $this->service->validateAppleCertificate($file);

        $this->assertTrue($result['valid']);
        $this->assertNotEmpty($result['fingerprint']);
        $this->assertNotEmpty($result['valid_from']);
        $this->assertNotEmpty($result['expiry_date']);
    }

    public function test_apple_certificate_rejects_invalid_extension(): void
    {
        $file = UploadedFile::fake()->createWithContent('certificate.txt', 'not a cert');

        $result = $this->service->validateAppleCertificate($file);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('Certificate must be a .cer or .pem file', $result['errors'][0]);
    }

    public function test_valid_google_json_returns_valid_result(): void
    {
        $jsonContent = $this->makeGoogleServiceAccountJson();
        $file = UploadedFile::fake()->createWithContent('credentials.json', $jsonContent);

        $result = $this->service->validateGoogleJSON($file);

        $this->assertTrue($result['valid']);
        $this->assertEquals('issuer', $result['issuer_id']);
        $this->assertEquals('test-project', $result['project_id']);
    }

    public function test_google_json_rejects_missing_fields(): void
    {
        $jsonContent = json_encode([
            'type' => 'service_account',
            'project_id' => 'test-project',
        ]);

        $file = UploadedFile::fake()->createWithContent('credentials.json', $jsonContent);
        $result = $this->service->validateGoogleJSON($file);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('Missing required fields', $result['errors'][0]);
    }

    public function test_google_json_rejects_invalid_type(): void
    {
        $data = json_decode($this->makeGoogleServiceAccountJson(), true);
        $data['type'] = 'oauth2';

        $file = UploadedFile::fake()->createWithContent('credentials.json', json_encode($data));
        $result = $this->service->validateGoogleJSON($file);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('Credential type must be "service_account"', $result['errors'][0]);
    }

    private function generateCertificatePem(int $daysValid): string
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

        $certificate = openssl_csr_sign($csr, null, $privateKey, $daysValid);
        $certOut = '';
        openssl_x509_export($certificate, $certOut);

        return $certOut;
    }

    private function makeGoogleServiceAccountJson(): string
    {
        $privateKey = $this->generatePrivateKeyPem();

        return json_encode([
            'type' => 'service_account',
            'project_id' => 'test-project',
            'private_key_id' => 'key-id-123',
            'private_key' => $privateKey,
            'client_email' => 'issuer@test-project.iam.gserviceaccount.com',
            'client_id' => '123456789',
            'auth_uri' => 'https://accounts.google.com/o/oauth2/auth',
            'token_uri' => 'https://oauth2.googleapis.com/token',
            'auth_provider_x509_cert_url' => 'https://www.googleapis.com/oauth2/v1/certs',
        ]);
    }

    private function generatePrivateKeyPem(): string
    {
        $privateKey = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        $privateKeyPem = '';
        openssl_pkey_export($privateKey, $privateKeyPem);

        return $privateKeyPem;
    }
}
