<?php

namespace Tests\Feature\Auth;

use App\Mail\UserApprovedMail;
use App\Mail\UserPendingApprovalMail;
use App\Models\BusinessDomain;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SignupFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed business domains
        BusinessDomain::create(['domain' => 'stripe.com']);
        BusinessDomain::create(['domain' => 'google.com']);
    }

    /**
     * Test signup with business email auto-approves.
     */
    public function test_signup_with_business_email_auto_approves(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/signup', [
            'name' => 'John Doe',
            'email' => 'john@stripe.com',
            'password' => 'Password123!@#',
            'password_confirmation' => 'Password123!@#',
            'region' => 'US',
            'industry' => 'Finance',
            'agree_terms' => true,
        ]);

        $response->assertCreated();

        $user = User::where('email', 'john@stripe.com')->first();
        $this->assertNotNull($user);
        $this->assertEquals('approved', $user->approval_status);
        $this->assertNotNull($user->approved_at);

        // Verify approval email sent
        Mail::assertSent(UserApprovedMail::class);
    }

    /**
     * Test signup with consumer email queues for approval.
     */
    public function test_signup_with_consumer_email_queues_for_approval(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/signup', [
            'name' => 'Jane Doe',
            'email' => 'jane@gmail.com',
            'password' => 'Password123!@#',
            'password_confirmation' => 'Password123!@#',
            'region' => 'EU',
            'industry' => 'Retail',
            'agree_terms' => true,
        ]);

        $response->assertCreated();

        $user = User::where('email', 'jane@gmail.com')->first();
        $this->assertNotNull($user);
        $this->assertEquals('pending', $user->approval_status);
        $this->assertNull($user->approved_at);

        // Verify pending email sent
        Mail::assertSent(UserPendingApprovalMail::class);
    }

    /**
     * Test validation fails without required fields.
     */
    public function test_signup_validation_errors(): void
    {
        $response = $this->postJson('/api/signup', [
            'name' => 'John Doe',
            // Missing email
            'password' => 'password',
            'password_confirmation' => 'password',
            'region' => 'US',
            'agree_terms' => true,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['email']);
    }

    /**
     * Test password validation.
     */
    public function test_signup_password_validation(): void
    {
        $response = $this->postJson('/api/signup', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'weak', // Too simple
            'password_confirmation' => 'weak',
            'region' => 'US',
            'agree_terms' => true,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['password']);
    }

    /**
     * Test region is required.
     */
    public function test_signup_region_required(): void
    {
        $response = $this->postJson('/api/signup', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'Password123!@#',
            'password_confirmation' => 'Password123!@#',
            // Missing region
            'agree_terms' => true,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['region']);
    }

    /**
     * Test duplicate email validation.
     */
    public function test_signup_duplicate_email_fails(): void
    {
        $existing = User::factory()->create(['email' => 'existing@example.com']);

        $response = $this->postJson('/api/signup', [
            'name' => 'John Doe',
            'email' => 'existing@example.com',
            'password' => 'Password123!@#',
            'password_confirmation' => 'Password123!@#',
            'region' => 'US',
            'agree_terms' => true,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['email']);
    }

    /**
     * Test onboarding steps are created.
     */
    public function test_onboarding_steps_created(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/signup', [
            'name' => 'John Doe',
            'email' => 'john@stripe.com',
            'password' => 'Password123!@#',
            'password_confirmation' => 'Password123!@#',
            'region' => 'US',
            'agree_terms' => true,
        ]);

        $user = User::where('email', 'john@stripe.com')->first();
        $steps = $user->onboardingSteps()->pluck('step_key')->toArray();

        $this->assertContains('email_verified', $steps);
        $this->assertContains('apple_setup', $steps);
        $this->assertContains('google_setup', $steps);
        $this->assertContains('user_profile', $steps);
        $this->assertContains('first_pass', $steps);
    }
}
