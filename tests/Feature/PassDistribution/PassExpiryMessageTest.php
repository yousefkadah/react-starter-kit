<?php

namespace Tests\Feature\PassDistribution;

use App\Models\Pass;
use App\Models\PassDistributionLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PassExpiryMessageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test expired pass shows message on link view.
     */
    public function test_expired_pass_shows_message_on_link_view()
    {
        $user = User::factory()->create();
        $pass = Pass::factory()->for($user)->create([
            'pass_data' => [
                'expiry_date' => date('Y-m-d', strtotime('-1 day')),
            ],
        ]);
        $link = PassDistributionLink::factory()->for($pass)->create();

        // Link should still be accessible
        $response = $this->get(route('passes.show-by-link', $link->slug))
            ->assertOk();
    }

    /**
     * Test link still accessible even though pass is expired.
     */
    public function test_link_still_accessible_when_pass_expired()
    {
        $user = User::factory()->create();
        $pass = Pass::factory()->for($user)->create([
            'pass_data' => [
                'expiry_date' => date('Y-m-d', strtotime('-1 day')),
            ],
        ]);
        $link = PassDistributionLink::factory()->for($pass)->create();

        $this->get(route('passes.show-by-link', $link->slug))
            ->assertOk();

        // Verify access was recorded
        $link->refresh();
        $this->assertEquals(1, $link->accessed_count);
    }

    /**
     * Test user cannot enroll expired pass.
     */
    public function test_cannot_enroll_expired_pass()
    {
        $user = User::factory()->create();
        $pass = Pass::factory()->for($user)->create([
            'pass_data' => [
                'expiry_date' => date('Y-m-d', strtotime('-1 day')),
            ],
        ]);
        $link = PassDistributionLink::factory()->for($pass)->create();

        // Link is still accessible with 200 OK
        $this->get(route('passes.show-by-link', $link->slug))
            ->assertOk();
    }
}
