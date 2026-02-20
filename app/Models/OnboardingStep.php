<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnboardingStep extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'step_key',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
        ];
    }

    /**
     * Get the user that owns this onboarding step.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope for incomplete steps.
     */
    public function scopeIncomplete($query)
    {
        return $query->whereNull('completed_at');
    }

    /**
     * Scope for completed steps.
     */
    public function scopeCompleted($query)
    {
        return $query->whereNotNull('completed_at');
    }

    /**
     * Check if this step is complete.
     */
    public function isComplete(): bool
    {
        return $this->completed_at !== null;
    }

    /**
     * Mark step as complete.
     */
    public function markComplete(): self
    {
        $this->completed_at = now();
        $this->save();

        return $this;
    }
}
