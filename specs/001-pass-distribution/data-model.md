# Data Model: Pass Distribution System

**Date**: February 12, 2026  
**Feature**: Pass Distribution System  
**Output of**: `/speckit.plan` Phase 1

---

## Entity Diagram

```
┌─────────────────────────┐
│        Pass             │
├─────────────────────────┤
│ id (PK)                 │
│ user_id (FK)            │
│ pass_template_id (FK)   │
│ status                  │
│ created_at              │
│ updated_at              │
└────────────┬────────────┘
             │ 1:M
             │
             ▼
┌─────────────────────────┐
│ PassDistributionLink    │
├─────────────────────────┤
│ id (PK)                 │
│ pass_id (FK)            │
│ slug (UNIQUE INDEX)     │
│ status                  │
│ last_accessed_at        │
│ accessed_count          │
│ created_at              │
│ updated_at              │
└─────────────────────────┘
```

---

## Entity: PassDistributionLink

### Purpose
Represents a shareable, device-aware link for a specific pass. Enables issuers to distribute passes without sharing files directly.

### Schema

```sql
CREATE TABLE pass_distribution_links (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    pass_id BIGINT UNSIGNED NOT NULL,
    slug VARCHAR(36) NOT NULL UNIQUE,
    status ENUM('active', 'disabled') NOT NULL DEFAULT 'active',
    last_accessed_at TIMESTAMP NULL,
    accessed_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    CONSTRAINT fk_pass_distribution_link_pass
        FOREIGN KEY (pass_id) 
        REFERENCES passes(id) 
        ON DELETE CASCADE,
    
    INDEX idx_slug (slug),
    INDEX idx_pass_id (pass_id),
    INDEX idx_created_at (created_at)
);
```

### Attributes

| Column | Type | Nullable | Indexed | Notes |
|--------|------|----------|---------|-------|
| `id` | BIGINT | No | Yes (PK) | Auto-incrementing primary key |
| `pass_id` | BIGINT | No | Yes (FK) | Reference to passes table; cascades on delete |
| `slug` | VARCHAR(36) | No | Yes (UNIQUE) | UUIDv4 string; unguessable identifier; route parameter |
| `status` | ENUM | No | — | 'active' or 'disabled'; controls link access |
| `last_accessed_at` | TIMESTAMP | Yes | — | Null until first access; updated on every view |
| `accessed_count` | INT UNSIGNED | No | — | Incremented on each view; 0 if never accessed |
| `created_at` | TIMESTAMP | No | Yes | Record creation time |
| `updated_at` | TIMESTAMP | No | — | Record update time |

### Relationships

#### Belongs To: `pass()`

```php
public function pass()
{
    return $this->belongsTo(Pass::class, 'pass_id');
}
```

- **Type**: Many-to-one (via `pass_id` foreign key)
- **Inverse**: `Pass::hasMany('distributionLinks')`
- **Cascade**: Deleting the pass deletes all associated links
- **Eager Load Context**: Always eager-load when fetching links to avoid N+1 queries

**Example Usage**:
```php
$link = PassDistributionLink::with('pass')->find($linkId);
$pass = $link->pass;  // No additional query
```

### Accessors & Computed Properties

#### `isActive()`
```php
public function isActive(): bool
{
    return $this->status === 'active';
}
```

#### `isDisabled()`
```php
public function isDisabled(): bool
{
    return $this->status === 'disabled';
}
```

#### `hasBeenAccessed()`
```php
public function hasBeenAccessed(): bool
{
    return $this->accessed_count > 0;
}
```

#### `daysOld()`
```php
public function daysOld(): int
{
    return $this->created_at->diffInDays(now());
}
```

#### `url(): string`
```php
public function url(): string
{
    return route('passes.show-by-link', ['slug' => $this->slug]);
}
```

### Lifecycle Events

#### `creating` (Eloquent Model Event)
Ensure slug is generated before insert (or apply in factory/service layer).

#### `updated`
Log changes to status for audit trail (implement in Service layer if needed).

---

## Entity: Pass (Extended)

### New Relationship

```php
public function distributionLinks()
{
    return $this->hasMany(PassDistributionLink::class);
}
```

### Mutation Helper

```php
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

### Deletion Cascade

- When a Pass is soft-deleted or hard-deleted, all associated PassDistributionLink records are cascade-deleted
- No orphaned links remain

---

## Validation Rules

### PassDistributionLink Creation (Form Request)

```php
class StorePassDistributionLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewDistributionLinks', $this->pass);
    }

    public function rules(): array
    {
        return [
            // No user input required; slug auto-generated
            // Validation only if future features add customizable slugs
        ];
    }
}
```

### PassDistributionLink Status Update

```php
class UpdatePassDistributionLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('updateDistributionLink', $this->link);
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'in:active,disabled'],
        ];
    }
}
```

---

## Data Integrity Constraints

### Uniqueness
- **slug**: Checked at application level (UUIDv4 collision probability negligible)
- **Database constraint**: UNIQUE INDEX on `slug` column prevents duplicates

### Referential Integrity
- **Foreign Key**: pass_id references passes(id) with ON DELETE CASCADE
- **No orphans**: Deleting a pass automatically removes related links
- **Cascade behavior**: Tested in PassDistributionLinkTest

### State Transitions

```
┌─────────┐
│ active  │ ◄──────┐
└────┬────┘        │
     │             │
     ▼             │
┌─────────┐        │
│disabled │ ────────┘
└─────────┘
```

- Initial state: `active` (on creation)
- Allowed transitions: `active` ↔ `disabled` (bidirectional)
- No other states

---

## Indexing Strategy

### Primary Index
- **Column**: `id` (PK)
- **Type**: BTREE (auto)
- **Purpose**: Record lookup and foreign key target

### Unique Index
- **Column**: `slug`
- **Type**: UNIQUE BTREE
- **Purpose**: Ensure no duplicate slugs; used for route lookup
- **Query Pattern**: `SELECT * FROM pass_distribution_links WHERE slug = ?`

### Foreign Key Index
- **Column**: `pass_id`
- **Type**: BTREE
- **Purpose**: Fast lookups for "find all links for this pass"
- **Query Pattern**: `SELECT * FROM pass_distribution_links WHERE pass_id = ?`

### Timestamp Index
- **Column**: `created_at`
- **Type**: BTREE
- **Purpose**: Analytics queries: "links created in past 7 days"
- **Query Pattern**: `SELECT COUNT(*) FROM pass_distribution_links WHERE created_at >= ?`

### Query Performance Targets

| Query | Execution Time | Index Used |
|-------|----------------|------------|
| `SELECT * FROM pass_distribution_links WHERE slug = ?` | <1ms | UNIQUE(slug) |
| `SELECT * FROM pass_distribution_links WHERE pass_id = ? ORDER BY created_at DESC` | <5ms | FK(pass_id) + created_at |
| `SELECT COUNT(*) FROM pass_distribution_links WHERE status = 'active'` | <20ms | Full scan (acceptable; cardinality low) |

---

## Audit & Analytics

### Access Tracking

```php
// Automatic on link view
public function recordAccess(): void
{
    $this->update([
        'last_accessed_at' => now(),
        'accessed_count' => $this->accessed_count + 1,
    ]);
}
```

### Analytics Query Examples

```php
// Links accessed in past 7 days
PassDistributionLink::where('last_accessed_at', '>=', now()->subDays(7))->get();

// Links never accessed
PassDistributionLink::whereNull('last_accessed_at')->get();

// Most popular link for a pass
Pass::find($passId)->distributionLinks()->orderByDesc('accessed_count')->first();

// Active vs disabled link distribution
PassDistributionLink::where('status', 'active')->count();  // active
PassDistributionLink::where('status', 'disabled')->count();  // disabled
```

---

## Future Extensibility

### Potential Future Columns (not in MVP)
- `custom_slug`: Allow issuers to set branded slugs (e.g., `/p/my-event-2024`)
- `expires_at`: Auto-disable link after date
- `max_uses`: Limit link access count before auto-disable
- `metadata`: JSON column for custom tracking tags

### Future Relationships
- `PassDistributionLinkAccess`: Track individual access events with IP, device, timestamp
- `PassDistributionLinkEvent`: Audit log of status changes

These can be added without modifying the existing schema (just add columns/tables).

---

## Migration File Template

```php
// database/migrations/2026_02_12_create_pass_distribution_links_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pass_distribution_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pass_id')->constrained()->cascadeOnDelete();
            $table->string('slug', 36)->unique();
            $table->enum('status', ['active', 'disabled'])->default('active');
            $table->timestamp('last_accessed_at')->nullable();
            $table->unsignedInteger('accessed_count')->default(0);
            $table->timestamps();

            $table->index('slug');
            $table->index('pass_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pass_distribution_links');
    }
};
```

---

## Model File Template

```php
// app/Models/PassDistributionLink.php

namespace App\Models;

use Database\Factories\PassDistributionLinkFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PassDistributionLink extends Model
{
    use HasFactory;

    protected $fillable = ['pass_id', 'slug', 'status', 'last_accessed_at', 'accessed_count'];

    protected $casts = [
        'last_accessed_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (PassDistributionLink $link) {
            if (!$link->slug) {
                $link->slug = Str::uuid()->toString();
            }
        });
    }

    public function pass(): BelongsTo
    {
        return $this->belongsTo(Pass::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isDisabled(): bool
    {
        return $this->status === 'disabled';
    }

    public function recordAccess(): void
    {
        $this->update([
            'last_accessed_at' => now(),
            'accessed_count' => $this->accessed_count + 1,
        ]);
    }

    public function url(): string
    {
        return route('passes.show-by-link', ['slug' => $this->slug]);
    }
}
```

---

## Conclusion

The PassDistributionLink model is minimal, focused, and extensible. It integrates cleanly with the existing Pass model and follows Laravel conventions. No breaking changes to existing schema required.
