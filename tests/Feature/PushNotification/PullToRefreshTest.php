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

class PullToRefreshTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_latest_pass_returns_updated_pkpass_when_modified(): void
    {
        [$user, $pass] = $this->createApplePass();

        $disk = config('passkit.storage.passes_disk', 'local');
        Storage::fake($disk);

        $pkpassPath = 'passes/pass-'.$pass->serial_number.'.pkpass';
        Storage::disk($disk)->put($pkpassPath, 'updated-binary');

        $pass->forceFill([
            'pkpass_path' => $pkpassPath,
            'updated_at' => now(),
        ])->save();

        $response = $this->withHeaders(array_merge(
            $this->appleAuthHeader($pass),
            ['If-Modified-Since' => now()->subHour()->toRfc7231String()],
        ))->get($this->latestPassUrl($user->apple_pass_type_id, $pass->serial_number));

        $response->assertOk();
        $response->assertHeader('Last-Modified');
    }

    public function test_get_latest_pass_returns_304_when_not_modified(): void
    {
        [$user, $pass] = $this->createApplePass();

        $disk = config('passkit.storage.passes_disk', 'local');
        Storage::fake($disk);

        $pkpassPath = 'passes/pass-'.$pass->serial_number.'.pkpass';
        Storage::disk($disk)->put($pkpassPath, 'updated-binary');

        $pass->forceFill([
            'pkpass_path' => $pkpassPath,
            'updated_at' => now(),
        ])->save();

        $response = $this->withHeaders(array_merge(
            $this->appleAuthHeader($pass),
            ['If-Modified-Since' => Carbon::parse($pass->updated_at)->addSecond()->toRfc7231String()],
        ))->get($this->latestPassUrl($user->apple_pass_type_id, $pass->serial_number));

        $response->assertStatus(304);
    }

    public function test_passes_updated_since_returns_only_newer_serial_numbers(): void
    {
        /** @var User $user */
        $user = User::factory()->forRegionUS()->create([
            'apple_pass_type_id' => 'pass.com.example.test',
        ]);

        $template = PassTemplate::factory()->for($user)->create();

        $olderPass = Pass::factory()->for($user)->apple()->create([
            'pass_template_id' => $template->id,
            'serial_number' => 'serial-old',
            'authentication_token' => 'token-old',
            'updated_at' => now()->subMinutes(10),
        ]);

        $newerPass = Pass::factory()->for($user)->apple()->create([
            'pass_template_id' => $template->id,
            'serial_number' => 'serial-new',
            'authentication_token' => 'token-new',
            'updated_at' => now(),
        ]);

        DeviceRegistration::query()->create([
            'device_library_identifier' => 'device-lib-1',
            'push_token' => 'push-token-1',
            'pass_type_identifier' => $user->apple_pass_type_id,
            'serial_number' => $olderPass->serial_number,
            'user_id' => $user->id,
            'is_active' => true,
        ]);

        DeviceRegistration::query()->create([
            'device_library_identifier' => 'device-lib-1',
            'push_token' => 'push-token-1',
            'pass_type_identifier' => $user->apple_pass_type_id,
            'serial_number' => $newerPass->serial_number,
            'user_id' => $user->id,
            'is_active' => true,
        ]);

        $since = now()->subMinutes(5)->timestamp;

        $response = $this->getJson(
            "/api/apple/v1/devices/device-lib-1/registrations/{$user->apple_pass_type_id}?passesUpdatedSince={$since}"
        );

        $response->assertOk();
        $response->assertJsonPath('serialNumbers', ['serial-new']);
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
            'serial_number' => 'serial-apple-refresh',
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

    private function latestPassUrl(string $passTypeIdentifier, string $serialNumber): string
    {
        return "/api/apple/v1/passes/{$passTypeIdentifier}/{$serialNumber}";
    }
}
