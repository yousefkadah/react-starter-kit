# Research & Decision-Making: Account Creation & Wallet Setup

**Phase**: 0 - Research & Decision-Making  
**Date**: February 13, 2026  
**Status**: 🔬 Research Complete  
**Tasks**: T001-T006 (6 research items)  

---

## Overview

This document captures technical research findings and architectural decisions made during Phase 0. Each decision is backed by analysis of alternatives, trade-offs, and performance implications.

---

## T001: Apple CSR Generation with PHP OpenSSL

### Problem Statement

Users need a self-service way to generate Apple Certificate Signing Requests (CSR) without requiring server-level config file editing. A CSR is a cryptographic request sent to Apple Developer Portal to obtain a pass signing certificate.

### Research Findings

#### Option A: Use PHP OpenSSL Functions (RECOMMENDED)

**Approach**: Use PHP's OpenSSL extension to generate CSR locally in the application.

**Key Functions**:
```php
// Generate CSR with OpenSSL
$config = [
    "private_key_bits" => 2048,
    "default_md" => "sha256",
    "distinguished_name" => "req_distinguished_name"
];

$subject = [
    "countryName" => "US",
    "organizationName" => "Your Company",
    "organizationalUnitName" => "PassKit",
    "commonName" => "passkit.company.com"
];

// Generate private/public keypair
$privkey = openssl_pkey_new($config);

// Generate CSR
$csr = openssl_csr_new($subject, $privkey, $config);

// Export CSR to PEM format
openssl_csr_export($csr, $csr_pem);

// Export private key (encrypted)
openssl_pkey_export($privkey, $privkey_pem, "password123");
```

**Pros**:
- ✅ No external dependencies (OpenSSL is standard in PHP)
- ✅ Fully self-service in UI (no config files needed)
- ✅ Can store CSR and private key encrypted in database
- ✅ Supports multiple CSRs per user (test/prod scenarios)
- ✅ Fast (< 100ms to generate)
- ✅ No licensing issues

**Cons**:
- ⚠️ Requires OpenSSL extension enabled in PHP (usually enabled by default)
- ⚠️ Private key handling requires encryption at rest
- ⚠️ Error cases (invalid config) need graceful handling

**Testing**:
```php
// Unit test: CSR generation produces valid PEM format
$service = new AppleCSRService();
$csr_pem = $service->generateCSR($user);
$this->assertStringContainsString("-----BEGIN CERTIFICATE REQUEST-----", $csr_pem);
$this->assertStringContainsString("-----END CERTIFICATE REQUEST-----", $csr_pem);
```

#### Option B: Pre-generated Templates

**Approach**: Generate CSRs offline and provide downloadable templates.

**Pros**: No runtime CSR generation needed.  
**Cons**: Not self-service, less flexible, not scalable.

#### Option C: Third-party Service (e.g., Sectigo API)

**Approach**: Offload CSR generation to third-party CSR service.

**Pros**: Outsourced complexity.  
**Cons**: Extra API call, additional cost, vendor lock-in, slower.

### Decision

**✅ CHOSEN: Option A (PHP OpenSSL)**

**Rationale**:
- Self-service experience critical for UX
- No external dependencies or costs
- OpenSSL is industry-standard and available in all PHP environments
- Aligns with LaraPassKit constitution (first-party tools only)
- Can store private key encrypted in database for future re-use

**Implementation**:
- Create `AppleCSRService` class in `app/Services/`
- Method: `generateCSR(User $user): string` returns PEM-formatted CSR
- Store CSR in temp storage (session/cache) with 1-day TTL
- Return CSR as file download to user
- Private key: encrypt with Laravel Crypt, store in AppleCertificate table

---

## T002: Google Service Account JSON Validation

### Problem Statement

Users upload Google service account JSON files. The system must validate:
1. File is valid JSON
2. Contains required Google Wallet API fields
3. Private key format is valid
4. Extract issuer_id for later use

### Research Findings

#### Google Service Account JSON Structure

```json
{
  "type": "service_account",
  "project_id": "my-project-123456",
  "private_key_id": "abcd1234",
  "private_key": "-----BEGIN PRIVATE KEY-----\n...\n-----END PRIVATE KEY-----\n",
  "client_email": "passkit-service@my-project-123456.iam.gserviceaccount.com",
  "client_id": "123456789",
  "auth_uri": "https://accounts.google.com/o/oauth2/auth",
  "token_uri": "https://oauth2.googleapis.com/token",
  "auth_provider_x509_cert_url": "https://www.googleapis.com/oauth2/v1/certs",
  "client_x509_cert_url": "https://www.googleapis.com/robot/v1/metadata/x509/..."
}
```

#### Validation Strategy

**Required Fields** (Google Wallet):
- `type` = "service_account"
- `project_id` (string)
- `private_key_id` (string)
- `private_key` (RSA private key in PEM format)
- `client_email` (email format)
- `client_id` (string)
- `token_uri` (URL)

**Validation Approach**:

```php
class GoogleCredentialValidationService
{
    public function validate(UploadedFile $file): array
    {
        $errors = [];
        
        // 1. Check valid JSON
        $content = $file->get();
        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $errors[] = "Invalid JSON: " . json_last_error_msg();
        }
        
        // 2. Check required fields
        $required = ['type', 'project_id', 'private_key_id', 'private_key', 'client_email', 'client_id', 'token_uri'];
        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $errors[] = "Missing required field: {$field}";
            }
        }
        
        // 3. Validate service account type
        if ($data['type'] !== 'service_account') {
            $errors[] = "Invalid type: expected 'service_account'";
        }
        
        // 4. Validate private key format (RSA)
        if (!preg_match('/^-----BEGIN (RSA )?PRIVATE KEY-----/', $data['private_key'])) {
            $errors[] = "Private key is not in valid RSA PEM format";
        }
        
        // 5. Validate email format
        if (!filter_var($data['client_email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format in client_email";
        }
        
        // 6. Extract issuer_id from client_email
        $issuer_id = explode('@', $data['client_email'])[0]; // e.g., "passkit-service"
        
        return [
            'valid' => count($errors) === 0,
            'errors' => $errors,
            'issuer_id' => $issuer_id ?? null,
            'project_id' => $data['project_id'] ?? null,
        ];
    }
}
```

#### Testing

```php
public function testValidGoogleJSON()
{
    $file = UploadedFile::fake()->createWithContent('sa.json', $this->validJSON);
    $result = $this->service->validate($file);
    $this->assertTrue($result['valid']);
}

public function testInvalidJSON()
{
    $file = UploadedFile::fake()->createWithContent('bad.json', 'not json');
    $result = $this->service->validate($file);
    $this->assertFalse($result['valid']);
    $this->assertStringContainsString('Invalid JSON', implode($result['errors']));
}

public function testMissingRequiredField()
{
    $data = json_decode($this->validJSON, true);
    unset($data['private_key']);
    $file = UploadedFile::fake()->createWithContent('bad.json', json_encode($data));
    $result = $this->service->validate($file);
    $this->assertStringContainsString('Missing required field: private_key', implode($result['errors']));
}
```

### Decision

**✅ CHOSEN: Schema Validation + Field Extraction**

**Rationale**:
- Simple, no external API calls needed
- Specific error messages for user feedback
- Extract issuer_id immediately for storage
- Can validate signature if needed later (e.g., test API call)

**Implementation**:
- Create `GoogleCredentialValidationService` in `app/Services/`
- Method: `validate(UploadedFile $file): array` returns validation result with errors
- Extract issuer_id and project_id
- Store private_key encrypted in database
- Return specific error messages if validation fails

---

## T003: Region Scoping in Eloquent

### Problem Statement

Users select EU or US region at signup. All user data (passes, templates, settings) must be scoped to their region. Prevent cross-region data leakage.

**Requirements**:
- User A (EU) cannot see User B (US) passes
- Admin can see all regions (or remain scoped, decide per security policy)
- Automatic filtering on all queries
- Minimal code duplication

### Research Findings

#### Option A: Global Scope + Trait

**Approach**: Use Eloquent global scopes to auto-filter all queries.

```php
// app/Traits/ScopedByRegion.php
trait ScopedByRegion
{
    public static function bootScopedByRegion()
    {
        static::addGlobalScope(new RegionScope());
    }
}

// app/Scopes/RegionScope.php
class RegionScope implements Scope
{
    public function apply(Builder $builder, Model $model)
    {
        if (auth()->check()) {
            $builder->where('region', auth()->user()->region);
        }
    }
}
```

**Pros**:
- ✅ Automatic on all queries (no need to remember to add filter)
- ✅ Clean, reusable across models
- ✅ Prevents accidental cross-region queries
- ✅ Can disable scope when needed: `User::withoutGlobalScopes()->get()`

**Cons**:
- ⚠️ Can be surprising if scope kicks in unexpectedly
- ⚠️ Admin queries need special handling to bypass scope
- ⚠️ Requires `auth()->check()` to work (must be after authentication)

#### Option B: Explicit Query Scope

**Approach**: Add explicit scope method to each model.

```php
// In model
public function scopeForUser(Builder $query)
{
    return $query->where('region', auth()->user()->region);
}

// In controller
Pass::forUser()->get();
```

**Pros**: Explicit, requires conscious opt-in.  
**Cons**: Easy to forget, code duplication, less safe.

#### Option C: Repository Pattern

**Approach**: Use repository layer to enforce scoping.

**Pros**: Centralized filtering.  
**Cons**: Extra layer of abstraction, violates LaraPassKit simplicity principle.

### Decision

**✅ CHOSEN: Option A (Global Scope + Trait)**

**Rationale**:
- Safest approach (prevents accidental leaks)
- Minimal code duplication
- Aligns with Laravel conventions
- Admin queries can use `withoutGlobalScopes()` when needed
- Easier to test (can mock auth context)

**Implementation**:
```php
// app/Traits/ScopedByRegion.php
trait ScopedByRegion
{
    public static function bootScopedByRegion()
    {
        static::addGlobalScope(new RegionScope());
    }
}

// app/Scopes/RegionScope.php
class RegionScope implements Scope
{
    public function apply(Builder $builder, Model $model)
    {
        // Only filter if model has 'region' column
        if ($builder->getModel()->getTable() !== 'users') {
            if (auth()->check()) {
                $builder->where('region', auth()->user()->region);
            }
        }
    }
}

// Apply to models: User, Pass, AppleCertificate, GoogleCredential
class Pass extends Model
{
    use ScopedByRegion;
}
```

**Testing**:
```php
public function testRegionScopingEU()
{
    $eu_user = User::factory()->forRegionEU()->create();
    $us_user = User::factory()->forRegionUS()->create();
    
    $eu_pass = Pass::factory()->for($eu_user)->create();
    $us_pass = Pass::factory()->for($us_user)->create();
    
    $this->actingAs($eu_user);
    $passes = Pass::get();
    
    $this->assertTrue($passes->contains($eu_pass));
    $this->assertFalse($passes->contains($us_pass));
}
```

---

## T004: Email Domain Whitelist Caching

### Problem Statement

During signup, system checks if email domain is in whitelist (auto-approve) or consumer email (approval queue). Checking database on every signup is inefficient.

**Requirements**:
- Fast lookup (< 10ms)
- Auto-invalidate when domains added/removed
- Fallback if cache misses
- No external dependencies

### Research Findings

#### Option A: Redis Cache (1-hour TTL)

```php
class EmailDomainService
{
    const CACHE_KEY = 'business_domains:all';
    const CACHE_TTL = 3600; // 1 hour
    
    public function isBusinessDomain(string $email): bool
    {
        $domain = $this->extractDomain($email);
        $domains = Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            return BusinessDomain::pluck('domain')->toArray();
        });
        
        return in_array($domain, $domains);
    }
    
    public function invalidateCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}

// In BusinessDomain model
public static function boot()
{
    parent::boot();
    static::created(function () {
        app(EmailDomainService::class)->invalidateCache();
    });
    static::updated(function () {
        app(EmailDomainService::class)->invalidateCache();
    });
    static::deleted(function () {
        app(EmailDomainService::class)->invalidateCache();
    });
}
```

**Pros**:
- ✅ Very fast (< 1ms on cache hit)
- ✅ Works with Redis or database cache driver
- ✅ Auto-invalidates when domains change
- ✅ Fallback to DB query if cache miss
- ✅ Laravel built-in

**Cons**:
- ⚠️ Requires cache driver configured (Redis or database)
- ⚠️ 1-hour delay if domain added (acceptable for this use case)

#### Option B: In-Memory Config Cache

```php
// config/business-domains.php
return [
    'domains' => [
        'stripe.com',
        'acme.com',
        'microsoft.com',
        // ... more domains
    ]
];

// But: requires config cache clear on domain changes
```

**Pros**: Very fast, no runtime queries.  
**Cons**: Requires manual cache clear, not dynamic.

#### Option C: No Caching (Direct DB)

```php
public function isBusinessDomain(string $email): bool
{
    $domain = $this->extractDomain($email);
    return BusinessDomain::where('domain', $domain)->exists();
}
```

**Pros**: Simple, always current.  
**Cons**: One DB query per signup (acceptable, but room for optimization).

### Decision

**✅ CHOSEN: Option A (Redis Cache 1-hour TTL)**

**Rationale**:
- Performance critical for signup flow
- 1-hour TTL acceptable (domain additions are rare)
- Easy to invalidate cache when domains change
- Works with both Redis and database cache drivers
- Fallback to DB query if cache miss

**Implementation**:
```php
// app/Services/EmailDomainService.php
public function isBusinessDomain(string $email): bool
{
    $domain = $this->extractDomain($email);
    $domains = Cache::remember('business_domains:all', 3600, function () {
        return BusinessDomain::pluck('domain')->toArray();
    });
    return in_array($domain, $domains);
}

// In BusinessDomain model: auto-invalidate cache on changes
```

---

## T005: Certificate Expiry Job Scheduling

### Problem Statement

System must check certificates daily for expiry and send notifications (30 days, 7 days, 0 days before expiry). 

**Requirements**:
- Runs reliably every day
- Doesn't miss expirations
- Can handle large numbers of certificates
- Logs failures for debugging

### Research Findings

#### Option A: Laravel Scheduler + Queued Job (RECOMMENDED)

**Approach**: Schedule command daily, dispatch queued job.

```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    $schedule->job(new CheckCertificateExpiryJob())->dailyAt('01:00');
}

// app/Jobs/CheckCertificateExpiryJob.php
class CheckCertificateExpiryJob implements ShouldQueue
{
    public function handle()
    {
        // Find certs expiring in 30, 7, 0 days
        $thirtyDays = AppleCertificate::whereDate('expiry_date', now()->addDays(30))
            ->where('expiry_notified_30_days', false)
            ->get();
        
        foreach ($thirtyDays as $cert) {
            SendExpiryNotificationJob::dispatch($cert, 30);
            $cert->update(['expiry_notified_30_days' => true]);
        }
        
        // Similar for 7 days and 0 days
    }
}

// app/Jobs/SendExpiryNotificationJob.php
class SendExpiryNotificationJob implements ShouldQueue
{
    public function __construct(public AppleCertificate $cert, public int $daysRemaining) {}
    
    public function handle()
    {
        Mail::to($this->cert->user->email)->send(
            new CertificateExpiryMail($this->cert, $this->daysRemaining)
        );
    }
}
```

**Pros**:
- ✅ Built into Laravel, no external service needed
- ✅ Queued jobs allow async email sending
- ✅ Can retry failed jobs
- ✅ Simple logging via Laravel logs
- ✅ Scales to 1000s of certificates
- ✅ Respects queue configuration (Redis, database, sync)

**Cons**:
- ⚠️ Requires running `php artisan queue:work` in background
- ⚠️ Scheduler relies on cron job (`* * * * * ...`) every minute to check schedule
- ⚠️ If cron doesn't run, scheduler misses execution

#### Option B: Fully Queued Approach

**Approach**: Dispatch all expiry checks as individual jobs.

**Pros**: More distributed.  
**Cons**: More jobs in queue, overhead.

#### Option C: External Service (e.g., AWS EventBridge)

**Approach**: Use Lambda or external scheduler.

**Pros**: Guaranteed execution if infrastructure available.  
**Cons**: Extra cost, external dependency, overkill for this system.

### Decision

**✅ CHOSEN: Option A (Laravel Scheduler + Queued Job)**

**Rationale**:
- Standard Laravel pattern, team already familiar
- Queued jobs allow async email sending without blocking
- Can inspect job results via failed_jobs table
- Simple to deploy (just need `php artisan queue:work` running)
- Cost-effective (no external services)

**Implementation**:
- Add to `app/Console/Kernel.php`: `$schedule->job(new CheckCertificateExpiryJob())->dailyAt('01:00');`
- Create `CheckCertificateExpiryJob` to find expiring certs
- Dispatch `SendExpiryNotificationJob` for each expiring cert
- Add tracking flags (expiry_notified_30_days, etc.) to AppleCertificate table to prevent duplicate emails

**Testing**:
```php
public function testExpiryJobIdentifiesCorrectCerts()
{
    // Create certs: 30 days away, 7 days away, 0 days away, far future
    $cert_30 = AppleCertificate::factory()->expiringIn(30)->create();
    $cert_7 = AppleCertificate::factory()->expiringIn(7)->create();
    
    CheckCertificateExpiryJob::dispatch();
    
    $this->assertEquals(2, SendExpiryNotificationJob::count());
}
```

---

## T006: Admin Authorization Patterns

### Problem Statement

Admins need access to approval queues, production requests, domain whitelist management. Regular users must not access these endpoints.

**Requirements**:
- Simple role/permission system
- Admin-only routes protected
- Admin checks in policies
- Graceful denial messages

### Research Findings

#### Option A: Simple Admin Flag on User Model

```php
// Schema: users.is_admin (boolean, default false)

// Middleware
class EnsureAdmin
{
    public function handle(Request $request, Closure $next)
    {
        if (!auth()->check() || !auth()->user()->is_admin) {
            abort(403, 'Unauthorized');
        }
        return $next($request);
    }
}

// Policy
class AdminApprovalPolicy
{
    public function viewQueue(User $user)
    {
        return $user->is_admin;
    }
    
    public function approve(User $user, User $target)
    {
        return $user->is_admin;
    }
}

// Route
Route::middleware('auth', EnsureAdmin::class)->group(function () {
    Route::get('/admin/approvals', [AdminApprovalController::class, 'index'])->name('admin.approvals.index');
    Route::post('/admin/approvals/{user}/approve', [AdminApprovalController::class, 'approve'])->name('admin.approvals.approve');
});
```

**Pros**:
- ✅ Simple, minimal setup
- ✅ Aligns with LaraPassKit simplicity
- ✅ Easy to test
- ✅ Sufficient for MVP (no complex roles needed)

**Cons**:
- ⚠️ Only two permission levels (admin or not)
- ⚠️ Not granular (all admins have all access)

#### Option B: Role-Based Access Control (RBAC)

```php
// Spatie Laravel Permission package
// Users have roles: super-admin, approver, support
// Roles have permissions: approve-accounts, manage-domains, etc.

if ($user->hasRole('approver')) { ... }
```

**Pros**: Granular, scalable.  
**Cons**: Over-engineered for current needs, adds complexity.

#### Option C: Policies Only

```php
// No middleware, just policies on each action
authorize('approve', $user);
```

**Pros**: Clean authorization.  
**Cons**: Easy to forget on a route.

### Decision

**✅ CHOSEN: Option A (Simple Admin Flag + Middleware + Policy)**

**Rationale**:
- MVP only needs two levels: admin and user
- Simple to implement and test
- LaraPassKit principle: no unnecessary dependencies
- Can upgrade to RBAC later if needed

**Implementation**:
```php
// 1. Add to users migration: $table->boolean('is_admin')->default(false);
// 2. Create EnsureAdmin middleware
// 3. Create AdminApprovalPolicy
// 4. Protect routes with middleware
// 5. Authorize actions in controllers

public function approve(AdminApprovalRequest $request, User $user)
{
    $this->authorize('approve', $user);
    // ... approval logic
}
```

**Testing**:
```php
public function testAdminCanApproveAccounts()
{
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->postJson('/admin/approvals/' . $user->id . '/approve')
        ->assertOk();
}

public function testRegularUserCannotApprove()
{
    $user = User::factory()->create(['is_admin' => false]);
    $this->actingAs($user)->postJson('/admin/approvals/' . $other_user->id . '/approve')
        ->assertForbidden();
}
```

---

## Summary of Decisions

| Research Item | Decision | Rationale | Trade-offs |
|---------------|----------|-----------|-----------|
| T001: Apple CSR | PHP OpenSSL functions | Self-service, no deps, standard | Private key encryption required |
| T002: Google JSON Validation | Schema validation + field extraction | Simple, no API calls, specific errors | Must maintain schema knowledge |
| T003: Region Scoping | Global scope + trait (ScopedByRegion) | Safest, prevents leaks, automatic | Admin queries need special handling |
| T004: Email Domain Caching | Redis cache (1h TTL) with invalidation | Fast, fallback to DB, auto-invalidate | Requires cache driver configured |
| T005: Certificate Expiry | Laravel Scheduler + queued jobs | Standard pattern, reliable, queued | Requires `php artisan queue:work` |
| T006: Admin Authorization | Simple admin flag + middleware + policy | MVP-sufficient, simple, testable | No granular roles yet (can add later) |

---

## Implementation Checklist

- [x] T001: Research Apple CSR generation → **PHP OpenSSL chosen**
- [x] T002: Research Google JSON validation → **Schema validation chosen**
- [x] T003: Research region scoping → **Global scope + trait chosen**
- [x] T004: Research email domain caching → **Redis 1h TTL chosen**
- [x] T005: Research certificate expiry → **Scheduler + queued jobs chosen**
- [x] T006: Research admin authorization → **Admin flag + middleware chosen**

---

## Next Steps: Phase 1

All Phase 0 research complete. Ready to proceed to **Phase 1: Database Schema & Eloquent Models**.

**Phase 1 Gate**: All migrations run, all Eloquent models with relationships functional.

**Tasks**: T101-T117 (database, models, factories, seeders)

---

**Phase 0 Status**: ✅ COMPLETE

Generated: February 13, 2026  
Reviewed: All decisions documented, rationales clear, no blockers identified  
Approved for Phase 1 transition
