<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PassDistributionLink extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'pass_id',
        'slug',
        'status',
        'last_accessed_at',
        'accessed_count',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'last_accessed_at' => 'datetime',
        'accessed_count' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model) {
            if (empty($model->slug)) {
                $model->slug = Str::uuid();
            }
        });
    }

    /**
     * Get the pass that owns this distribution link.
     */
    public function pass(): BelongsTo
    {
        return $this->belongsTo(Pass::class, 'pass_id');
    }

    /**
     * Check if the link is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if the link is disabled.
     */
    public function isDisabled(): bool
    {
        return $this->status === 'disabled';
    }

    /**
     * Check if the link has been accessed.
     */
    public function hasBeenAccessed(): bool
    {
        return $this->accessed_count > 0;
    }

    /**
     * Get the number of days since this link was created.
     */
    public function daysOld(): int
    {
        return $this->created_at->diffInDays(now());
    }

    /**
     * Get the public URL for this distribution link.
     */
    public function url(): string
    {
        return route('passes.show-by-link', ['slug' => $this->slug]);
    }

    /**
     * Record an access to this link.
     */
    public function recordAccess(): void
    {
        $this->update([
            'accessed_count' => $this->accessed_count + 1,
            'last_accessed_at' => now(),
        ]);
    }
}

