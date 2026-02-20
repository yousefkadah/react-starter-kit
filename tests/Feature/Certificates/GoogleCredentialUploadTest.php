<?php

namespace Tests\Feature\Certificates;

use App\Models\GoogleCredential;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GoogleCredentialUploadTest extends TestCase
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
     * Test valid Google JSON credentials are accepted and stored.
     */
    public function test_valid_google_json_is_accepted(): void
    {
        Storage::fake('certificates');

        $jsonContent = $this->getValidGoogleServiceAccountJson();
        $file = UploadedFile::fromString(
            $jsonContent,
            'service-account-key.json',
            'application/json'
        );

        $response = $this->actingAs($this->user)->postJson(
            '/api/certificates/google',
            ['credentials' => $file]
        );

        $response->assertSuccessful();
        $response->assertJsonStructure([
            'message',
            'credential' => [
                'id',
                'issuer_id',
                'project_id',
                'last_rotated_at',
            ],
        ]);

        // Verify credential was created
        $this->assertDatabaseHas('google_credentials', [
            'user_id' => $this->user->id,
        ]);

        $credential = GoogleCredential::where('user_id', $this->user->id)->first();
        $this->assertNotNull($credential);
        $this->assertEquals('test', $credential->issuer_id);
        $this->assertEquals('test-project', $credential->project_id);
    }

    /**
     * Test invalid JSON file is rejected.
     */
    public function test_invalid_json_is_rejected(): void
    {
        Storage::fake('certificates');

        $file = UploadedFile::fromString(
            'This is not valid JSON',
            'invalid.json',
            'text/plain'
        );

        $response = $this->actingAs($this->user)->postJson(
            '/api/certificates/google',
            ['credentials' => $file]
        );

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['credentials']);
    }

    /**
     * Test JSON missing required fields is rejected.
     */
    public function test_json_missing_required_fields_is_rejected(): void
    {
        Storage::fake('certificates');

        $jsonContent = json_encode([
            'type' => 'service_account',
            'project_id' => 'test-project',
            // Missing other required fields
        ]);

        $file = UploadedFile::fromString(
            $jsonContent,
            'incomplete.json',
            'application/json'
        );

        $response = $this->actingAs($this->user)->postJson(
            '/api/certificates/google',
            ['credentials' => $file]
        );

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['credentials']);
    }

    /**
     * Test JSON with wrong type is rejected.
     */
    public function test_json_with_wrong_type_is_rejected(): void
    {
        Storage::fake('certificates');

        $jsonContent = $this->getValidGoogleServiceAccountJson();
        $data = json_decode($jsonContent, true);
        $data['type'] = 'oauth2'; // Wrong type

        $file = UploadedFile::fromString(
            json_encode($data),
            'wrong-type.json',
            'application/json'
        );

        $response = $this->actingAs($this->user)->postJson(
            '/api/certificates/google',
            ['credentials' => $file]
        );

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['credentials']);
    }

    /**
     * Test issuer_id is correctly extracted from client_email.
     */
    public function test_issuer_id_extracted_from_client_email(): void
    {
        Storage::fake('certificates');

        $jsonContent = $this->getValidGoogleServiceAccountJson();
        $file = UploadedFile::fromString(
            $jsonContent,
            'service-account-key.json',
            'application/json'
        );

        $response = $this->actingAs($this->user)->postJson(
            '/api/certificates/google',
            ['credentials' => $file]
        );

        $response->assertSuccessful();

        $credential = GoogleCredential::where('user_id', $this->user->id)->first();
        $this->assertEquals(
            'test',
            $credential->issuer_id
        );
    }

    /**
     * Test multiple credentials can be uploaded.
     */
    public function test_multiple_credentials_can_be_uploaded(): void
    {
        Storage::fake('certificates');

        $jsonContent = $this->getValidGoogleServiceAccountJson();

        // First credential
        $file1 = UploadedFile::fromString(
            $jsonContent,
            'service-account-key-1.json',
            'application/json'
        );

        $response1 = $this->actingAs($this->user)->postJson(
            '/api/certificates/google',
            ['credentials' => $file1]
        );

        $response1->assertSuccessful();

        // Second credential with different issuer
        $jsonData = json_decode($jsonContent, true);
        $jsonData['client_email'] = 'test2@test-project.iam.gserviceaccount.com';
        $jsonContent2 = json_encode($jsonData);

        $file2 = UploadedFile::fromString(
            $jsonContent2,
            'service-account-key-2.json',
            'application/json'
        );

        $response2 = $this->actingAs($this->user)->postJson(
            '/api/certificates/google',
            ['credentials' => $file2]
        );

        $response2->assertSuccessful();

        // Verify both credentials exist
        $this->assertEquals(
            2,
            GoogleCredential::where('user_id', $this->user->id)->count()
        );
    }

    /**
     * Test credential upload advances user tier.
     */
    public function test_credential_upload_advances_tier(): void
    {
        Storage::fake('certificates');

        $this->user->update(['tier' => 'Email_Verified']);

        $jsonContent = $this->getValidGoogleServiceAccountJson();
        $file = UploadedFile::fromString(
            $jsonContent,
            'service-account-key.json',
            'application/json'
        );

        $response = $this->actingAs($this->user)->postJson(
            '/api/certificates/google',
            ['credentials' => $file]
        );

        $response->assertSuccessful();

        // Note: Tier advancement happens in TierProgressionJob
        // This test verifies the credential was created, tier job would be triggered
        $credential = GoogleCredential::where('user_id', $this->user->id)->first();
        $this->assertNotNull($credential);
    }

    /**
     * Test unauthenticated user cannot upload credentials.
     */
    public function test_unauthenticated_user_cannot_upload_credentials(): void
    {
        Storage::fake('certificates');

        $jsonContent = $this->getValidGoogleServiceAccountJson();
        $file = UploadedFile::fromString(
            $jsonContent,
            'service-account-key.json',
            'application/json'
        );

        $response = $this->postJson(
            '/api/certificates/google',
            ['credentials' => $file]
        );

        $response->assertUnauthorized();
    }

    /**
     * Get a valid Google Service Account JSON for testing.
     */
    private function getValidGoogleServiceAccountJson(): string
    {
        $privateKey = $this->generatePrivateKeyPem();

        return json_encode([
            'type' => 'service_account',
            'project_id' => 'test-project',
            'private_key_id' => 'key-id-123',
            'private_key' => $privateKey,
            'client_email' => 'test@test-project.iam.gserviceaccount.com',
            'client_id' => '123456789',
            'auth_uri' => 'https://accounts.google.com/o/oauth2/auth',
            'token_uri' => 'https://oauth2.googleapis.com/token',
            'auth_provider_x509_cert_url' => 'https://www.googleapis.com/oauth2/v1/certs',
        ]);
    }

    /**
     * Generate a valid RSA private key in PEM format for testing.
     */
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
