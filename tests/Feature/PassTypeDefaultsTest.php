<?php

namespace Tests\Feature;

use App\Models\Pass;
use App\Models\PassTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PassTypeDefaultsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_applies_template_defaults_when_pass_fields_are_null(): void
    {
        $user = $this->createRegionScopedUser();

        $template = PassTemplate::create([
            'user_id' => $user->id,
            'name' => 'Default Template',
            'description' => null,
            'pass_type' => 'generic',
            'platforms' => ['apple'],
            'design_data' => [
                'description' => 'Template Description',
                'organizationName' => 'Template Org',
                'logoText' => 'TEMPLATE',
                'backgroundColor' => '#ffffff',
                'foregroundColor' => '#000000',
                'labelColor' => '#111111',
                'headerFields' => [
                    ['key' => 'header1', 'label' => 'Header', 'value' => 'Default'],
                ],
                'primaryFields' => [],
                'secondaryFields' => [],
                'auxiliaryFields' => [],
                'backFields' => [],
                'transitType' => 'PKTransitTypeAir',
            ],
            'images' => null,
        ]);

        $payload = [
            'platforms' => ['apple'],
            'pass_type' => 'generic',
            'pass_template_id' => $template->id,
            'pass_data' => [
                'description' => null,
                'organizationName' => null,
            ],
            'barcode_data' => null,
            'images' => null,
        ];

        $response = $this->actingAs($user)->postJson(route('passes.store'), $payload);

        $response->assertRedirect();

        $pass = Pass::first();
        $this->assertNotNull($pass);
        $this->assertSame('Template Description', $pass->pass_data['description']);
        $this->assertSame('Template Org', $pass->pass_data['organizationName']);
    }

    public function test_it_keeps_explicit_empty_string_overrides(): void
    {
        $user = $this->createRegionScopedUser();

        $template = PassTemplate::create([
            'user_id' => $user->id,
            'name' => 'Default Template',
            'description' => null,
            'pass_type' => 'generic',
            'platforms' => ['apple'],
            'design_data' => [
                'description' => 'Template Description',
                'organizationName' => 'Template Org',
                'logoText' => 'TEMPLATE',
                'backgroundColor' => '#ffffff',
                'foregroundColor' => '#000000',
                'labelColor' => '#111111',
                'headerFields' => [],
                'primaryFields' => [],
                'secondaryFields' => [],
                'auxiliaryFields' => [],
                'backFields' => [],
            ],
            'images' => null,
        ]);

        $payload = [
            'platforms' => ['apple'],
            'pass_type' => 'generic',
            'pass_template_id' => $template->id,
            'pass_data' => [
                'description' => '',
            ],
            'barcode_data' => null,
            'images' => null,
        ];

        $response = $this->actingAs($user)->postJson(route('passes.store'), $payload);

        $response->assertRedirect();

        $pass = Pass::first();
        $this->assertNotNull($pass);
        $this->assertSame('', $pass->pass_data['description']);
    }
}
