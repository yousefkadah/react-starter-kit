<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScanEvent extends Model
{
    /** @use HasFactory<\Database\Factories\ScanEventFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'pass_id',
        'scanner_link_id',
        'action',
        'result',
        'ip_address',
        'user_agent',
    ];

    /**
     * Get the user (owner) associated with this scan event.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the pass that was scanned.
     */
    public function pass(): BelongsTo
    {
        return $this->belongsTo(Pass::class);
    }

    /**
     * Get the scanner link used for this scan event.
     */
    public function scannerLink(): BelongsTo
    {
        return $this->belongsTo(ScannerLink::class);
    }
}
