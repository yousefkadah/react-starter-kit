<?php

namespace Tests\Unit\Models;

use App\Models\Pass;
use App\Models\PassDistributionLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PassDistributionLinkUrlTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test url() method returns correct format /p/{slug}.
     */
    public function test_url_method_returns_correct_format()
    {
        $user = User::factory()->create();
        $pass = Pass::factory()->for($user)->create();
        $link = PassDistributionLink::factory()->for($pass)->create();

        $url = $link->url();

        $this->assertStringContainsString('/p/', $url);
        $this->assertStringContainsString($link->slug, $url);
        $this->assertStringContainsString($link->slug, $url);
    }

    /**
     * Test url() generates route name correctly.
     */
    public function test_url_uses_passes_show_by_link_route()
    {
        $user = User::factory()->create();
        $pass = Pass::factory()->for($user)->create();
        $link = PassDistributionLink::factory()->for($pass)->create();

        $url = $link->url();
        $expectedUrl = route('passes.show-by-link', ['slug' => $link->slug]);

        $this->assertEquals($expectedUrl, $url);
    }

    /**
     * Test url() can be accessed on models.
     */
    public function test_multiple_links_have_different_urls()
    {
        $user = User::factory()->create();
        $pass = Pass::factory()->for($user)->create();

        $link1 = PassDistributionLink::factory()->for($pass)->create();
        $link2 = PassDistributionLink::factory()->for($pass)->create();

        $this->assertNotEquals($link1->url(), $link2->url());
        $this->assertStringContainsString($link1->slug, $link1->url());
        $this->assertStringContainsString($link2->slug, $link2->url());
    }
}
