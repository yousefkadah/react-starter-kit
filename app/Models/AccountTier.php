<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccountTier extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'name',
        'description',
        'order',
    ];

    /**
     * Get tier by key.
     */
    public static function byKey(string $key): ?self
    {
        return self::where('key', $key)->first();
    }

    /**
     * Get all tiers ordered by progression.
     */
    public static function ordered()
    {
        return self::orderBy('order')->get();
    }
}
