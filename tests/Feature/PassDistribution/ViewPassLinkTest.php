<?php

namespace Tests\Feature\PassDistribution;

use App\Models\Pass;
use App\Models\PassDistributionLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ViewPassLinkTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test unauthenticated user can view a pass link.
     */
    public function test_unauthenticated_user_can_view_pass_link()
    {
        $user = User::factory()->create();
        $pass = Pass::factory()->for($user)->create();
        $link = PassDistributionLink::factory()->for($pass)->create();

        $this->get(route('passes.show-by-link', $link->slug))
            ->assertOk();
    }

    /**
     * Test correct HTTP response and pass data returned.
     */
    public function test_pass_data_returned_in_response()
    {
        $user = User::factory()->create();
        $pass = Pass::factory()->for($user)->create();
        $link = PassDistributionLink::factory()->for($pass)->create();

        $this->get(route('passes.show-by-link', $link->slug))
            ->assertOk();
    }

    /**
     * Test device detection occurs.
     */
    public function test_device_detection_occurs()
    {
        $user = User::factory()->create();
        $pass = Pass::factory()->for($user)->create();
        $link = PassDistributionLink::factory()->for($pass)->create();

        // All device types should return OK
        $this->get(
            route('passes.show-by-link', $link->slug),
            ['HTTP_USER_AGENT' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X)']
        )->assertOk();

        $this->get(
            route('passes.show-by-link', $link->slug),
            ['HTTP_USER_AGENT' => 'Mozilla/5.0 (Linux; Android 13; SM-G991B)']
        )->assertOk();

        $this->get(
            route('passes.show-by-link', $link->slug),
            ['HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)']
        )->assertOk();
    }

    /**
     * Test access is recorded on view.
     */
    public function test_access_recorded_on_view()
    {
        $user = User::factory()->create();
        $pass = Pass::factory()->for($user)->create();
        $link = PassDistributionLink::factory()->for($pass)->create();

        $this->assertNull($link->last_accessed_at);
        $this->assertEquals(0, $link->accessed_count);

        $this->get(route('passes.show-by-link', $link->slug))->assertOk();

        $link->refresh();
        $this->assertNotNull($link->last_accessed_at);
        $this->assertEquals(1, $link->accessed_count);

        // View again
        $this->get(route('passes.show-by-link', $link->slug))->assertOk();

        $link->refresh();
        $this->assertEquals(2, $link->accessed_count);
    }

    /**
     * Test 404 response for non-existent link.
     */
    public function test_404_for_nonexistent_link()
    {
        $this->get(route('passes.show-by-link', 'nonexistent-slug'))
            ->assertNotFound();
    }

    /**
     * Test 403 response for disabled link.
     */
    public function test_403_for_disabled_link()
    {
        $user = User::factory()->create();
        $pass = Pass::factory()->for($user)->create();
        $link = PassDistributionLink::factory()->for($pass)->disabled()->create();

        $this->get(route('passes.show-by-link', $link->slug))
            ->assertForbidden();
    }
}
