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
    use HasFactory, Notifiable, TwoFactorAuthenticatable, Billable, HasApiTokens;

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
