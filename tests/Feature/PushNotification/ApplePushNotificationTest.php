<?php

namespace Tests\Feature\PushNotification;

use App\Models\AppleCertificate;
use App\Models\DeviceRegistration;
use App\Models\User;
use App\Services\ApplePushService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class ApplePushNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_apns_push_sends_empty_payload_and_returns_true_on_success(): void
    {
        Http::fake([
            'https://api.development.push.apple.com/*' => Http::response('', 200),
        ]);

        $service = new ApplePushService('/tmp/test-cert.pem', '', 'sandbox');

        $this->assertTrue($service->sendPush('device-token-1', 'pass.com.example.test'));

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://api.development.push.apple.com:443/3/device/device-token-1'
                || $request->url() === 'https://api.development.push.apple.com/3/device/device-token-1';
        });

        Http::assertSent(function ($request): bool {
            return trim($request->body()) === '{}';
        });
    }

    public function test_apns_410_marks_registration_inactive(): void
    {
        $user = User::factory()->create();

        DeviceRegistration::factory()->create([
            'user_id' => $user->id,
            'push_token' => 'inactive-token',
            'is_active' => true,
        ]);

        Http::fake([
            'https://api.push.apple.com/*' => Http::response(['reason' => 'Unregistered'], 410),
        ]);

        $service = new ApplePushService('/tmp/test-cert.pem', '', 'production');

        $this->assertFalse($service->sendPush('inactive-token', 'pass.com.example.test'));

        $this->assertDatabaseHas('device_registrations', [
            'push_token' => 'inactive-token',
            'is_active' => false,
        ]);
    }

    public function test_apns_429_throws_runtime_exception(): void
    {
        Http::fake([
            'https://api.push.apple.com/*' => Http::response(['reason' => 'TooManyRequests'], 429),
        ]);

        $service = new ApplePushService('/tmp/test-cert.pem', '', 'production');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('APNS rate limit reached');

        $service->sendPush('device-token-2', 'pass.com.example.test');
    }

    public function test_for_user_loads_user_certificate_path(): void
    {
        $user = User::factory()->create();

        AppleCertificate::factory()->create([
            'user_id' => $user->id,
            'path' => 'certificates/user-cert.p12',
            'status' => 'active',
        ]);

        $service = ApplePushService::forUser($user);

        $reflection = new \ReflectionClass($service);
        $property = $reflection->getProperty('certificatePath');
        $property->setAccessible(true);

        $certificatePath = (string) $property->getValue($service);

        $this->assertStringContainsString('certificates/user-cert.p12', $certificatePath);
    }

    public function test_apns_rate_limit_is_enforced(): void
    {
        config()->set('passkit.push.rate_limit_per_second', 1);

        Http::fake([
            'https://api.push.apple.com/*' => Http::response('', 200),
        ]);

        $service = new ApplePushService('/tmp/test-cert.pem', '', 'production');

        $this->assertTrue($service->sendPush('device-token-3', 'pass.com.example.test'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('APNS rate limit reached');

        $service->sendPush('device-token-4', 'pass.com.example.test');
    }
}
