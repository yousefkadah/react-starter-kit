<?php

namespace Tests\Feature\PushNotification;

use App\Models\GoogleCredential;
use App\Models\User;
use App\Services\GooglePassService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class GoogleWalletUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_google_patch_sends_expected_payload_and_returns_response(): void
    {
        Http::fake([
            'https://walletobjects.googleapis.com/*' => Http::response(['id' => 'issuer.object-1', 'state' => 'ACTIVE'], 200),
        ]);

        $service = new class extends GooglePassService
        {
            public function __construct()
            {
                parent::__construct([
                    'client_email' => 'service-account@example.test',
                    'private_key' => "-----BEGIN PRIVATE KEY-----\nplaceholder\n-----END PRIVATE KEY-----",
                ], 'issuer', 'PassKit Test');
            }

            protected function getAccessToken(): string
            {
                return 'fake-token';
            }

            protected function guardDailyObjectUpdateLimit(string $objectId): void {}
        };

        $response = $service->patchObject('issuer.object-1', ['state' => 'ACTIVE']);

        $this->assertSame('issuer.object-1', $response['id']);

        Http::assertSent(function ($request): bool {
            return $request->method() === 'PATCH'
                && str_contains($request->url(), '/genericObject/issuer.object-1')
                && $request['state'] === 'ACTIVE';
        });
    }

    public function test_google_patch_throws_for_non_404_failure(): void
    {
        Http::fake([
            'https://walletobjects.googleapis.com/*' => Http::response(['error' => 'forbidden'], 403),
        ]);

        $service = new class extends GooglePassService
        {
            public function __construct()
            {
                parent::__construct([
                    'client_email' => 'service-account@example.test',
                    'private_key' => "-----BEGIN PRIVATE KEY-----\nplaceholder\n-----END PRIVATE KEY-----",
                ], 'issuer', 'PassKit Test');
            }

            protected function getAccessToken(): string
            {
                return 'fake-token';
            }

            protected function guardDailyObjectUpdateLimit(string $objectId): void {}
        };

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to patch Google Wallet object');

        $service->patchObject('issuer.object-2', ['state' => 'ACTIVE']);
    }

    public function test_for_user_loads_user_specific_google_credentials(): void
    {
        $user = User::factory()->create([
            'google_service_account_json' => json_encode([
                'client_email' => 'user-service@example.test',
                'private_key' => 'user-private-key',
            ]),
            'google_issuer_id' => 'issuer-from-user',
        ]);

        GoogleCredential::factory()->create([
            'user_id' => $user->id,
            'issuer_id' => 'issuer-from-credential',
            'private_key' => Crypt::encryptString('credential-private-key'),
        ]);

        $service = GooglePassService::forUser($user);

        $reflection = new \ReflectionClass($service);

        $issuerProperty = $reflection->getProperty('issuerId');
        $issuerProperty->setAccessible(true);
        $serviceAccountProperty = $reflection->getProperty('serviceAccount');
        $serviceAccountProperty->setAccessible(true);

        $this->assertSame('issuer-from-user', $issuerProperty->getValue($service));
        $this->assertSame('credential-private-key', $serviceAccountProperty->getValue($service)['private_key']);
    }

    public function test_google_object_daily_push_limit_is_enforced_at_three(): void
    {
        Http::fake([
            'https://walletobjects.googleapis.com/*' => Http::response(['id' => 'issuer.object-limit'], 200),
        ]);

        $service = new class extends GooglePassService
        {
            public function __construct()
            {
                parent::__construct([
                    'client_email' => 'service-account@example.test',
                    'private_key' => "-----BEGIN PRIVATE KEY-----\nplaceholder\n-----END PRIVATE KEY-----",
                ], 'issuer', 'PassKit Test');
            }

            protected function getAccessToken(): string
            {
                return 'fake-token';
            }
        };

        $service->patchObject('issuer.object-limit', ['state' => 'ACTIVE']);
        $service->patchObject('issuer.object-limit', ['state' => 'ACTIVE']);
        $service->patchObject('issuer.object-limit', ['state' => 'ACTIVE']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('daily limit reached');

        $service->patchObject('issuer.object-limit', ['state' => 'ACTIVE']);
    }
}
