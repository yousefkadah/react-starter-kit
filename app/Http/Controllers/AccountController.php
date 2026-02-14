<?php

namespace App\Http\Controllers;

use App\Http\Requests\SignupRequest;
use App\Jobs\MarkOnboardingStepJob;
use App\Jobs\ValidateEmailDomainJob;
use App\Models\OnboardingStep;
use App\Models\User;
use App\Services\EmailDomainService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;

class AccountController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(private EmailDomainService $emailDomainService) {}

    /**
     * Handle user signup/registration.
     */
    public function store(SignupRequest $request)
    {
        // Create the user
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'region' => $request->region,
            'industry' => $request->industry,
            'approval_status' => $this->emailDomainService->getApprovalStatus($request->email),
        ]);

        // Fire the Registered event
        event(new Registered($user));

        // Create initial onboarding steps
        $this->createOnboardingSteps($user);

        // Dispatch email validation job (will auto-approve or queue for approval)
        ValidateEmailDomainJob::dispatch($user);

        return response()->json([
            'message' => 'Account created successfully.',
            'user' => $user,
        ], 201);
    }

    /**
     * Create onboarding steps for a new user.
     */
    protected function createOnboardingSteps(User $user): void
    {
        $steps = [
            'email_verified',
            'apple_setup',
            'google_setup',
            'user_profile',
            'first_pass',
        ];

        foreach ($steps as $step) {
            OnboardingStep::create([
                'user_id' => $user->id,
                'step_key' => $step,
                'completed_at' => $step === 'email_verified' && $user->email_verified_at ? now() : null,
            ]);
        }
    }

    /**
     * Get the current user's account settings.
     */
    public function show()
    {
        $user = auth()->user();

        Gate::authorize('access-account-settings');

        return response()->json([
            'user' => $user,
            'tier' => $user->currentTier(),
            'is_approved' => $user->isApproved(),
            'can_setup_wallet' => $user->canAccessWalletSetup(),
            'onboarding_steps' => $user->onboardingSteps()->get(),
        ]);
    }

    /**
     * Update user account settings.
     */
    public function update()
    {
        $user = auth()->user();

        Gate::authorize('access-account-settings');

        $validated = request()->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'industry' => ['nullable', 'string', 'max:255'],
        ]);

        $user->update(array_filter($validated));

        if (! empty($user->name) && ! empty($user->industry)) {
            MarkOnboardingStepJob::dispatch($user->id, 'user_profile');
        }

        return response()->json([
            'message' => 'Account updated successfully.',
            'user' => $user,
        ]);
    }
}
