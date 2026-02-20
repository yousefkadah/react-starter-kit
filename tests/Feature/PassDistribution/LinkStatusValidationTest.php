<?php

namespace Tests\Feature\PassDistribution;

use App\Models\Pass;
use App\Models\PassDistributionLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LinkStatusValidationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test invalid status returns 422.
     */
    public function test_invalid_status_returns_422()
    {
        $user = User::factory()->forRegionUS()->create();
        $pass = Pass::factory()->for($user)->create();
        $link = PassDistributionLink::factory()->for($pass)->create();

        $this->actingAs($user)
            ->patchJson(route('passes.distribution-links.update', [$pass, $link]), [
                'status' => 'invalid-status',
            ])
            ->assertUnprocessable();
    }

    /**
     * Test only active and disabled statuses are allowed.
     */
    public function test_only_valid_statuses_allowed()
    {
        $user = User::factory()->forRegionUS()->create();
        $pass = Pass::factory()->for($user)->create();
        $link = PassDistributionLink::factory()->for($pass)->create();

        // Valid: active
        $this->actingAs($user)
            ->patchJson(route('passes.distribution-links.update', [$pass, $link]), [
                'status' => 'active',
            ])
            ->assertOk();

        // Valid: disabled
        $this->actingAs($user)
            ->patchJson(route('passes.distribution-links.update', [$pass, $link]), [
                'status' => 'disabled',
            ])
            ->assertOk();

        // Invalid: anything else should fail
        $this->actingAs($user)
            ->patchJson(route('passes.distribution-links.update', [$pass, $link]), [
                'status' => 'pending',
            ])
            ->assertUnprocessable();
    }

    /**
     * Test unauthenticated user cannot update.
     */
    public function test_unauthenticated_user_cannot_update()
    {
        $user = User::factory()->create();
        $pass = Pass::factory()->for($user)->create();
        $link = PassDistributionLink::factory()->for($pass)->create();

        $this->patchJson(route('passes.distribution-links.update', [$pass, $link]), [
            'status' => 'disabled',
        ])
            ->assertUnauthorized();
    }

    /**
     * Test unauthorized user cannot update others' links.
     */
    public function test_unauthorized_user_cannot_update_link()
    {
        $user1 = User::factory()->forRegionUS()->create();
        $user2 = User::factory()->forRegionUS()->create();
        $pass = Pass::factory()->for($user1)->create();
        $link = PassDistributionLink::factory()->for($pass)->create();

        $this->actingAs($user2)
            ->patchJson(route('passes.distribution-links.update', [$pass, $link]), [
                'status' => 'disabled',
            ])
            ->assertForbidden();
    }
}
