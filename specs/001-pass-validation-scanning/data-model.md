# Data Model: Pass Validation & Scanning

## Entities

### 1. `ScannerLink`
Represents a unique, long-lived URL token used by staff to access the web-based scanning interface without a full user login.

**Fields**:
- `id` (uuid, primary key)
- `tenant_id` (uuid, foreign key to `tenants`)
- `name` (string) - e.g., "Front Register 1"
- `token` (string, unique) - Cryptographically secure random string (e.g., 40 chars)
- `is_active` (boolean) - Default true
- `last_used_at` (timestamp, nullable)
- `created_at` (timestamp)
- `updated_at` (timestamp)

**Relationships**:
- Belongs to `Tenant`
- Has many `ScanEvent`s

### 2. `Pass` (Existing Entity Updates)
The existing `Pass` model needs to track its lifecycle state and usage type.

**New/Updated Fields**:
- `usage_type` (enum/string) - `single_use` or `multi_use`
- `status` (enum/string) - `active`, `redeemed`, `voided`, `expired`
- `custom_redemption_message` (text, nullable)
- `redeemed_at` (timestamp, nullable)

**Relationships**:
- Has many `ScanEvent`s

### 3. `ScanEvent`
Represents a single instance of a pass being scanned or redeemed.

**Fields**:
- `id` (uuid, primary key)
- `tenant_id` (uuid, foreign key to `tenants`)
- `pass_id` (uuid, foreign key to `passes`)
- `scanner_link_id` (uuid, foreign key to `scanner_links`, nullable)
- `action` (enum/string) - `scan`, `redeem`
- `result` (enum/string) - `success`, `invalid_signature`, `already_redeemed`, `voided`, `expired`, `not_found`
- `ip_address` (string, nullable)
- `user_agent` (string, nullable)
- `created_at` (timestamp)
- `updated_at` (timestamp)

**Relationships**:
- Belongs to `Tenant`
- Belongs to `Pass`
- Belongs to `ScannerLink`

## State Transitions (Pass)

- **Active** -> **Redeemed**: When a `single_use` pass is successfully redeemed.
- **Active** -> **Voided**: When an admin manually voids a pass.
- **Active** -> **Expired**: When the pass's expiration date passes.
