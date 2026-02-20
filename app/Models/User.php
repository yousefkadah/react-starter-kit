<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Cashier\Billable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use Billable, HasApiTokens, HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_admin',
        'region',
        'tier',
        'industry',
        'approval_status',
        'approved_at',
        'approved_by',
        'production_requested_at',
        'production_approved_at',
        'production_approved_by',
        'production_rejected_at',
        'production_rejected_reason',
        'pre_launch_checklist',
        'live_approved_at',
        'business_name',
        'business_address',
        'business_phone',
        'business_email',
        'business_website',
        'google_service_account_json',
        'google_issuer_id',
        'apple_certificate',
        'apple_certificate_password',
        'apple_team_id',
        'apple_pass_type_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'two_factor_confirmed_at' => 'datetime',
            'approved_at' => 'datetime',
            'production_requested_at' => 'datetime',
            'production_approved_at' => 'datetime',
            'production_rejected_at' => 'datetime',
            'pre_launch_checklist' => 'array',
            'live_approved_at' => 'datetime',
        ];
    }

    /**
     * Get the passes for the user.
     */
    public function passes(): HasMany
    {
        return $this->hasMany(Pass::class);
    }

    /**
     * Get the pass templates for the user.
     */
    public function passTemplates(): HasMany
    {
        return $this->hasMany(PassTemplate::class);
    }

    /**
     * Get the Apple certificates for the user.
     */
    public function appleCertificates(): HasMany
    {
        return $this->hasMany(AppleCertificate::class);
    }

    /**
     * Get the Google credentials for the user.
     */
    public function googleCredentials(): HasMany
    {
        return $this->hasMany(GoogleCredential::class);
    }

    /**
     * Get the onboarding steps for the user.
     */
    public function onboardingSteps(): HasMany
    {
        return $this->hasMany(OnboardingStep::class);
    }

    /**
     * Get the admin who approved the account.
     */
    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get the admin who approved production tier.
     */
    public function productionApprovedBy()
    {
        return $this->belongsTo(User::class, 'production_approved_by');
    }

    /**
     * Check if user is approved for production access.
     */
    public function isApproved(): bool
    {
        return $this->approval_status === 'approved';
    }

    /**
     * Get the current tier of the user.
     */
    public function currentTier(): string
    {
        return $this->tier ?? 'Email_Verified';
    }

    /**
     * Check if user can access wallet setup.
     */
    public function canAccessWalletSetup(): bool
    {
        return $this->email_verified_at !== null;
    }
}
