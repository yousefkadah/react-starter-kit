<?php

namespace Tests\Feature\Admin;

use App\Mail\UserApprovedMail;
use App\Mail\UserRejectedMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminApprovalTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test admin can view pending approvals.
     */
    public function test_admin_can_view_pending_approvals(): void
    {
        $admin = User::factory()->admin()->create(['region' => 'US']);
        $pending = User::factory()->pending()->count(3)->create(['region' => 'US']);

        $response = $this->actingAs($admin)->getJson('/admin/approvals');

        $response->assertOk();
        $response->assertJsonPath('pending_count', 3);
    }

    /**
     * Test non-admin cannot view pending approvals.
     */
    public function test_non_admin_cannot_view_approvals(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $response = $this->actingAs($user)->getJson('/admin/approvals');

        $response->assertForbidden();
    }

    /**
     * Test admin can approve a pending user.
     */
    public function test_admin_can_approve_user(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create();
        $user = User::factory()->pending()->create();

        $response = $this->actingAs($admin)
            ->postJson("/admin/approvals/{$user->id}/approve");

        $response->assertOk();

        $user->refresh();
        $this->assertEquals('approved', $user->approval_status);
        $this->assertNotNull($user->approved_at);
        $this->assertEquals($admin->id, $user->approved_by);

        Mail::assertSent(UserApprovedMail::class);
    }

    /**
     * Test admin can reject a pending user.
     */
    public function test_admin_can_reject_user(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create();
        $user = User::factory()->pending()->create();

        $response = $this->actingAs($admin)
            ->postJson("/admin/approvals/{$user->id}/reject", [
                'reason' => 'Suspicious activity detected',
            ]);

        $response->assertOk();

        $user->refresh();
        $this->assertEquals('rejected', $user->approval_status);
        $this->assertNotNull($user->approved_at);

        Mail::assertSent(UserRejectedMail::class);
    }

    /**
     * Test admin cannot approve an already-approved user.
     */
    public function test_cannot_approve_already_approved_user(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->approved()->create();

        $response = $this->actingAs($admin)
            ->postJson("/admin/approvals/{$user->id}/approve");

        $response->assertBadRequest();
        $response->assertJsonPath('message', 'User is not pending approval.');
    }

    /**
     * Test admin can view approved accounts.
     */
    public function test_admin_can_view_approved_accounts(): void
    {
        $admin = User::factory()->admin()->create(['region' => 'US']);
        User::factory()->approved()->count(5)->create(['region' => 'US']);

        $response = $this->actingAs($admin)->getJson('/admin/approvals/approved');

        $response->assertOk();
        $response->assertJsonPath('total', 5);
    }

    /**
     * Test admin can view rejected accounts.
     */
    public function test_admin_can_view_rejected_accounts(): void
    {
        $admin = User::factory()->admin()->create(['region' => 'US']);
        User::factory()->count(2)->create(['approval_status' => 'rejected', 'region' => 'US']);

        $response = $this->actingAs($admin)->getJson('/admin/approvals/rejected');

        $response->assertOk();
        $response->assertJsonPath('total', 2);
    }
}
