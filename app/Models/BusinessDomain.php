<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BusinessDomain extends Model
{
    use HasFactory;

    protected $fillable = [
        'domain',
    ];

    /**
     * Check if a given email address matches this business domain.
     */
    public function matchesDomain(string $email): bool
    {
        $emailDomain = $this->extractDomainFromEmail($email);

        return strtolower($emailDomain) === strtolower($this->domain);
    }

    /**
     * Extract domain from email address.
     */
    protected function extractDomainFromEmail(string $email): string
    {
        $parts = explode('@', $email);

        return end($parts) ?? '';
    }

    /**
     * Scope to find domain by email.
     */
    public function scopeByEmail($query, string $email)
    {
        $emailDomain = $this->extractDomainFromEmail($email);

        return $query->where('domain', $emailDomain);
    }
}
