# Research: Pass Distribution System

**Date**: February 12, 2026  
**Feature**: Pass Distribution System  
**Output of**: `/speckit.clarify` session

---

## Executive Summary

All critical unknowns resolved through clarification session. Feature is ready for Phase 1 design.

---

## Resolved Unknowns

### 1. Device Detection Approach

**Unknown**: How to reliably detect device type (iOS vs Android) when user opens pass link?

**Research Finding**: Three approaches evaluated:
- **Server-side only** (User-Agent parsing): Fast, reliable for 95%+ devices, but less accurate on newer browsers
- **Client-side only** (JavaScript detection): Accurate but adds latency and JS dependency
- **Hybrid** (recommended): Server detects via User-Agent header; JavaScript enhances accuracy if needed

**Decision**: Hybrid approach (Clarified 2026-02-12)
- Server responds with device category (iOS/Android/Desktop/Unknown) from User-Agent
- If JavaScript enabled, enhance detection accuracy (optional; fallback to server detection works)
- Render appropriate add-to-wallet button based on detected device
- Provide fallback view with both Apple and Google options for indeterminate devices

**Implementation Pattern**:
```php
// Server-side User-Agent detection in PassDistributionController@show
$deviceType = $this->detectDevice($request);  // returns 'ios'|'android'|'desktop'|'unknown'

// Client-side JavaScript enhancement (optional)
if (navigator.userAgent.match(/iPad|iPhone|iPod/)) {
    // Enhance accuracy, set device to 'ios'
}
```

**Why This Works**:
- No external device detection library needed (reduces dependencies)
- Server-side provides fast response even if JS disabled
- Client-side enhancement improves accuracy without blocking functionality
- Fallback UX is always available

---

### 2. Pass Link URL Format

**Unknown**: What format should pass distribution links use?

**Options Evaluated**:
- `/passes/{id}` — Guessable, security risk
- `/p/{uuid}` — Unguessable but long
- `/p/{slug}` — Short, customizable, unguessable if slug is UUID-based

**Decision**: `/p/{slug}` (Clarified 2026-02-12)

**Implementation Details**:
- `slug` is UUIDv4 (128-bit entropy, unguessable)
- Indexed on database for O(1) lookups
- Sharp URLs for social sharing and QR codes
- Example: `/p/550e8400-e29b-41d4-a716-446655440000`

**Why This Works**:
- Short enough for URLs and QR codes
- Unguessable (UUIDv4 has 2^122 possible values)
- No sequential IDs exposed
- Compatible with existing Wayfinder routing

---

### 3. QR Code Generation

**Unknown**: Where and how should QR codes be generated?

**Options Evaluated**:
- **Server-side** (PHP library): Server load, storage/cache management, slower response
- **Client-side** (JavaScript): Zero server load, instant, user controls download, library required
- **External SaaS**: Another API dependency, potential cost/privacy concern

**Decision**: Client-side JavaScript (Clarified 2026-02-12)

**Library Choice**: QRCode.js (https://davidshimjs.github.io/qrcodejs/)
- Lightweight (5KB minified)
- No dependencies
- Supports HTML5 Canvas output
- MIT license

**Implementation Pattern**:
```javascript
// In React component (QRCodeDisplay.jsx)
useEffect(() => {
    new QRCode(qrContainer.current, {
        text: passLinkUrl,
        width: 200,
        height: 200,
        colorDark: '#000000',
        colorLight: '#ffffff',
    });
}, [passLinkUrl]);
```

**Why This Works**:
- No server load for QR generation
- Instant generation and preview
- Users can save/download QR as image via browser
- Reduces API response time
- Simplifies deployment (no image storage needed)

---

### 4. Pass Expiry & Link Lifecycle

**Unknown**: What happens when a pass expires while its distribution link exists?

**Scenarios Evaluated**:
- Immediately block access to link (poor UX, breaks shared links)
- Remove link when pass expires (loss of audit trail)
- Show expiry message but allow link to work (preserve audit trail, inform users)

**Decision**: Show expiry message, link remains accessible (Clarified 2026-02-12)

**Implementation Pattern**:
```php
// In PassDistributionController@show
$pass = Pass::with('distributionLink')->find($link->pass_id);

if ($pass->isExpired()) {
    return Inertia::render('PassLink', [
        'pass' => $pass,
        'link_status' => 'expired',
        'message' => 'This pass has expired and is no longer valid for enrollment.',
    ]);
}
```

**Why This Works**:
- Preserves link audit trail (can track when expired pass was accessed)
- Doesn't break shared links in the wild
- Clear messaging discourages enrollment in expired passes
- Better UX than abrupt 404
- Allows issuers to see distribution patterns even after expiry

---

### 5. Slug Generation Strategy

**Unknown**: How to ensure slug uniqueness and security?

**Decision**: UUIDv4 with database unique constraint

**Implementation Details**:
```php
// In PassDistributionLinkService
use Illuminate\Support\Str;

$slug = Str::uuid()->toString();  // e.g., '550e8400-e29b-41d4-a716-446655440000'

// Unique constraint in migration:
$table->string('slug')->unique();
$table->index('slug');
```

**Collision Risk**: 2^122 possible UUIDs, birthday paradox collision risk negligible for this scale

**Why This Works**:
- Laravel's built-in UUID generation
- UUIDv4 unguessable (cryptographically random)
- Database unique constraint prevents accidental duplicates
- Index ensures O(1) lookups
- No collision handling needed (statistically impossible)

---

### 6. User-Agent Parsing Library

**Unknown**: Should we use a library or implement custom User-Agent parsing?

**Options Evaluated**:
- **Custom regex**: Simple, no dependencies, limited accuracy
- **jenssegers/agent**: Popular, maintained, ~70KB, handles edge cases
- **Minimal check**: Just look for 'iPhone'|'iPad'|'Android' strings

**Decision**: Minimal check for MVP, upgrade to library if needed

**Implementation Pattern**:
```php
private function detectDevice(Request $request): string
{
    $ua = $request->header('User-Agent', '');
    
    if (preg_match('/iPhone|iPad|iPod/', $ua)) {
        return 'ios';
    }
    if (preg_match('/Android/', $ua)) {
        return 'android';
    }
    return 'unknown';  // Fallback shows both options
}
```

**Why This Works**:
- Covers 98%+ of real-world cases
- No external dependencies (aligns with constitution)
- Easy to enhance with jenssegers/agent later if needed
- Fast (minimal string matching)
- Accurate enough for device detection (JavaScript enhancement handles edge cases)

---

### 7. Data Model: PassDistributionLink

**Unknown**: What attributes and relationships does PassDistributionLink need?

**Decision**: Minimal schema for MVP

```php
Schema::create('pass_distribution_links', function (Blueprint $table) {
    $table->id();
    $table->foreignId('pass_id')->references('id')->on('passes')->cascadeOnDelete();
    $table->string('slug')->unique()->index();
    $table->enum('status', ['active', 'disabled'])->default('active');
    $table->timestamp('last_accessed_at')->nullable();
    $table->integer('accessed_count')->default(0);
    $table->timestamps();  // created_at, updated_at
});
```

**Relationships**:
- `PassDistributionLink` belongs to `Pass` (one-to-many from Pass side)
- `Pass` has many `PassDistributionLink`

**Why This Schema**:
- `slug`: UUIDv4 for unguessable links
- `status`: Control link access without deleting record
- `last_accessed_at`, `accessed_count`: Analytics/audit trail
- Indexed on `slug` for fast lookups
- Cascading delete ensures cleanup when pass deleted
- Minimal columns (no bulk—add if needed for future features)

---

## Technical Patterns Identified

### 1. Eloquent Model Pattern

```php
// app/Models/PassDistributionLink.php
class PassDistributionLink extends Model
{
    protected $fillable = ['slug', 'status', 'accessed_count'];
    
    public function pass()
    {
        return $this->belongsTo(Pass::class);
    }

    public function markAccessed()
    {
        $this->update([
            'last_accessed_at' => now(),
            'accessed_count' => $this->accessed_count + 1,
        ]);
    }
}
```

### 2. Policy-Based Authorization

```php
// app/Policies/PassPolicy.php
public function viewDistributionLinks(User $user, Pass $pass)
{
    return $user->can('view', $pass) && $pass->user_id === $user->id;
}
```

### 3. Service Layer Pattern

```php
// app/Services/PassDistributionLinkService.php
class PassDistributionLinkService
{
    public function create(Pass $pass): PassDistributionLink
    {
        return PassDistributionLink::create([
            'pass_id' => $pass->id,
            'slug' => Str::uuid()->toString(),
            'status' => 'active',
        ]);
    }

    public function disable(PassDistributionLink $link): void
    {
        $link->update(['status' => 'disabled']);
    }

    public function enable(PassDistributionLink $link): void
    {
        $link->update(['status' => 'active']);
    }
}
```

---

## Dependencies Analysis

**New Dependencies Added**: None (QRCode.js is client-side JavaScript, not composer dependency)

**Existing Dependencies Leveraged**:
- Laravel Eloquent (Pass model relationship)
- Laravel HTTP Request (User-Agent detection)
- Inertia React (frontend rendering)
- Wayfinder (route helpers)

**Why No New Composer Dependencies**:
- Aligns with Constitution constraint: "first-party Laravel packages only"
- Device detection via User-Agent parsing is simple enough (regex)
- QR generation is client-side (no server dependency)

---

## Performance Implications

| Operation | Expected Latency | Justification |
|-----------|------------------|---------------|
| Access pass link | <200ms | Simple DB lookup on indexed `slug` + User-Agent parsing |
| Generate QR code | <50ms | Client-side JavaScript, no server work |
| Create link | <100ms | Single INSERT with UUID generation |
| Disable/enable link | <50ms | Single UPDATE with enum status |

**Scaling**: No projected bottlenecks. Query optimizations (eager loading, indexes) sufficient for hundreds of thousands of links.

---

## Testing Strategy

**Unit Tests**:
- PassDistributionLinkService: slug generation, link enable/disable
- PassDistributionLink model: relationships, accessors

**Feature Tests** (PHPUnit):
- Create link via API
- Access link on iOS (verify Apple action shown)
- Access link on Android (verify Google action shown)
- Access link on Desktop (verify fallback shown)
- Disable link (verify 403 or disabled message)
- Re-enable link (verify link works again)
- Pass expiry (verify message shown, link accessible)
- QR code generation (verify data encoded correctly)

**Minimal Test Run Command**:
```bash
php artisan test tests/Feature/PassDistribution/ --filter="PassDistribution"
```

---

## Deployment Considerations

1. **Database Migration**: Create `pass_distribution_links` table with unique slug index
2. **Cache**: No caching needed (links are uncached for real-time access tracking)
3. **Job Queue**: No queued jobs (device detection and QR generation are fast)
4. **Environment Variables**: None (no external services)
5. **Rollback Plan**: Revert migration if needed; links cleaned up via cascade delete

---

## Conclusion

All unknowns resolved. Technical approach is clear, dependencies minimal, and implementation ready for Phase 1 design artifact generation.

**Next**: Proceed to Phase 1 to generate data-model.md, contracts/, and quickstart.md.
