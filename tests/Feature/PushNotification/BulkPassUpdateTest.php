<?php

namespace Tests\Feature\PushNotification;

use App\Jobs\BulkPassUpdateJob;
use App\Models\BulkUpdate;
use App\Models\Pass;
use App\Models\PassTemplate;
use App\Models\User;
use App\Services\PassUpdateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class BulkPassUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_bulk_update_queues_job_and_returns_accepted(): void
    {
        Queue::fake();

        /** @var User $user */
        $user = User::factory()->forRegionUS()->create();
        $template = PassTemplate::factory()->for($user)->create();

        Pass::factory()->count(3)->for($user)->create([
            'pass_template_id' => $template->id,
            'pass_data' => $template->design_data,
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)->postJson('/passes/bulk-update', [
            'pass_template_id' => $template->id,
            'field_key' => 'primary1',
            'field_value' => 'Updated Bulk',
        ]);

        $response
            ->assertStatus(202)
            ->assertJsonPath('data.status', 'pending');

        Queue::assertPushed(BulkPassUpdateJob::class);
    }

    public function test_bulk_update_mutex_rejects_concurrent_requests_for_same_template(): void
    {
        /** @var User $user */
        $user = User::factory()->forRegionUS()->create();
        $template = PassTemplate::factory()->for($user)->create();

        BulkUpdate::factory()->create([
            'user_id' => $user->id,
            'pass_template_id' => $template->id,
            'status' => 'processing',
        ]);

        $this->actingAs($user)->postJson('/passes/bulk-update', [
            'pass_template_id' => $template->id,
            'field_key' => 'primary1',
            'field_value' => 'Updated Bulk',
        ])->assertStatus(409);
    }

    public function test_bulk_update_job_tracks_processed_and_failed_counts(): void
    {
        Queue::fake();

        /** @var User $user */
        $user = User::factory()->forRegionUS()->create();
        $template = PassTemplate::factory()->for($user)->create();

        $goodPass = Pass::factory()->for($user)->google()->create([
            'pass_template_id' => $template->id,
            'pass_data' => $template->design_data,
            'status' => 'active',
            'google_object_id' => 'issuer.good-pass',
        ]);

        Pass::factory()->for($user)->google()->voided()->create([
            'pass_template_id' => $template->id,
            'pass_data' => $template->design_data,
            'google_object_id' => 'issuer.voided-pass',
        ]);

        $bulkUpdate = BulkUpdate::factory()->create([
            'user_id' => $user->id,
            'pass_template_id' => $template->id,
            'field_key' => 'primary1',
            'field_value' => 'Updated Bulk',
            'status' => 'pending',
            'total_count' => 2,
            'processed_count' => 0,
            'failed_count' => 0,
        ]);

        $job = new BulkPassUpdateJob($bulkUpdate->id);
        $job->handle(new PassUpdateService);

        $bulkUpdate->refresh();
        $goodPass->refresh();

        $this->assertSame('completed', $bulkUpdate->status);
        $this->assertSame(1, $bulkUpdate->processed_count);
        $this->assertSame(1, $bulkUpdate->failed_count);

        $this->assertSame(
            'Updated Bulk',
            $goodPass->pass_data['primaryFields'][0]['value'] ?? null
        );
    }

    public function test_bulk_update_filters_by_status_and_platform(): void
    {
        Queue::fake();

        /** @var User $user */
        $user = User::factory()->forRegionUS()->create();
        $template = PassTemplate::factory()->for($user)->create();

        $googleActive = Pass::factory()->for($user)->google()->create([
            'pass_template_id' => $template->id,
            'pass_data' => $template->design_data,
            'status' => 'active',
            'google_object_id' => 'issuer.google-active',
        ]);

        $appleActive = Pass::factory()->for($user)->apple()->create([
            'pass_template_id' => $template->id,
            'pass_data' => $template->design_data,
            'status' => 'active',
        ]);

        Pass::factory()->for($user)->apple()->voided()->create([
            'pass_template_id' => $template->id,
            'pass_data' => $template->design_data,
        ]);

        $bulkUpdate = BulkUpdate::factory()->create([
            'user_id' => $user->id,
            'pass_template_id' => $template->id,
            'field_key' => 'primary1',
            'field_value' => 'Apple Only',
            'status' => 'pending',
            'filters' => [
                'status' => 'active',
                'platform' => 'google',
            ],
        ]);

        $job = new BulkPassUpdateJob($bulkUpdate->id);
        $job->handle(new PassUpdateService);

        $appleActive->refresh();
        $googleActive->refresh();

        $this->assertSame('Apple Only', $googleActive->pass_data['primaryFields'][0]['value'] ?? null);
        $this->assertNotSame('Apple Only', $appleActive->pass_data['primaryFields'][0]['value'] ?? null);
    }
}
