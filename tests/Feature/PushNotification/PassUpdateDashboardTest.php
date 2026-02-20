<?php

namespace Tests\Feature\PushNotification;

use App\Jobs\ProcessPassUpdateJob;
use App\Models\Pass;
use App\Models\PassTemplate;
use App\Models\PassUpdate;
use App\Models\User;
use App\Services\PassUpdateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class PassUpdateDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_update_dispatches_process_job(): void
    {
        Queue::fake();

        /** @var User $user */
        $user = User::factory()->forRegionUS()->create();
        $template = PassTemplate::factory()->for($user)->create();
        $pass = Pass::factory()->for($user)->create([
            'pass_template_id' => $template->id,
            'pass_data' => $template->design_data,
        ]);

        $this->actingAs($user)
            ->patchJson(route('passes.update-fields', $pass), [
                'fields' => [
                    'primary1' => 'Updated Value',
                ],
            ])
            ->assertOk()
            ->assertJsonPath('pass_id', $pass->id);

        Queue::assertPushed(ProcessPassUpdateJob::class, function ($job) use ($pass, $user): bool {
            return $job->passId === $pass->id
                && $job->initiatorId === $user->id
                && $job->source === 'dashboard';
        });
    }

    public function test_dashboard_update_rejects_voided_pass(): void
    {
        /** @var User $user */
        $user = User::factory()->forRegionUS()->create();
        $template = PassTemplate::factory()->for($user)->create();
        $pass = Pass::factory()->for($user)->voided()->create([
            'pass_template_id' => $template->id,
            'pass_data' => $template->design_data,
        ]);

        $this->actingAs($user)
            ->patchJson(route('passes.update-fields', $pass), [
                'fields' => [
                    'primary1' => 'Updated Value',
                ],
            ])
            ->assertStatus(409);
    }

    public function test_dashboard_update_rejects_payload_over_10kb(): void
    {
        /** @var User $user */
        $user = User::factory()->forRegionUS()->create();
        $template = PassTemplate::factory()->for($user)->create();
        $pass = Pass::factory()->for($user)->create([
            'pass_template_id' => $template->id,
            'pass_data' => $template->design_data,
        ]);

        $this->actingAs($user)
            ->patchJson(route('passes.update-fields', $pass), [
                'fields' => [
                    'primary1' => str_repeat('A', 11000),
                ],
            ])
            ->assertStatus(422);
    }

    public function test_dashboard_update_returns_warning_when_no_registered_devices(): void
    {
        Queue::fake();

        /** @var User $user */
        $user = User::factory()->forRegionUS()->create();
        $template = PassTemplate::factory()->for($user)->create();
        $pass = Pass::factory()->for($user)->apple()->create([
            'pass_template_id' => $template->id,
            'pass_data' => $template->design_data,
        ]);

        $this->actingAs($user)
            ->patchJson(route('passes.update-fields', $pass), [
                'fields' => [
                    'primary1' => 'Updated Value',
                ],
            ])
            ->assertOk()
            ->assertJsonPath('has_registered_devices', false)
            ->assertJsonPath('warning', 'No registered Apple Wallet devices for this pass.');
    }

    public function test_history_endpoint_returns_paginated_updates(): void
    {
        /** @var User $user */
        $user = User::factory()->forRegionUS()->create();
        $template = PassTemplate::factory()->for($user)->create();
        $pass = Pass::factory()->for($user)->create([
            'pass_template_id' => $template->id,
            'pass_data' => $template->design_data,
        ]);

        PassUpdate::factory()->count(2)->create([
            'pass_id' => $pass->id,
            'user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->getJson(route('passes.updates.history', $pass))
            ->assertOk()
            ->assertJsonStructure([
                'data',
                'current_page',
                'per_page',
                'total',
            ]);
    }

    public function test_process_job_delegates_to_pass_update_service(): void
    {
        /** @var User $user */
        $user = User::factory()->forRegionUS()->create();
        $template = PassTemplate::factory()->for($user)->create();
        $pass = Pass::factory()->for($user)->create([
            'pass_template_id' => $template->id,
            'platforms' => ['apple', 'google'],
            'google_object_id' => 'issuer.object-1',
            'pass_data' => $template->design_data,
        ]);

        $pass->deviceRegistrations()->create([
            'device_library_identifier' => 'device-lib-1',
            'push_token' => 'push-token-1',
            'pass_type_identifier' => 'pass.com.example.test',
            'serial_number' => $pass->serial_number,
            'user_id' => $user->id,
            'is_active' => true,
        ]);

        $passUpdate = PassUpdate::factory()->create([
            'pass_id' => $pass->id,
            'user_id' => $user->id,
            'apple_delivery_status' => 'pending',
            'google_delivery_status' => 'pending',
        ]);

        $mockService = Mockery::mock(PassUpdateService::class);
        $mockService
            ->shouldReceive('updatePassFields')
            ->once()
            ->andReturn($passUpdate);

        $job = new ProcessPassUpdateJob($pass->id, ['primary1' => 'Updated Value'], $user->id);
        $job->handle($mockService);
    }
}
