<?php

namespace App\Models;

use App\Traits\ScopedByRegion;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AppleCertificate extends Model
{
    use HasFactory, ScopedByRegion, SoftDeletes;

    protected $fillable = [
        'user_id',
        'path',
        'password',
        'valid_from',
        'expiry_date',
        'expiry_notified_30_days',
        'expiry_notified_7_days',
        'expiry_notified_0_days',
    ];

    protected function casts(): array
    {
        return [
            'valid_from' => 'datetime',
            'expiry_date' => 'datetime',
            'expiry_notified_30_days' => 'boolean',
            'expiry_notified_7_days' => 'boolean',
            'expiry_notified_0_days' => 'boolean',
        ];
    }

    /**
     * Get the user that owns this certificate.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if certificate is currently valid.
     */
    public function isValid(): bool
    {
        return now()->isBetween($this->valid_from, $this->expiry_date);
    }

    /**
     * Check if certificate is expiring within 30 days.
     */
    public function isExpiringSoon(): bool
    {
        $daysRemaining = $this->expiry_date->diffInDays(now(), absolute: false);

        return $daysRemaining <= 30 && $daysRemaining > 0;
    }

    /**
     * Check if certificate is expired.
     */
    public function isExpired(): bool
    {
        return now()->greaterThan($this->expiry_date);
    }

    /**
     * Get days remaining until expiry.
     */
    public function daysUntilExpiry(): int
    {
        return $this->expiry_date->diffInDays(now(), absolute: false);
    }
}
