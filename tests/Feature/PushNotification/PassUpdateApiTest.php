<?php

namespace Tests\Feature\PushNotification;

use App\Models\Pass;
use App\Models\PassTemplate;
use App\Models\PassUpdate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PassUpdateApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_patch_with_authenticated_user_updates_pass_and_returns_200(): void
    {
        Queue::fake();

        [$user, $pass] = $this->createApiPass();

        $this->actingAs($user)
            ->patchJson("/api/passes/{$pass->id}/fields", [
                'fields' => ['primary1' => 'Updated API Value'],
            ])
            ->assertOk()
            ->assertJsonPath('data.pass_id', $pass->id)
            ->assertJsonPath('data.apple_delivery_status', 'skipped')
            ->assertJsonPath('data.google_delivery_status', 'skipped');
    }

    public function test_patch_with_valid_hmac_signature_is_accepted(): void
    {
        Queue::fake();

        [$user, $pass] = $this->createApiPass();

        config(['passkit.api.hmac_secret' => 'test-secret']);

        $payload = [
            'fields' => ['primary1' => 'HMAC Update'],
        ];

        $signature = hash_hmac('sha256', json_encode($payload) ?: '', 'test-secret');

        $this->withHeaders([
            'X-Signature' => $signature,
        ])->patchJson("/api/passes/{$pass->id}/fields", $payload)
            ->assertOk()
            ->assertJsonPath('data.pass_id', $pass->id);
    }

    public function test_patch_with_invalid_hmac_signature_is_rejected(): void
    {
        [$user, $pass] = $this->createApiPass();

        config(['passkit.api.hmac_secret' => 'test-secret']);

        $this->withHeaders([
            'X-Signature' => 'invalid-signature',
        ])->patchJson("/api/passes/{$pass->id}/fields", [
            'fields' => ['primary1' => 'HMAC Update'],
        ])->assertStatus(401);
    }

    public function test_returns_403_for_other_users_pass(): void
    {
        Queue::fake();

        [$owner, $pass] = $this->createApiPass();
        $otherUser = User::factory()->forRegionUS()->create();

        $this->actingAs($otherUser)
            ->patchJson("/api/passes/{$pass->id}/fields", [
                'fields' => ['primary1' => 'Blocked Update'],
            ])
            ->assertStatus(403);
    }

    public function test_returns_422_for_invalid_or_oversized_payload(): void
    {
        Queue::fake();

        [$user, $pass] = $this->createApiPass();

        $this->actingAs($user)
            ->patchJson("/api/passes/{$pass->id}/fields", [
                'fields' => ['missing_field' => 'bad'],
            ])
            ->assertStatus(422);

        $this->actingAs($user)
            ->patchJson("/api/passes/{$pass->id}/fields", [
                'fields' => ['primary1' => str_repeat('A', 11000)],
            ])
            ->assertStatus(422);
    }

    public function test_returns_409_for_voided_pass(): void
    {
        Queue::fake();

        [$user, $pass] = $this->createApiPass();
        $pass->update(['status' => 'voided']);

        $this->actingAs($user)
            ->patchJson("/api/passes/{$pass->id}/fields", [
                'fields' => ['primary1' => 'Blocked Update'],
            ])
            ->assertStatus(409);
    }

    public function test_history_endpoint_returns_paginated_records(): void
    {
        [$user, $pass] = $this->createApiPass();

        PassUpdate::factory()->count(3)->create([
            'pass_id' => $pass->id,
            'user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->getJson("/api/passes/{$pass->id}/updates")
            ->assertOk()
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);
    }

    /**
     * @return array{0: User, 1: Pass}
     */
    private function createApiPass(): array
    {
        /** @var User $user */
        $user = User::factory()->forRegionUS()->create();

        $template = PassTemplate::factory()->for($user)->create();

        /** @var Pass $pass */
        $pass = Pass::factory()->for($user)->google()->create([
            'pass_template_id' => $template->id,
            'pass_data' => $template->design_data,
            'google_object_id' => null,
        ]);

        return [$user, $pass];
    }
}
