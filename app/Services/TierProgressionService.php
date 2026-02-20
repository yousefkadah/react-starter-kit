<?php

namespace App\Services;

use App\Events\ProductionApprovedEvent;
use App\Events\TierAdvancedEvent;
use App\Mail\AdminProductionRequestMail;
use App\Mail\LiveTierMail;
use App\Mail\ProductionApprovedMail;
use App\Mail\ProductionRejectedMail;
use App\Mail\ProductionRequestMail;
use App\Mail\TierAdvancedMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

class TierProgressionService
{
    /**
     * Evaluate and advance user tier based on current state.
     *
     * Rules:
     * 1. Email_Verified → Verified_And_Configured: if approved AND both Apple + Google certs exist
     * 2. Verified_And_Configured → (no auto-advance): Production requires manual request
     * 3. Production tier requires manual admin approval
     * 4. Live tier requires manual admin confirmation
     */
    public function evaluateAndAdvanceTier(User $user): void
    {
        $user->refresh();

        // Cannot advance if not approved
        if ($user->approval_status !== 'approved') {
            return;
        }

        // Check if user has both credentials
        $hasAppleCertificate = $user->appleCertificates()
            ->whereNull('deleted_at')
            ->exists();

        $hasGoogleCredential = $user->googleCredentials()
            ->whereNull('deleted_at')
            ->exists();

        $oldTier = $user->tier;

        // Rule 1: Email_Verified → Verified_And_Configured
        if ($user->tier === 'Email_Verified' && $hasAppleCertificate && $hasGoogleCredential) {
            $user->update(['tier' => 'Verified_And_Configured']);
            $this->dispatchTierAdvancedEvent($user, $oldTier, 'Verified_And_Configured');

            return;
        }

        // Rule 2: Already at Verified_And_Configured (no auto-advance beyond this)
        if ($user->tier === 'Verified_And_Configured') {
            return;
        }

        // Rules 3 & 4: Production and Live require manual approval
    }

    /**
     * Check if user can request production tier.
     *
     * Requirements:
     * - Current tier must be Verified_And_Configured
     * - Both Apple and Google credentials must be configured
     */
    public function canRequestProduction(User $user): bool
    {
        if ($user->tier !== 'Verified_And_Configured') {
            return false;
        }

        $hasAppleCertificate = $user->appleCertificates()
            ->whereNull('deleted_at')
            ->exists();

        $hasGoogleCredential = $user->googleCredentials()
            ->whereNull('deleted_at')
            ->exists();

        return $hasAppleCertificate && $hasGoogleCredential;
    }

    /**
     * Submit a production tier request.
     *
     * Creates a production request record and notifies the admin team.
     */
    public function submitProductionRequest(User $user): void
    {
        if (! $this->canRequestProduction($user)) {
            throw new \Exception('User cannot request production tier at this time.');
        }

        // Create production request record
        // This could be stored as a record or just in a requests table
        // For now, we'll update a column on the users table to track this
        $user->update([
            'production_requested_at' => now(),
            'production_rejected_at' => null,
            'production_rejected_reason' => null,
        ]);

        // Send email to user
        Mail::to($user->email)->send(new ProductionRequestMail($user));

        // Send email to admin team (all admins)
        $admins = User::where('is_admin', true)->get();
        foreach ($admins as $admin) {
            Mail::to($admin->email)->send(new AdminProductionRequestMail($user));
        }
    }

    /**
     * Approve production tier request.
     *
     * Advances user to Production tier and notifies them.
     */
    public function approveProduction(User $user, User $admin): void
    {
        if ($user->tier !== 'Verified_And_Configured') {
            throw new \Exception('User must be in Verified_And_Configured tier to approve for production.');
        }

        $user->update([
            'tier' => 'Production',
            'production_approved_at' => now(),
            'production_approved_by' => $admin->id,
            'production_requested_at' => null,
            'production_rejected_at' => null,
            'production_rejected_reason' => null,
        ]);

        // Send success email to user
        Mail::to($user->email)->send(new ProductionApprovedMail($user));

        // Dispatch approval event
        event(new ProductionApprovedEvent($user, $admin));
    }

    /**
     * Reject production tier request.
     *
     * Notifies user with rejection reason.
     */
    public function rejectProduction(User $user, User $admin, string $reason): void
    {
        if ($user->tier !== 'Verified_And_Configured') {
            throw new \Exception('User must be in Verified_And_Configured tier to reject production request.');
        }

        // Clear production request timestamp
        $user->update([
            'production_requested_at' => null,
            'production_rejected_reason' => $reason,
            'production_rejected_at' => now(),
        ]);

        // Send rejection email to user with reason
        Mail::to($user->email)->send(new ProductionRejectedMail($user, $reason));
    }

    /**
     * Request to go live (requires pre-launch checklist completion).
     *
     * Validates that all pre-launch requirements are met.
     */
    public function requestLive(User $user): bool
    {
        if ($user->tier !== 'Production') {
            throw new \Exception('User must be in Production tier to request live.');
        }

        // Validate pre-launch checklist
        return $this->validatePreLaunchChecklist($user);
    }

    /**
     * Advance user to Live tier.
     *
     * Only called after pre-launch checklist validation.
     */
    public function advanceToLive(User $user): void
    {
        if ($user->tier !== 'Production') {
            throw new \Exception('User must be in Production tier to advance to live.');
        }

        if (! $this->validatePreLaunchChecklist($user)) {
            throw new \Exception('Pre-launch checklist requirements not met.');
        }

        $oldTier = $user->tier;

        $user->update([
            'tier' => 'Live',
            'live_approved_at' => now(),
        ]);

        // Send celebration email
        Mail::to($user->email)->send(new LiveTierMail($user));

        // Dispatch event
        event(new TierAdvancedEvent($user, $oldTier, 'Live'));
    }

    /**
     * Validate pre-launch checklist requirements.
     *
     * Checklist items:
     * 1. Apple Wallet configured (has valid Apple cert)
     * 2. Google Wallet configured (has valid Google cred)
     * 3. Created at least 1 pass
     * 4. Tested on device (manual checkbox - stored in pre_launch_checklist field)
     * 5. User profile complete
     */
    private function validatePreLaunchChecklist(User $user): bool
    {
        // 1. Apple Certificate
        $hasAppleCertificate = $user->appleCertificates()
            ->whereNull('deleted_at')
            ->where('expiry_date', '>', now())
            ->exists();

        if (! $hasAppleCertificate) {
            return false;
        }

        // 2. Google Credential
        $hasGoogleCredential = $user->googleCredentials()
            ->whereNull('deleted_at')
            ->exists();

        if (! $hasGoogleCredential) {
            return false;
        }

        // 3. At least 1 pass created by user
        // (assuming user has many passes through a relationship)
        if (! method_exists($user, 'passes') || ! $user->passes()->exists()) {
            return false;
        }

        // 4. Tested on device (check pre_launch_checklist JSON or column)
        $checklist = $user->pre_launch_checklist ?? [];
        if (! isset($checklist['tested_on_device']) || ! $checklist['tested_on_device']) {
            return false;
        }

        // 5. Profile complete
        if (empty($user->name) || empty($user->business_name ?? null)) {
            return false;
        }

        return true;
    }

    /**
     * Mark pre-launch checklist item as complete.
     */
    public function markChecklistItem(User $user, string $item, bool $value = true): void
    {
        $checklist = $user->pre_launch_checklist ?? [];
        $checklist[$item] = $value;

        $user->update([
            'pre_launch_checklist' => $checklist,
        ]);
    }

    /**
     * Dispatch tier advanced event and send email.
     */
    private function dispatchTierAdvancedEvent(User $user, string $oldTier, string $newTier): void
    {
        event(new TierAdvancedEvent($user, $oldTier, $newTier));
        Mail::to($user->email)->send(new TierAdvancedMail($user, $newTier));
    }

    /**
     * Get tier advancement requirements for display.
     *
     * Returns human-readable requirements for the next tier.
     */
    public function getNextTierRequirements(User $user): array
    {
        $tier = $user->tier;

        return match ($tier) {
            'Email_Verified' => [
                ['name' => 'Configure Apple Wallet', 'met' => (bool) $user->appleCertificates()->whereNull('deleted_at')->exists()],
                ['name' => 'Configure Google Wallet', 'met' => (bool) $user->googleCredentials()->whereNull('deleted_at')->exists()],
            ],
            'Verified_And_Configured' => [
                ['name' => 'All wallets configured', 'met' => true],
                ['name' => 'Request Production review', 'met' => false],
            ],
            'Production' => [
                ['name' => 'Create test passes', 'met' => (bool) $user->passes()->exists()],
                ['name' => 'Test all features', 'met' => false],
                ['name' => 'Complete pre-launch checklist', 'met' => false],
            ],
            'Live' => [
                ['name' => 'Account is live!', 'met' => true],
            ],
            default => [],
        };
    }
}
