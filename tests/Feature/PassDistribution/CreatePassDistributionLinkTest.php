<?php

namespace Tests\Feature\PassDistribution;

use App\Models\Pass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreatePassDistributionLinkTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test authenticated user can create a distribution link for their pass.
     */
    public function test_authenticated_user_can_create_distribution_link()
    {
        $user = User::factory()->forRegionUS()->create();
        $pass = Pass::factory()->for($user)->create();

        $this->actingAs($user)
            ->postJson(route('passes.distribution-links.store', $pass))
            ->assertCreated()
            ->assertJsonStructure([
                'id',
                'pass_id',
                'slug',
                'status',
                'url',
                'last_accessed_at',
                'accessed_count',
                'created_at',
                'updated_at',
            ]);
    }

    /**
     * Test created link has active status.
     */
    public function test_created_link_has_active_status()
    {
        $user = User::factory()->forRegionUS()->create();
        $pass = Pass::factory()->for($user)->create();

        $response = $this->actingAs($user)
            ->postJson(route('passes.distribution-links.store', $pass))
            ->assertCreated();

        $this->assertEquals('active', $response['status']);
    }

    /**
     * Test slug is generated on creation.
     */
    public function test_slug_is_generated_on_creation()
    {
        $user = User::factory()->forRegionUS()->create();
        $pass = Pass::factory()->for($user)->create();

        $response = $this->actingAs($user)
            ->postJson(route('passes.distribution-links.store', $pass))
            ->assertCreated();

        // Verify slug is UUID format (36 characters)
        $this->assertMatchesRegularExpression(
            '/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/',
            $response['slug']
        );

        // Verify it's stored in database
        $this->assertDatabaseHas('pass_distribution_links', [
            'pass_id' => $pass->id,
            'slug' => $response['slug'],
        ]);
    }

    /**
     * Test unauthorized user cannot create link for others' passes.
     */
    public function test_unauthorized_user_cannot_create_link()
    {
        $user1 = User::factory()->forRegionUS()->create();
        $user2 = User::factory()->forRegionUS()->create();
        $pass = Pass::factory()->for($user1)->create();

        $this->actingAs($user2)
            ->postJson(route('passes.distribution-links.store', $pass))
            ->assertForbidden();
    }

    /**
     * Test unauthenticated user cannot create link.
     */
    public function test_unauthenticated_user_cannot_create_link()
    {
        $user = User::factory()->forRegionUS()->create();
        $pass = Pass::factory()->for($user)->create();

        $this->postJson(route('passes.distribution-links.store', $pass))
            ->assertUnauthorized();
    }
}
