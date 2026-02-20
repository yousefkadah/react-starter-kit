<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PassUpdate extends Model
{
    /** @use HasFactory<\Database\Factories\PassUpdateFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'pass_id',
        'user_id',
        'bulk_update_id',
        'source',
        'fields_changed',
        'apple_delivery_status',
        'google_delivery_status',
        'apple_devices_notified',
        'google_updated',
        'error_message',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fields_changed' => 'array',
            'google_updated' => 'boolean',
        ];
    }

    /**
     * Get the pass associated with this update.
     */
    public function pass(): BelongsTo
    {
        return $this->belongsTo(Pass::class);
    }

    /**
     * Get the user who initiated this update.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the bulk update this record belongs to.
     */
    public function bulkUpdate(): BelongsTo
    {
        return $this->belongsTo(BulkUpdate::class);
    }
}
