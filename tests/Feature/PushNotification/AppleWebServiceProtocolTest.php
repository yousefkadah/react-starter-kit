<?php

namespace Tests\Feature\PushNotification;

use App\Models\DeviceRegistration;
use App\Models\Pass;
use App\Models\PassTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AppleWebServiceProtocolTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_device_returns_created_for_new_registration(): void
    {
        [$user, $pass] = $this->createApplePass();

        $response = $this->withHeaders($this->appleAuthHeader($pass))
            ->postJson($this->registrationUrl('device-lib-1', $user->apple_pass_type_id, $pass->serial_number), [
                'pushToken' => 'push-token-1',
            ]);

        $response->assertCreated();

        $this->assertDatabaseHas('device_registrations', [
            'device_library_identifier' => 'device-lib-1',
            'pass_type_identifier' => $user->apple_pass_type_id,
            'serial_number' => $pass->serial_number,
            'push_token' => 'push-token-1',
            'user_id' => $user->id,
            'is_active' => true,
        ]);
    }

    public function test_register_device_returns_ok_for_existing_registration_and_updates_push_token(): void
    {
        [$user, $pass] = $this->createApplePass();

        DeviceRegistration::query()->create([
            'device_library_identifier' => 'device-lib-1',
            'push_token' => 'old-token',
            'pass_type_identifier' => $user->apple_pass_type_id,
            'serial_number' => $pass->serial_number,
            'user_id' => $user->id,
            'is_active' => true,
        ]);

        $response = $this->withHeaders($this->appleAuthHeader($pass))
            ->postJson($this->registrationUrl('device-lib-1', $user->apple_pass_type_id, $pass->serial_number), [
                'pushToken' => 'new-token',
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('device_registrations', [
            'device_library_identifier' => 'device-lib-1',
            'serial_number' => $pass->serial_number,
            'push_token' => 'new-token',
            'is_active' => true,
        ]);
    }

    public function test_unregister_device_returns_ok(): void
    {
        [$user, $pass] = $this->createApplePass();

        DeviceRegistration::query()->create([
            'device_library_identifier' => 'device-lib-1',
            'push_token' => 'push-token-1',
            'pass_type_identifier' => $user->apple_pass_type_id,
            'serial_number' => $pass->serial_number,
            'user_id' => $user->id,
            'is_active' => true,
        ]);

        $response = $this->withHeaders($this->appleAuthHeader($pass))
            ->deleteJson($this->registrationUrl('device-lib-1', $user->apple_pass_type_id, $pass->serial_number));

        $response->assertOk();

        $this->assertDatabaseMissing('device_registrations', [
            'device_library_identifier' => 'device-lib-1',
            'serial_number' => $pass->serial_number,
        ]);
    }

    public function test_get_updated_passes_returns_serial_numbers_and_last_updated(): void
    {
        [$user, $pass] = $this->createApplePass();

        DeviceRegistration::query()->create([
            'device_library_identifier' => 'device-lib-1',
            'push_token' => 'push-token-1',
            'pass_type_identifier' => $user->apple_pass_type_id,
            'serial_number' => $pass->serial_number,
            'user_id' => $user->id,
            'is_active' => true,
        ]);

        $since = now()->subMinute()->timestamp;

        $response = $this->getJson(
            "/api/apple/v1/devices/device-lib-1/registrations/{$user->apple_pass_type_id}?passesUpdatedSince={$since}"
        );

        $response
            ->assertOk()
            ->assertJsonStructure([
                'serialNumbers',
                'lastUpdated',
            ])
            ->assertJsonFragment([
                'serialNumbers' => [$pass->serial_number],
            ]);
    }

    public function test_get_updated_passes_returns_no_content_when_no_updates_exist(): void
    {
        [$user, $pass] = $this->createApplePass();

        DeviceRegistration::query()->create([
            'device_library_identifier' => 'device-lib-1',
            'push_token' => 'push-token-1',
            'pass_type_identifier' => $user->apple_pass_type_id,
            'serial_number' => $pass->serial_number,
            'user_id' => $user->id,
            'is_active' => true,
        ]);

        $since = now()->addMinute()->timestamp;

        $response = $this->getJson(
            "/api/apple/v1/devices/device-lib-1/registrations/{$user->apple_pass_type_id}?passesUpdatedSince={$since}"
        );

        $response->assertNoContent();
    }

    public function test_get_latest_pass_returns_pkpass_binary(): void
    {
        [$user, $pass] = $this->createApplePass();

        $disk = config('passkit.storage.passes_disk', 'local');
        Storage::fake($disk);

        $pkpassPath = 'passes/pass-'.$pass->serial_number.'.pkpass';
        Storage::disk($disk)->put($pkpassPath, 'pkpass-binary');

        $pass->forceFill([
            'pkpass_path' => $pkpassPath,
            'updated_at' => now(),
        ])->save();

        $response = $this->withHeaders($this->appleAuthHeader($pass))
            ->get($this->latestPassUrl($user->apple_pass_type_id, $pass->serial_number));

        $response
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.apple.pkpass');
    }

    public function test_get_latest_pass_returns_not_modified_when_if_modified_since_is_current(): void
    {
        [$user, $pass] = $this->createApplePass();

        $disk = config('passkit.storage.passes_disk', 'local');
        Storage::fake($disk);

        $pkpassPath = 'passes/pass-'.$pass->serial_number.'.pkpass';
        Storage::disk($disk)->put($pkpassPath, 'pkpass-binary');

        $pass->forceFill([
            'pkpass_path' => $pkpassPath,
            'updated_at' => now(),
        ])->save();

        $header = Carbon::parse($pass->updated_at)->addSecond()->toRfc7231String();

        $response = $this->withHeaders(array_merge(
            $this->appleAuthHeader($pass),
            ['If-Modified-Since' => $header],
        ))->get($this->latestPassUrl($user->apple_pass_type_id, $pass->serial_number));

        $response->assertStatus(304);
    }

    public function test_get_latest_pass_returns_unauthorized_with_bad_token(): void
    {
        [$user, $pass] = $this->createApplePass();

        $response = $this->withHeaders([
            'Authorization' => 'ApplePass invalid-token',
        ])->get($this->latestPassUrl($user->apple_pass_type_id, $pass->serial_number));

        $response->assertUnauthorized();
    }

    public function test_log_errors_returns_ok(): void
    {
        $response = $this->postJson('/api/apple/v1/log', [
            'logs' => ['Error 1', 'Error 2'],
        ]);

        $response->assertOk();
    }

    /**
     * @return array{0: User, 1: Pass}
     */
    private function createApplePass(): array
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
            'authentication_token' => 'auth-token-123456',
            'serial_number' => 'serial-apple-001',
            'updated_at' => now(),
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

    private function latestPassUrl(string $passTypeIdentifier, string $serialNumber): string
    {
        return "/api/apple/v1/passes/{$passTypeIdentifier}/{$serialNumber}";
    }
}
