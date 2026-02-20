# Contract: Pass Distribution Routes

**Date**: February 12, 2026  
**Feature**: Pass Distribution System  
**Output of**: `/speckit.plan` Phase 1

---

## Overview

This document defines all HTTP routes and contracts for the Pass Distribution System feature.

---

## Routes

### 1. Public Pass Link View (Unauthenticated)

```
GET /p/{slug}
```

#### Purpose
Display pass in device-aware format with add-to-wallet button. No authentication required.

#### Parameters

| Name | Type | Location | Required | Notes |
|------|------|----------|----------|-------|
| `slug` | string(36) | URL path | Yes | UUIDv4; route parameter |

#### Request Headers

| Header | Value | Notes |
|--------|-------|-------|
| `User-Agent` | (auto) | Used for device detection (iOS/Android/Desktop) |

#### Response (200 OK)

**Content-Type**: `text/html` (Inertia response)

**Body**:
```json
{
  "component": "PassLink",
  "props": {
    "pass": {
      "id": 1,
      "type": "loyalty_card",
      "name": "Coffee Shop Loyalty",
      "description": "Earn points with every purchase",
      "icon_url": "https://...",
      "logo_url": "https://...",
      "serial_number": "ABC123",
      "barcodes": [
        {
          "format": "qr",
          "value": "1234567890"
        }
      ]
    },
    "device": "ios",  // or "android", "desktop", "unknown"
    "link_status": "active",  // or "expired", "disabled", "voided"
    "message": null,  // or error/info message
    "add_to_wallet_url": {
      "apple": "https://wallet.apple.com/join/...",  // .pkpass download URL
      "google": "https://pay.google.com/save/..."  // Google Pay save URL
    },
    "qr_code_data": {
      "text": "https://app.passkit.local/p/550e8400-e29b-41d4-a716-446655440000",
      "width": 200,
      "height": 200
    }
  }
}
```

#### Response (404 Not Found)

**Body**:
```json
{
  "message": "Pass link not found or has been disabled."
}
```

#### Response (410 Gone)

**Body** (if pass is voided):
```json
{
  "message": "This pass is no longer valid and cannot be enrolled."
}
```

#### Side Effects
- Updates `PassDistributionLink.last_accessed_at` and `accessed_count` on successful view
- No authentication check (public endpoint)

#### Device Detection

Determines which add-to-wallet action to present:

| Device | Behavior |
|--------|----------|
| iOS | Show Apple Wallet button; hide Google Pay |
| Android | Show Google Pay button; hide Apple Wallet |
| Desktop | Show both options (fallback) |
| Unknown | Show both options (fallback) |

---

### 2. List Pass Distribution Links (Authenticated)

```
GET /dashboard/passes/{pass}/distribution-links
```

#### Purpose
List all distribution links for a specific pass (admin/issuer view).

#### Parameters

| Name | Type | Location | Required | Notes |
|------|------|----------|----------|-------|
| `pass` | integer | URL path | Yes | Pass ID; route parameter |

#### Request Headers

| Header | Value | Notes |
|--------|-------|-------|
| `Authorization` | Bearer {token} | Optional; required for API version |

#### Authorization

User must be authenticated and have `viewDistributionLinks` policy on the Pass.

#### Response (200 OK)

**Content-Type**: `application/json` or `text/html` (Inertia)

**Body** (JSON):
```json
{
  "data": [
    {
      "id": 1,
      "pass_id": 123,
      "slug": "550e8400-e29b-41d4-a716-446655440000",
      "status": "active",
      "url": "https://app.passkit.local/p/550e8400-e29b-41d4-a716-446655440000",
      "last_accessed_at": "2026-02-12T15:30:00Z",
      "accessed_count": 42,
      "created_at": "2026-02-11T10:00:00Z",
      "updated_at": "2026-02-12T15:30:00Z"
    }
  ],
  "pagination": {
    "total": 1,
    "current_page": 1,
    "per_page": 15,
    "last_page": 1
  }
}
```

#### Response (403 Forbidden)

User does not have permission to view this pass.

#### Response (404 Not Found)

Pass not found.

---

### 3. Create Pass Distribution Link (Authenticated)

```
POST /dashboard/passes/{pass}/distribution-links
```

#### Purpose
Generate a new distribution link for a pass.

#### Parameters

| Name | Type | Location | Required | Notes |
|------|------|----------|----------|-------|
| `pass` | integer | URL path | Yes | Pass ID; route parameter |

#### Request Body

Empty body (all link data auto-generated).

```json
{}
```

#### Authorization

User must be authenticated and have `createDistributionLink` policy on the Pass.

#### Response (201 Created)

**Location Header**: `https://app.passkit.local/dashboard/passes/123/distribution-links/1`

**Content-Type**: `application/json`

**Body**:
```json
{
  "data": {
    "id": 2,
    "pass_id": 123,
    "slug": "a1b2c3d4-e5f6-4a5b-9c8d-7e6f5a4b3c2d",
    "status": "active",
    "url": "https://app.passkit.local/p/a1b2c3d4-e5f6-4a5b-9c8d-7e6f5a4b3c2d",
    "last_accessed_at": null,
    "accessed_count": 0,
    "created_at": "2026-02-12T16:00:00Z",
    "updated_at": "2026-02-12T16:00:00Z"
  }
}
```

#### Response (403 Forbidden)

User does not have permission to create a link for this pass.

#### Response (404 Not Found)

Pass not found.

---

### 4. Disable/Enable Pass Distribution Link (Authenticated)

```
PATCH /dashboard/passes/{pass}/distribution-links/{link}
```

#### Purpose
Change link status (active ↔ disabled).

#### Parameters

| Name | Type | Location | Required | Notes |
|------|------|----------|----------|-------|
| `pass` | integer | URL path | Yes | Pass ID |
| `link` | integer | URL path | Yes | PassDistributionLink ID |

#### Request Body

```json
{
  "status": "disabled"  // or "active"
}
```

#### Authorization

User must be authenticated and have `updateDistributionLink` policy on the link.

#### Response (200 OK)

```json
{
  "data": {
    "id": 2,
    "pass_id": 123,
    "slug": "a1b2c3d4-e5f6-4a5b-9c8d-7e6f5a4b3c2d",
    "status": "disabled",
    "url": "https://app.passkit.local/p/a1b2c3d4-e5f6-4a5b-9c8d-7e6f5a4b3c2d",
    "last_accessed_at": "2026-02-12T15:30:00Z",
    "accessed_count": 42,
    "created_at": "2026-02-11T10:00:00Z",
    "updated_at": "2026-02-12T16:05:00Z"
  }
}
```

#### Response (422 Unprocessable Entity)

Invalid status value.

```json
{
  "message": "The status field must be one of: active, disabled."
}
```

#### Response (403 Forbidden)

User does not have permission to update this link.

#### Response (404 Not Found)

Pass or link not found.

---

## Route Registration

### In `routes/web.php` (or `routes/passes.php`):

```php
Route::middleware(['auth:sanctum', 'verified'])->prefix('dashboard/passes')->group(
    function () {
        Route::get('/{pass}/distribution-links', PassDistributionController::class . '@index')
            ->name('passes.distribution-links.index');

        Route::post('/{pass}/distribution-links', PassDistributionController::class . '@store')
            ->name('passes.distribution-links.store');

        Route::patch('/{pass}/distribution-links/{link}', PassDistributionController::class . '@update')
            ->name('passes.distribution-links.update');
    }
);

// Public route (no auth required)
Route::get('/p/{slug}', PassDistributionController::class . '@show')
    ->where('slug', '[a-f0-9\-]{36}')  // UUID format validation
    ->name('passes.show-by-link');
```

---

## Wayfinder Route Helpers

### Frontend Navigation

```typescript
// Import from @/routes
import { route } from '@/routes';

// Generate link to distribution list
const listUrl = route('passes.distribution-links.index', { pass: 123 });
// → /dashboard/passes/123/distribution-links

// Generate link to create endpoint
const createUrl = route('passes.distribution-links.store', { pass: 123 });
// → /dashboard/passes/123/distribution-links (POST method)

// Generate public pass link
const publicUrl = route('passes.show-by-link', { slug: 'abc123...' });
// → /p/abc123...
```

### Backend Route Helpers

```php
// In controllers/views
route('passes.distribution-links.index', ['pass' => $pass->id]);
route('passes.distribution-links.store', ['pass' => $pass->id]);
route('passes.distribution-links.update', ['pass' => $pass->id, 'link' => $link->id]);
route('passes.show-by-link', ['slug' => $link->slug]);
```

---

## Data Contract: Request/Response Objects

### Request: StorePassDistributionLinkRequest

```php
class StorePassDistributionLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('createDistributionLink', $this->route('pass'));
    }

    public function rules(): array
    {
        return [];  // No user input; all auto-generated
    }
}
```

### Request: UpdatePassDistributionLinkRequest

```php
class UpdatePassDistributionLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('updateDistributionLink', $this->route('link'));
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'in:active,disabled'],
        ];
    }
}
```

### Response: PassDistributionLinkResource

```php
class PassDistributionLinkResource extends JsonResource
{
    public function toArray($request)
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

## Error Handling

### HTTP Status Codes

| Code | Scenario |
|------|----------|
| 200 | Successful GET or PATCH |
| 201 | Successful POST (resource created) |
| 404 | Pass or link not found |
| 403 | User not authorized |
| 410 | Pass is voided (cannot enroll) |
| 422 | Validation error (invalid status) |
| 500 | Server error |

### Error Response Format

```json
{
  "message": "User error message",
  "errors": {
    "status": ["The status field must be one of: active, disabled."]
  }
}
```

---

## Rate Limiting

- **Public endpoint** (`GET /p/{slug}`): No rate limit (encourage shares to scaling)
- **Authenticated endpoints**: Standard Laravel rate limits (60 requests/minute per user)

---

## CORS & Security Headers

- **Public endpoint**: Allow all origins (encourage embedding)
- **Authenticated endpoints**: Standard CSRF token validation via Session or Sanctum token

---

## Conclusion

Routes are minimal, focused on the core feature, and follow Laravel conventions. All responses use consistent JSON schema with Wayfinder route helpers for frontend integration.
