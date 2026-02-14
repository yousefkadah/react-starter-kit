<?php

namespace Tests\Feature\AccountSetup;

use App\Models\BusinessDomain;
use App\Models\User;
use App\Services\TierProgressionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class FullUserJourneyTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_user_journey_advances_tier(): void
    {
        Mail::fake();

        User::factory()->admin()->create([
            'email' => 'admin@example.com',
        ]);

        BusinessDomain::create(['domain' => 'stripe.com']);

        $response = $this->postJson('/api/signup', [
            'name' => 'Acme Corp',
            'email' => 'ops@stripe.com',
            'password' => 'Password123!@#',
            'password_confirmation' => 'Password123!@#',
            'region' => 'US',
            'industry' => 'Retail',
            'agree_terms' => true,
        ]);

        $response->assertCreated();

        $user = User::where('email', 'ops@stripe.com')->firstOrFail();
        $this->assertEquals('approved', $user->approval_status);

        $appleCert = UploadedFile::fake()->createWithContent(
            'certificate.cer',
            $this->generateCertificatePem(3650)
        );

        $this->actingAs($user)->postJson('/api/certificates/apple', [
            'certificate' => $appleCert,
        ])->assertCreated();

        $googleCredentials = UploadedFile::fake()->createWithContent(
            'credentials.json',
            $this->makeGoogleServiceAccountJson()
        );

        $this->actingAs($user)->postJson('/api/certificates/google', [
            'credentials' => $googleCredentials,
        ])->assertCreated();

        app(TierProgressionService::class)->evaluateAndAdvanceTier($user);

        $user->refresh();
        $this->assertEquals('Verified_And_Configured', $user->tier);
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
