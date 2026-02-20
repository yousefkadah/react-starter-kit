<?php

namespace Tests\Feature\PushNotification;

use App\Models\DeviceRegistration;
use App\Models\Pass;
use App\Models\PassTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_lifecycle_create_update_deactivate_and_delete(): void
    {
        [$user, $pass] = $this->createApplePass();

        $this->withHeaders($this->appleAuthHeader($pass))
            ->postJson($this->registrationUrl('device-lib-1', $user->apple_pass_type_id, $pass->serial_number), [
                'pushToken' => 'push-token-1',
            ])
            ->assertCreated();

        $this->withHeaders($this->appleAuthHeader($pass))
            ->postJson($this->registrationUrl('device-lib-1', $user->apple_pass_type_id, $pass->serial_number), [
                'pushToken' => 'push-token-2',
            ])
            ->assertOk();

        /** @var DeviceRegistration $registration */
        $registration = DeviceRegistration::query()->firstOrFail();
        $registration->update(['is_active' => false]);

        $this->assertFalse($registration->fresh()->is_active);
        $this->assertCount(0, DeviceRegistration::query()->active()->get());

        $this->withHeaders($this->appleAuthHeader($pass))
            ->deleteJson($this->registrationUrl('device-lib-1', $user->apple_pass_type_id, $pass->serial_number))
            ->assertOk();

        $this->assertDatabaseMissing('device_registrations', [
            'id' => $registration->id,
        ]);
    }

    public function test_auth_token_validation_rejects_mismatched_tokens(): void
    {
        [$user, $pass] = $this->createApplePass();

        $this->withHeaders([
            'Authorization' => 'ApplePass wrong-token',
        ])->postJson($this->registrationUrl('device-lib-1', $user->apple_pass_type_id, $pass->serial_number), [
            'pushToken' => 'push-token-1',
        ])->assertUnauthorized();
    }

    public function test_non_existent_serial_number_is_rejected_with_unauthorized(): void
    {
        [$user, $pass] = $this->createApplePass();

        $this->withHeaders($this->appleAuthHeader($pass))
            ->postJson($this->registrationUrl('device-lib-1', $user->apple_pass_type_id, 'missing-serial'), [
                'pushToken' => 'push-token-1',
            ])
            ->assertUnauthorized();
    }

    public function test_tenant_isolation_rejects_other_users_pass(): void
    {
        [$userA, $passA] = $this->createApplePass('serial-apple-001', 'token-111111');
        [$userB, $passB] = $this->createApplePass('serial-apple-002', 'token-222222');

        $this->assertNotSame($userA->id, $userB->id);

        $this->withHeaders($this->appleAuthHeader($passA))
            ->postJson($this->registrationUrl('device-lib-1', $userA->apple_pass_type_id, $passB->serial_number), [
                'pushToken' => 'push-token-1',
            ])
            ->assertUnauthorized();
    }

    /**
     * @return array{0: User, 1: Pass}
     */
    private function createApplePass(string $serial = 'serial-apple-001', string $token = 'auth-token-123456'): array
    {
        /** @var User $user */
        $user = User::factory()->forRegionUS()->create([
            'apple_pass_type_id' => 'pass.com.example.test',
        ]);

        $template = PassTemplate::factory()->for($user)->create();

        /** @var Pass $pass */
        $pass = Pass::factory()->for($user)->apple()->create([
            'pass_template_id' => $template->id,
            'pass_data' => $template->design_data,
            'authentication_token' => $token,
            'serial_number' => $serial,
        ]);

        return [$user, $pass];
    }

    /**
     * @return array<string, string>
     */
    private function appleAuthHeader(Pass $pass): array
    {
        return [
            'Authorization' => 'ApplePass '.$pass->authentication_token,
        ];
    }

    private function registrationUrl(string $deviceLibraryIdentifier, string $passTypeIdentifier, string $serialNumber): string
    {
        return "/api/apple/v1/devices/{$deviceLibraryIdentifier}/registrations/{$passTypeIdentifier}/{$serialNumber}";
    }
}
