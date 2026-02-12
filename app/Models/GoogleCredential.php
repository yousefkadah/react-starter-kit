<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class GoogleCredential extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'issuer_id',
        'private_key',
        'project_id',
        'last_rotated_at',
    ];

    protected function casts(): array
    {
        return [
            'last_rotated_at' => 'datetime',
        ];
    }

    /**
     * Get the user that owns this credential.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Parse the JSON structure and extract issuer ID (if needed).
     * This method documentation: can accept a JSON string and extract issuer_id.
     */
    public static function parseIssuerIdFromJson(string $json): string
    {
        $data = json_decode($json, true);
        if (isset($data['client_email'])) {
            // Extract the issuer ID from client_email (e.g., "passkit-service@project.iam.gserviceaccount.com")
            return explode('@', $data['client_email'])[0];
        }
        return '';
    }

    /**
     * Check if credentials are recent (rotated within last 90 days).
     */
    public function isRecent(): bool
    {
        if (!$this->last_rotated_at) {
            return false;
        }
        return $this->last_rotated_at->diffInDays(now()) <= 90;
    }
}
