<?php

namespace Tests\Feature\Region;

use App\Models\Pass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegionScopingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test users only see passes in their region.
     */
    public function test_user_only_sees_passes_in_their_region(): void
    {
        $euUser = User::factory()->approved()->create(['region' => 'EU']);
        $usUser = User::factory()->approved()->create(['region' => 'US']);

        Pass::factory()->for($euUser)->create();
        Pass::factory()->for($usUser)->create();

        $this->actingAs($euUser);

        $passes = Pass::all();
        $this->assertCount(1, $passes);
        $this->assertEquals($euUser->id, $passes->first()->user_id);
    }

    /**
     * Test admin can see passes across regions.
     */
    public function test_admin_can_see_all_regions(): void
    {
        $admin = User::factory()->admin()->create(['region' => 'EU']);
        $euUser = User::factory()->approved()->create(['region' => 'EU']);
        $usUser = User::factory()->approved()->create(['region' => 'US']);

        Pass::factory()->for($euUser)->create();
        Pass::factory()->for($usUser)->create();

        $this->actingAs($admin);

        $passes = Pass::all();
        $this->assertCount(2, $passes);
    }
}
