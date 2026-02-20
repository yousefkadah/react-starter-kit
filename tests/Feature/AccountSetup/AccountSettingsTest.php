<?php

namespace Tests\Feature\AccountSetup;

use App\Jobs\MarkOnboardingStepJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class AccountSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_approved_user_can_view_account_settings(): void
    {
        $user = User::factory()->approved()->create([
            'tier' => 'Email_Verified',
            'approval_status' => 'approved',
            'region' => 'US',
        ]);

        $response = $this->actingAs($user)->getJson('/api/account');

        $response->assertOk();
        $response->assertJsonStructure([
            'user',
            'tier',
            'is_approved',
            'can_setup_wallet',
            'onboarding_steps',
        ]);
        $response->assertJsonPath('user.id', $user->id);
    }

    public function test_update_account_dispatches_onboarding_step(): void
    {
        Bus::fake();

        $user = User::factory()->approved()->create([
            'tier' => 'Email_Verified',
            'approval_status' => 'approved',
            'region' => 'US',
        ]);

        $user->update([
            'name' => null,
            'industry' => null,
        ]);

        $response = $this->actingAs($user)->putJson('/api/account', [
            'name' => 'Acme Corp',
            'industry' => 'Retail',
        ]);

        $response->assertOk();

        Bus::assertDispatched(MarkOnboardingStepJob::class, function ($job) use ($user) {
            return $job->userId === $user->id && $job->stepKey === 'user_profile';
        });
    }
}
