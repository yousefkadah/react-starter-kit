<?php

namespace Tests\Feature\PassDistribution;

use App\Models\Pass;
use App\Models\PassDistributionLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DisableEnableLinkTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test disabling a link returns 403 when accessed.
     */
    public function test_disabled_link_returns_403()
    {
        $user = User::factory()->create();
        $pass = Pass::factory()->for($user)->create();
        $link = PassDistributionLink::factory()->for($pass)->disabled()->create();

        $this->get(route('passes.show-by-link', $link->slug))
            ->assertForbidden();
    }

    /**
     * Test cannot access disabled link.
     */
    public function test_cannot_access_disabled_link()
    {
        $user = User::factory()->create();
        $pass = Pass::factory()->for($user)->create();
        $link = PassDistributionLink::factory()->for($pass)->create();

        // Link works when active
        $this->get(route('passes.show-by-link', $link->slug))->assertOk();

        // Disable the link
        $link->update(['status' => 'disabled']);

        // Link no longer works
        $this->get(route('passes.show-by-link', $link->slug))
            ->assertForbidden();
    }

    /**
     * Test re-enabling a disabled link.
     */
    public function test_re_enable_disabled_link()
    {
        $user = User::factory()->create();
        $pass = Pass::factory()->for($user)->create();
        $link = PassDistributionLink::factory()->for($pass)->disabled()->create();

        // Initially disabled
        $this->get(route('passes.show-by-link', $link->slug))
            ->assertForbidden();

        // Re-enable the link
        $link->update(['status' => 'active']);

        // Link works again
        $this->get(route('passes.show-by-link', $link->slug))
            ->assertOk();
    }

    /**
     * Test authorized user can update link status.
     */
    public function test_authorized_user_can_update_link_status()
    {
        $user = User::factory()->forRegionUS()->create();
        $pass = Pass::factory()->for($user)->create();
        $link = PassDistributionLink::factory()->for($pass)->create();

        $response = $this->actingAs($user)
            ->patchJson(route('passes.distribution-links.update', [$pass, $link]), [
                'status' => 'disabled',
            ])
            ->assertOk();

        $this->assertEquals('disabled', $response['status']);
        $this->assertDatabaseHas('pass_distribution_links', [
            'id' => $link->id,
            'status' => 'disabled',
        ]);
    }
}
