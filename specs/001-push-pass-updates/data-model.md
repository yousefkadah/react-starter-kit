# Data Model: Push Notifications & Real-Time Pass Updates

**Spec**: [spec.md](spec.md) | **Plan**: [plan.md](plan.md) | **Research**: [research.md](research.md)

---

## Entity Relationship Overview

```
User 1──N AppleCertificate
User 1──N GoogleCredential
User 1──N PassTemplate 1──N Pass
Pass 1──N DeviceRegistration
Pass 1──N PassUpdate
PassTemplate 1──N BulkUpdate
PassUpdate N──1 Pass
DeviceRegistration ──references── Pass (via serial_number + pass_type_identifier)
```

---

## Existing Tables (Modified)

### `passes` — Add authentication_token column

| Column | Type | Change | Notes |
|--------|------|--------|-------|
| `authentication_token` | `string(64)`, unique, not null | **ADD** | UUID or random hex token included in pass.json for Apple Web Service Protocol auth. Generated on pass creation. |

**Migration**: `xxxx_add_authentication_token_to_passes_table.php`

**Impact**: 
- `ApplePassService::buildPassJson()` must include `authenticationToken` and `webServiceURL` fields
- Existing passes without tokens need a backfill migration (generate token for all existing passes)

---

## New Tables

### `device_registrations` — Apple device ↔ pass association

Stores which Apple devices have installed which passes, along with the push token needed to notify them of updates.

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| `id` | bigint PK | no | auto | |
| `device_library_identifier` | string(64) | no | — | Apple-assigned unique device identifier |
| `push_token` | string(128) | no | — | APNS push token for this device |
| `pass_type_identifier` | string(128) | no | — | e.g., `pass.com.example.loyalty` |
| `serial_number` | string(64) | no | — | Pass serial number (references `passes.serial_number`) |
| `user_id` | bigint FK → users | no | — | Owner of the pass (for tenant scoping) |
| `is_active` | boolean | no | true | Set to false when push token is invalid |
| `created_at` | timestamp | no | — | Registration time |
| `updated_at` | timestamp | no | — | Last update time |

**Indexes**:
- `unique(device_library_identifier, pass_type_identifier, serial_number)` — Apple requires this uniqueness
- `index(serial_number)` — For querying all devices registered to a pass
- `index(user_id, is_active)` — For tenant-scoped queries
- `index(push_token)` — For push token lookup/dedup

**Relationships**:
- `pass()` → BelongsTo Pass (via serial_number lookup, not FK — Apple protocol uses serial_number)
- `user()` → BelongsTo User

**Traits**: `HasFactory`

**Notes**: No `ScopedByRegion` trait — scoped by `user_id` directly since Apple's Web Service Protocol doesn't go through standard auth middleware. The `user_id` is derived from the pass's owner at registration time.

---

### `pass_updates` — Audit log of field changes

Records every field change made to a pass, who initiated it, and the resulting push delivery status.

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| `id` | bigint PK | no | auto | |
| `pass_id` | bigint FK → passes | no | — | cascadeOnDelete |
| `user_id` | bigint FK → users | yes | — | null for API/system-initiated updates |
| `bulk_update_id` | bigint FK → bulk_updates | yes | null | Links to parent bulk update if part of one |
| `source` | string(20) | no | — | `dashboard`, `api`, `bulk`, `system` |
| `fields_changed` | json | no | — | `{"balance": {"old": 50, "new": 75}, "promotion": {"old": "...", "new": "..."}}` |
| `apple_delivery_status` | string(20) | yes | null | `pending`, `sent`, `delivered`, `failed`, `skipped` |
| `google_delivery_status` | string(20) | yes | null | `pending`, `sent`, `delivered`, `failed`, `skipped` |
| `apple_devices_notified` | integer | no | 0 | Count of Apple devices push was sent to |
| `google_updated` | boolean | no | false | Whether Google Wallet object was patched |
| `error_message` | text | yes | null | Error details if delivery failed |
| `created_at` | timestamp | no | — | When the update was initiated |
| `updated_at` | timestamp | no | — | |

**Indexes**:
- `index(pass_id, created_at)` — For pass update history, ordered by time
- `index(bulk_update_id)` — For bulk update tracking
- `index(created_at)` — For 90-day pruning job
- `index(user_id)` — For user's update activity

**Relationships**:
- `pass()` → BelongsTo Pass
- `user()` → BelongsTo User
- `bulkUpdate()` → BelongsTo BulkUpdate (nullable)

**Traits**: `HasFactory`

**Pruning**: Records older than 90 days are deleted by `PrunePassUpdateHistoryJob` (scheduled daily). Aggregate counts are preserved in a separate summary before deletion (future analytics spec).

---

### `bulk_updates` — Bulk update job tracking

Tracks the lifecycle and progress of bulk update operations across multiple passes of a template.

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| `id` | bigint PK | no | auto | |
| `user_id` | bigint FK → users | no | — | cascadeOnDelete |
| `pass_template_id` | bigint FK → pass_templates | no | — | cascadeOnDelete |
| `field_key` | string(100) | no | — | Which field is being updated |
| `field_value` | text | no | — | The new value |
| `filters` | json | yes | null | `{"status": "active", "platform": "apple"}` |
| `status` | string(20) | no | `pending` | `pending`, `processing`, `completed`, `failed`, `cancelled` |
| `total_count` | integer | no | 0 | Total passes to update |
| `processed_count` | integer | no | 0 | Passes updated so far |
| `failed_count` | integer | no | 0 | Passes that failed to update |
| `started_at` | timestamp | yes | null | When processing began |
| `completed_at` | timestamp | yes | null | When processing finished |
| `created_at` | timestamp | no | — | |
| `updated_at` | timestamp | no | — | |

**Indexes**:
- `index(pass_template_id, status)` — For mutex check (reject if `processing` exists)
- `index(user_id, created_at)` — For user's bulk update history
- `index(status)` — For dashboard filtering

**Relationships**:
- `user()` → BelongsTo User
- `passTemplate()` → BelongsTo PassTemplate
- `passUpdates()` → HasMany PassUpdate

**Traits**: `HasFactory`

**Mutex enforcement**: Before creating a new `BulkUpdate`, query: `BulkUpdate::where('pass_template_id', $templateId)->whereIn('status', ['pending', 'processing'])->exists()`. If true, reject the new request.

---

## Existing Model Changes

### Pass Model (`app/Models/Pass.php`)

**New fields**:
- `authentication_token` — add to `$fillable` and `$hidden` arrays

**New relationships**:
- `deviceRegistrations()` → HasMany DeviceRegistration (via `serial_number` column match)
- `passUpdates()` → HasMany PassUpdate

**New methods**:
- `hasRegisteredDevices(): bool` — Check if any active device registrations exist
- `activeDeviceRegistrations()` — Scope to `is_active = true`

**New casts**:
```php
protected function casts(): array
{
    return [
        // ... existing casts
        'authentication_token' => 'string',
    ];
}
```

### PassTemplate Model (`app/Models/PassTemplate.php`)

**New relationships**:
- `bulkUpdates()` → HasMany BulkUpdate

**New methods**:
- `hasBulkUpdateInProgress(): bool` — Check mutex

### User Model (`app/Models/User.php`)

**New relationships**:
- `deviceRegistrations()` → HasMany DeviceRegistration
- `passUpdates()` → HasMany PassUpdate
- `bulkUpdates()` → HasMany BulkUpdate

---

## State Transitions

### Pass Update Delivery Status

```
pending → sent → delivered
pending → sent → failed → (retry up to 3x) → failed (final)
pending → skipped (no devices registered / voided pass / expired cert)
```

### Bulk Update Status

```
pending → processing → completed
pending → processing → failed
pending → cancelled (user cancellation)
```

### Device Registration Active Status

```
active (registered) → inactive (push token invalid / APNS 410 response)
active → removed (device unregistered via DELETE endpoint)
```

---

## Validation Rules

### UpdatePassFieldsRequest

| Field | Rules |
|-------|-------|
| `fields` | required, array, min:1 |
| `fields.*` | required, string (field key must exist in pass template's field map) |
| `fields.*.value` | required (type depends on field definition) |
| `change_messages` | optional, array |
| `change_messages.*` | string, max:256 |

### BulkUpdatePassesRequest

| Field | Rules |
|-------|-------|
| `pass_template_id` | required, exists:pass_templates,id (scoped to user) |
| `field_key` | required, string (must exist in template's field map) |
| `field_value` | required |
| `filters` | optional, array |
| `filters.status` | optional, in:active |
| `filters.platform` | optional, in:apple,google |
