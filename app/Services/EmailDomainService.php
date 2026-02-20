<?php

namespace App\Services;

use App\Jobs\MarkOnboardingStepJob;
use App\Models\BusinessDomain;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class EmailDomainService
{
    const CACHE_KEY = 'business_domains:all';

    const CACHE_TTL = 3600; // 1 hour

    /**
     * Check if an email domain is a business domain (whitelisted).
     *
     * @param  string  $email  The email address to check
     * @return bool True if domain is whitelisted, false otherwise
     */
    public function isBusinessDomain(string $email): bool
    {
        $domain = $this->extractDomain($email);

        $domains = Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            return BusinessDomain::pluck('domain')->toArray();
        });

        return in_array(strtolower($domain), array_map('strtolower', $domains));
    }

    /**
     * Extract domain from email address.
     *
     * @param  string  $email  The email address
     * @return string The domain part (e.g., 'example.com' from 'user@example.com')
     */
    public function extractDomain(string $email): string
    {
        $parts = explode('@', $email);

        return end($parts) ?? '';
    }

    /**
     * Determine approval status based on email domain.
     *
     * @param  string  $email  The email address
     * @return string Either 'approved' or 'pending'
     */
    public function getApprovalStatus(string $email): string
    {
        return $this->isBusinessDomain($email) ? 'approved' : 'pending';
    }

    /**
     * Invalidate the domain cache (call after adding/removing domains).
     */
    public function invalidateCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Queue a user for manual approval.
     *
     * @param  User  $user  The user to queue
     */
    public function queueForApproval(User $user): void
    {
        $user->update([
            'approval_status' => 'pending',
            'approved_at' => null,
            'approved_by' => null,
        ]);
    }

    /**
     * Approve a user account.
     *
     * @param  User  $user  The user to approve
     * @param  User|null  $admin  The admin approving the user (null for auto-approvals)
     */
    public function approveAccount(User $user, ?User $admin): void
    {
        $user->update([
            'approval_status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $admin?->id,
        ]);

        MarkOnboardingStepJob::dispatch($user->id, 'email_verified');
    }

    /**
     * Reject a user account.
     *
     * @param  User  $user  The user to reject
     * @param  User  $admin  The admin rejecting the user
     */
    public function rejectAccount(User $user, User $admin): void
    {
        $user->update([
            'approval_status' => 'rejected',
            'approved_at' => now(),
            'approved_by' => $admin->id,
        ]);
    }
}
