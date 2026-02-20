<?php

namespace Tests\Feature\PassDistribution;

use App\Models\Pass;
use App\Models\PassDistributionLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoutingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that named routes resolve correctly
     */
    public function test_passes_show_by_link_route_resolves()
    {
        $user = User::factory()->create();
        $pass = Pass::factory()->for($user)->create();
        $link = PassDistributionLink::factory()->for($pass)->create();

        $response = $this->get(route('passes.show-by-link', $link->slug));
        $this->assertEquals(200, $response->status());
    }

    /**
     * Test that distribution-links.index route resolves
     */
    public function test_distribution_links_index_route_resolves()
    {
        $user = User::factory()->forRegionUS()->create();
        $pass = Pass::factory()->for($user)->create();

        $response = $this->actingAs($user)->get(
            route('passes.distribution-links.index', $pass)
        );
        $this->assertEquals(200, $response->status());
    }

    /**
     * Test that distribution-links.store route resolves
     */
    public function test_distribution_links_store_route_resolves()
    {
        $user = User::factory()->forRegionUS()->create();
        $pass = Pass::factory()->for($user)->create();

        $response = $this->actingAs($user)->post(
            route('passes.distribution-links.store', $pass)
        );
        $this->assertEquals(201, $response->status());
    }

    /**
     * Test that distribution-links.update route resolves
     */
    public function test_distribution_links_update_route_resolves()
    {
        $user = User::factory()->forRegionUS()->create();
        $pass = Pass::factory()->for($user)->create();
        $link = PassDistributionLink::factory()->for($pass)->create();

        $response = $this->actingAs($user)->patch(
            route('passes.distribution-links.update', [$pass, $link]),
            ['status' => 'disabled']
        );
        $this->assertEquals(200, $response->status());
    }

    /**
     * Test that all distribution routes require authentication except show-by-link
     */
    public function test_distribution_routes_require_authentication()
    {
        $user = User::factory()->create();
        $pass = Pass::factory()->for($user)->create();

        // show-by-link should be accessible without auth
        $link = PassDistributionLink::factory()->for($pass)->create();
        $response = $this->get(route('passes.show-by-link', $link->slug));
        $this->assertEquals(200, $response->status());

        // Other routes should require auth
        $response = $this->get(route('passes.distribution-links.index', $pass));
        $this->assertEquals(302, $response->status());
    }
}
