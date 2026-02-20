<?php

namespace App\Models;

use App\Traits\ScopedByRegion;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pass extends Model
{
    /** @use HasFactory<\Database\Factories\PassFactory> */
    use HasFactory, ScopedByRegion, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'pass_template_id',
        'platforms',
        'pass_type',
        'serial_number',
        'status',
        'pass_data',
        'barcode_data',
        'images',
        'pkpass_path',
        'google_save_url',
        'google_class_id',
        'google_object_id',
        'last_generated_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'platforms' => 'array',
            'pass_data' => 'array',
            'barcode_data' => 'array',
            'images' => 'array',
            'last_generated_at' => 'datetime',
        ];
    }

    /**
     * Get the user that owns the pass.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the template that was used for this pass.
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(PassTemplate::class, 'pass_template_id');
    }

    /**
     * Get the distribution links for this pass.
     */
    public function distributionLinks(): HasMany
    {
        return $this->hasMany(PassDistributionLink::class, 'pass_id');
    }

    /**
     * Get or create a distribution link for this pass.
     */
    public function getOrCreateDistributionLink(): PassDistributionLink
    {
        return $this->distributionLinks()
            ->where('status', 'active')
            ->firstOrCreate(
                ['status' => 'active'],
                ['slug' => \Illuminate\Support\Str::uuid()]
            );
    }

    /**
     * Check if the pass has expired.
     */
    public function isExpired(): bool
    {
        // Check if pass_data has expiry_date and compare to now
        if (is_array($this->pass_data) && isset($this->pass_data['expiry_date'])) {
            return strtotime($this->pass_data['expiry_date']) < time();
        }

        return false;
    }

    /**
     * Check if the pass has been voided.
     */
    public function isVoided(): bool
    {
        return $this->status === 'void' || $this->status === 'voided';
    }
}
