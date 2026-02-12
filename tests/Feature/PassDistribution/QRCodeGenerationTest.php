<?php

namespace Tests\Feature\PassDistribution;

use App\Models\Pass;
use App\Models\PassDistributionLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QRCodeGenerationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test QR code page renders without error when accessing link.
     */
    public function test_qr_code_page_renders_without_error()
    {
        $user = User::factory()->create();
        $pass = Pass::factory()->for($user)->create();
        $link = PassDistributionLink::factory()->for($pass)->create();

        $this->get(route('passes.show-by-link', $link->slug))
            ->assertOk();
    }

    /**
     * Test distribution link URL is correct format.
     */
    public function test_distribution_link_url_is_correct_format()
    {
        $user = User::factory()->create();
        $pass = Pass::factory()->for($user)->create();
        $link = PassDistributionLink::factory()->for($pass)->create();

        $url = $link->url();

        // Verify format: /p/{slug}
        $this->assertStringContainsString('/p/', $url);
        $this->assertStringContainsString($link->slug, $url);
    }

    /**
     * Test multiple links generate different URLs.
     */
    public function test_multiple_links_generate_different_urls()
    {
        $user = User::factory()->create();
        $pass = Pass::factory()->for($user)->create();

        $link1 = PassDistributionLink::factory()->for($pass)->create();
        $link2 = PassDistributionLink::factory()->for($pass)->create();

        $this->assertNotEquals($link1->url(), $link2->url());
    }
}

