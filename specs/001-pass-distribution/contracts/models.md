# Contract: Pass Distribution Models

**Date**: February 12, 2026  
**Feature**: Pass Distribution System  
**Output of**: `/speckit.plan` Phase 1

---

## Overview

This document defines the data contracts (TypeScript interfaces) for frontend models and API responses related to the Pass Distribution System.

---

## TypeScript Interfaces

### PassDistributionLink (Frontend Model)

```typescript
// resources/js/types/passDistributionLink.ts

export interface PassDistributionLink {
  id: number;
  pass_id: number;
  slug: string;  // UUIDv4
  status: 'active' | 'disabled';
  url: string;  // Full URL to public pass link
  last_accessed_at: string | null;  // ISO 8601 timestamp or null
  accessed_count: number;
  created_at: string;  // ISO 8601 timestamp
  updated_at: string;  // ISO 8601 timestamp
}
```

### Pass (Extended for Distribution)

```typescript
// resources/js/types/pass.ts (existing model)

export interface Pass {
  id: number;
  user_id: number;
  type: 'loyalty_card' | 'coupon' | 'event_ticket' | 'boarding_pass' | 'store_card' | 'generic';
  name: string;
  description: string;
  // ... other pass fields
  distribution_links?: PassDistributionLink[];  // NEW: optional eager-loaded relationship
}
```

### PassLinkViewProps (Inertia Page Props)

```typescript
// resources/js/pages/PassLink.tsx (public pass link page)

export interface PassLinkViewProps {
  pass: {
    id: number;
    type: string;
    name: string;
    description: string;
    icon_url: string;
    logo_url: string;
    background_url?: string;
    serial_number: string;
    barcodes: Array<{
      format: 'qr' | 'pdf417' | 'aztec' | 'code128';
      value: string;
    }>;
    expiration_date?: string | null;
  };
  device: 'ios' | 'android' | 'desktop' | 'unknown';
  link_status: 'active' | 'expired' | 'disabled' | 'voided';
  message?: string | null;  // Error or info message
  add_to_wallet_url: {
    apple: string;  // URL to download .pkpass file
    google: string;  // URL to open Google Pay save flow
  };
  qr_code_data: {
    text: string;  // Pass link URL
    width: number;
    height: number;
  };
}
```

### QRCodeDisplayProps (React Component Props)

```typescript
// resources/js/components/QRCodeDisplay.tsx

export interface QRCodeDisplayProps {
  url: string;  // Pass link URL to encode
  width?: number;  // Default: 200
  height?: number;  // Default: 200
  errorLevel?: 'L' | 'M' | 'Q' | 'H';  // Default: 'M'
  darkenColor?: string;  // Default: '#000000'
  lightenColor?: string;  // Default: '#ffffff'
  downloadable?: boolean;  // Default: true
}
```

### DistributionPanelProps (React Component Props)

```typescript
// resources/js/pages/Passes/DistributionPanel.tsx

export interface DistributionPanelProps {
  pass: Pass;
  links: PassDistributionLink[];
  onCreateLink: (link: PassDistributionLink) => void;
  onToggleLink: (link: PassDistributionLink, newStatus: 'active' | 'disabled') => void;
  isLoading: boolean;
}
```

---

## API Response Contracts

### 200 OK - List Distribution Links

```typescript
interface ListDistributionLinksResponse {
  data: PassDistributionLink[];
  pagination: {
    total: number;
    current_page: number;
    per_page: number;
    last_page: number;
  };
}
```

### 201 Created - Create Distribution Link

```typescript
interface CreateDistributionLinkResponse {
  data: PassDistributionLink;
}
```

### 200 OK - Update Distribution Link

```typescript
interface UpdateDistributionLinkResponse {
  data: PassDistributionLink;
}
```

### 404 Not Found

```typescript
interface NotFoundResponse {
  message: string;  // e.g., "Pass link not found or has been disabled."
}
```

### 410 Gone

```typescript
interface GoneResponse {
  message: string;  // e.g., "This pass is no longer valid and cannot be enrolled."
}
```

### 422 Unprocessable Entity

```typescript
interface ValidationErrorResponse {
  message: string;
  errors: Record<string, string[]>;  // Field -> error messages
}
```

---

## Validation Contracts

### PassDistributionLink Status Enum

```typescript
export type PassDistributionLinkStatus = 'active' | 'disabled';

export const PASS_DISTRIBUTION_LINK_STATUSES: Record<PassDistributionLinkStatus, string> = {
  active: 'Active',
  disabled: 'Disabled',
};
```

### Device Type Enum

```typescript
export type DeviceType = 'ios' | 'android' | 'desktop' | 'unknown';

export const DEVICE_TYPES: Record<DeviceType, string> = {
  ios: 'Apple Wallet',
  android: 'Google Pay',
  desktop: 'Web (Choose Platform)',
  unknown: 'Choose Platform',
};
```

### Link Status Display

```typescript
export type PassLinkStatus = 'active' | 'expired' | 'disabled' | 'voided';

export const PASS_LINK_STATUS_MESSAGES: Record<PassLinkStatus, string> = {
  active: null,  // No message; normal flow
  expired: 'This pass has expired and is no longer valid for enrollment.',
  disabled: 'This link has been disabled by the issuer.',
  voided: 'This pass is no longer valid and cannot be enrolled.',
};
```

---

## Backend Model Contracts (PHP)

### PassDistributionLink Eloquent Model

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PassDistributionLink extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
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
     *
     * @var array<string, string>
     */
    protected $casts = [
        'last_accessed_at' => 'datetime',
        'accessed_count' => 'integer',
    ];

    /**
     * The relationship to the Pass model.
     *
     * @return BelongsTo
     */
    public function pass(): BelongsTo
    {
        return $this->belongsTo(Pass::class);
    }

    /**
     * Check if the link is active.
     *
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if the link is disabled.
     *
     * @return bool
     */
    public function isDisabled(): bool
    {
        return $this->status === 'disabled';
    }

    /**
     * Record an access event.
     *
     * @return void
     */
    public function recordAccess(): void
    {
        $this->update([
            'last_accessed_at' => now(),
            'accessed_count' => $this->accessed_count + 1,
        ]);
    }

    /**
     * Get the full public URL for this link.
     *
     * @return string
     */
    public function url(): string
    {
        return route('passes.show-by-link', ['slug' => $this->slug]);
    }
}
```

### Pass Model (Extended)

```php
// In app/Models/Pass.php (existing model)

public function distributionLinks()
{
    return $this->hasMany(PassDistributionLink::class);
}

/**
 * Get or create the primary distribution link.
 * (Helper method for convenience)
 *
 * @return PassDistributionLink
 */
public function getOrCreateDistributionLink(): PassDistributionLink
{
    return $this->distributionLinks()
        ->where('status', 'active')
        ->first() ?? PassDistributionLink::create([
            'pass_id' => $this->id,
            'slug' => Str::uuid()->toString(),
        ]);
}
```

---

## Form Request Validation Contracts

### StorePassDistributionLinkRequest

```php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePassDistributionLinkRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return $this->user()->can('createDistributionLink', $this->route('pass'));
    }

    /**
     * Get the validation rules that apply to the request.
     * (No user input; all auto-generated)
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [];
    }
}
```

### UpdatePassDistributionLinkRequest

```php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePassDistributionLinkRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return $this->user()->can('updateDistributionLink', $this->route('link'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'status' => [
                'required',
                Rule::in(['active', 'disabled']),
            ],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.in' => 'The status must be either "active" or "disabled".',
        ];
    }
}
```

---

## Resource Response Contracts

### PassDistributionLinkResource

```php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PassDistributionLinkResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'pass_id' => $this->pass_id,
            'slug' => $this->slug,
            'status' => $this->status,
            'url' => $this->url(),
            'last_accessed_at' => $this->last_accessed_at?->toIso8601String(),
            'accessed_count' => $this->accessed_count,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
```

---

## Factory Contract (Testing)

### PassDistributionLinkFactory

```php
namespace Database\Factories;

use App\Models\Pass;
use App\Models\PassDistributionLink;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PassDistributionLink>
 */
class PassDistributionLinkFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pass_id' => Pass::factory(),
            'slug' => Str::uuid(),
            'status' => 'active',
            'last_accessed_at' => null,
            'accessed_count' => 0,
        ];
    }

    /**
     * Mark the link as disabled.
     *
     * @return static
     */
    public function disabled()
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'disabled',
        ]);
    }

    /**
     * Mark the link as accessed.
     *
     * @return static
     */
    public function accessed(int $count = 1)
    {
        return $this->state(fn (array $attributes) => [
            'last_accessed_at' => now(),
            'accessed_count' => $count,
        ]);
    }
}
```

---

## Conclusion

These contracts define the complete interface between frontend, backend, and API for the Pass Distribution System feature. All types are consistent, well-documented, and follow Laravel and TypeScript conventions.
