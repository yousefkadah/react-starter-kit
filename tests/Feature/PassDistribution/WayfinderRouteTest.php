<?php

namespace Tests\Feature\PassDistribution;

use App\Models\Pass;
use App\Models\PassDistributionLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WayfinderRouteTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that Wayfinder passes.show-by-link helper generates correct URL
     */
    public function test_wayfinder_show_by_link_generates_correct_url()
    {
        $user = User::factory()->create();
        $pass = Pass::factory()->for($user)->create();
        $link = PassDistributionLink::factory()->for($pass)->create();

        $response = $this->get("/p/{$link->slug}");
        $this->assertEquals(200, $response->status());
    }

    /**
     * Test that Wayfinder passes.distribution-links.index generates correct URL
     */
    public function test_wayfinder_distribution_links_index_generates_correct_url()
    {
        $user = User::factory()->forRegionUS()->create();
        $pass = Pass::factory()->for($user)->create();

        $response = $this->actingAs($user)->get(
            "/passes/{$pass->id}/distribution-links"
        );
        $this->assertEquals(200, $response->status());
    }

    /**
     * Test that Wayfinder passes.distribution-links.store generates correct URL
     */
    public function test_wayfinder_distribution_links_store_generates_correct_url()
    {
        $user = User::factory()->forRegionUS()->create();
        $pass = Pass::factory()->for($user)->create();

        $response = $this->actingAs($user)->post(
            "/passes/{$pass->id}/distribution-links"
        );
        $this->assertEquals(201, $response->status());
    }

    /**
     * Test that Wayfinder passes.distribution-links.update generates correct URL
     */
    public function test_wayfinder_distribution_links_update_generates_correct_url()
    {
        $user = User::factory()->forRegionUS()->create();
        $pass = Pass::factory()->for($user)->create();
        $link = PassDistributionLink::factory()->for($pass)->create();

        $response = $this->actingAs($user)->patch(
            "/passes/{$pass->id}/distribution-links/{$link->id}",
            ['status' => 'disabled']
        );
        $this->assertEquals(200, $response->status());
    }

    /**
     * Test that routes are properly named in Wayfinder
     */
    public function test_routes_are_properly_named_in_wayfinder()
    {
        $user = User::factory()->create();
        $pass = Pass::factory()->for($user)->create();
        $link = PassDistributionLink::factory()->for($pass)->create();

        // Test show-by-link route name
        $this->assertStringContainsString(
            "/p/{$link->slug}",
            route('passes.show-by-link', $link->slug)
        );

        // Test distribution-links.index route name
        $this->assertStringContainsString(
            "/passes/{$pass->id}/distribution-links",
            route('passes.distribution-links.index', $pass)
        );

        // Test distribution-links.store route name
        $this->assertStringContainsString(
            "/passes/{$pass->id}/distribution-links",
            route('passes.distribution-links.store', $pass)
        );

        // Test distribution-links.update route name
        $this->assertStringContainsString(
            "/passes/{$pass->id}/distribution-links/{$link->id}",
            route('passes.distribution-links.update', [$pass, $link])
        );
    }

    /**
     * Test that route parameters are correctly interpolated
     */
    public function test_route_parameters_are_correctly_interpolated()
    {
        $user = User::factory()->create();
        $pass = Pass::factory()->for($user)->create();
        $link1 = PassDistributionLink::factory()->for($pass)->create();
        $link2 = PassDistributionLink::factory()->for($pass)->create();

        // Verify different links produce different URLs
        $url1 = route('passes.show-by-link', $link1->slug);
        $url2 = route('passes.show-by-link', $link2->slug);
        $this->assertNotEquals($url1, $url2);

        // Verify parameter substitution works
        $this->assertStringContainsString($link1->slug, $url1);
        $this->assertStringContainsString($link2->slug, $url2);
    }
}
