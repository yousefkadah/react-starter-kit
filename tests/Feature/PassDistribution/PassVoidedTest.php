<?php

namespace Tests\Feature\PassDistribution;

use App\Models\Pass;
use App\Models\PassDistributionLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PassVoidedTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test voided pass returns 410 Gone.
     */
    public function test_voided_pass_returns_410()
    {
        $user = User::factory()->create();
        $pass = Pass::factory()->for($user)->create([
            'status' => 'voided',
        ]);
        $link = PassDistributionLink::factory()->for($pass)->create();

        $this->get(route('passes.show-by-link', $link->slug))
            ->assertGone();
    }

    /**
     * Test void status returns error message.
     */
    public function test_void_status_returns_error_message()
    {
        $user = User::factory()->create();
        $pass = Pass::factory()->for($user)->create([
            'status' => 'void',
        ]);
        $link = PassDistributionLink::factory()->for($pass)->create();

        $this->get(route('passes.show-by-link', $link->slug))
            ->assertGone();
    }

    /**
     * Test access is not recorded for voided pass.
     */
    public function test_access_not_recorded_for_voided_pass()
    {
        $user = User::factory()->create();
        $pass = Pass::factory()->for($user)->create([
            'status' => 'voided',
        ]);
        $link = PassDistributionLink::factory()->for($pass)->create();

        $this->get(route('passes.show-by-link', $link->slug))->assertGone();

        $link->refresh();
        $this->assertEquals(0, $link->accessed_count);
    }
}
