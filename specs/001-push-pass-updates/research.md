# Research: Push Notifications & Real-Time Pass Updates

**Date**: February 20, 2026
**Purpose**: Technical research for implementing Apple Wallet push notifications, Apple Web Service Protocol, Google Wallet object updates, and per-user credential loading in this Laravel application.

---

## Topic 1: Apple APNS HTTP/2 for Wallet Push Notifications

### APNS Endpoint URLs

| Environment | URL |
|---|---|
| **Production** | `https://api.push.apple.com:443` |
| **Sandbox/Development** | `https://api.development.push.apple.com:443` |

The path for sending a push notification is:
```
POST /3/device/{deviceToken}
```

### Payload Format for Wallet Push Notifications

Apple Wallet push notifications use an **empty JSON payload** `{}`. The push notification itself carries no content — it simply signals Apple Wallet to contact the web service and download the latest version of the pass. The actual updated content is delivered when the device calls the "get latest pass" endpoint.

```json
{}
```

This is confirmed in the spec assumptions: "Apple APNS push notifications for Wallet are empty (zero-length payload) — the notification tells Apple Wallet to pull the latest pass from the server."

**Important**: The payload body must not be compressed. Maximum size is 4KB (4096 bytes), but for Wallet passes this is irrelevant since the payload is empty.

### Certificate-Based Authentication (.p12)

For certificate-based APNS authentication (as opposed to token-based/JWT), the TLS client certificate is used during the SSL handshake. The `.p12` file is the same certificate used for pass signing — no separate push certificate is required (confirmed in spec assumptions: same `apple_certificates` table certificate used for both signing and APNS).

**PHP curl implementation approach:**

1. Convert the `.p12` to a `.pem` file (combined certificate + private key) using OpenSSL:
   ```bash
   openssl pkcs12 -in certificate.p12 -out certificate.pem -nodes -passin pass:PASSWORD
   ```
   In PHP, this can be done programmatically via `openssl_pkcs12_read()` to extract the cert and key, then write to a temp `.pem` file.

2. Use PHP's native curl with HTTP/2:
   ```php
   $ch = curl_init();
   curl_setopt($ch, CURLOPT_URL, "https://api.push.apple.com/3/device/{$deviceToken}");
   curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_2_0);
   curl_setopt($ch, CURLOPT_SSLCERT, $pemFilePath);
   curl_setopt($ch, CURLOPT_SSLCERTPASSWD, $certPassword); // if PEM is encrypted
   curl_setopt($ch, CURLOPT_POST, true);
   curl_setopt($ch, CURLOPT_POSTFIELDS, '{}');
   curl_setopt($ch, CURLOPT_HTTPHEADER, [
       'apns-topic: pass.com.example.passTypeIdentifier',
       'apns-push-type: alert',
       'apns-priority: 5',
   ]);
   curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
   curl_setopt($ch, CURLOPT_HEADER, true);
   ```

3. **Alternative: Laravel HTTP client** — Laravel's `Http::` facade uses Guzzle under the hood. Guzzle supports HTTP/2 via curl, but you need to set the curl option directly:
   ```php
   Http::withOptions([
       'curl' => [
           CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2_0,
           CURLOPT_SSLCERT => $pemFilePath,
       ],
   ])->withHeaders([
       'apns-topic' => $passTypeIdentifier,
       'apns-push-type' => 'alert',
       'apns-priority' => '5',
   ])->post("https://api.push.apple.com/3/device/{$deviceToken}", new \stdClass);
   ```

### Required HTTP Headers

| Header | Value | Required? |
|---|---|---|
| `apns-topic` | The pass type identifier (e.g., `pass.com.example.loyalty`) | **YES** — Required for certificate-based auth if cert has multiple topics. Best practice to always include. |
| `apns-push-type` | `alert` | Recommended |
| `apns-priority` | `5` (low) or `10` (high) | Optional. `5` is appropriate for Wallet updates (not time-critical). |
| `apns-id` | UUID for the notification | Optional. Server returns one if omitted. |
| `apns-expiration` | UNIX timestamp (0 = immediate or no retry) | Optional. `0` means APNS won't store/retry. |
| `apns-collapse-id` | String (max 64 bytes) | Optional. Can deduplicate multiple updates to same pass. |

**Note**: For certificate-based auth, the `apns-topic` is extracted from the certificate's Subject if only one topic exists. Since pass certificates typically cover a single pass type identifier, it will default correctly — but explicit inclusion is safer.

### APNS Response Codes

| Status Code | Description | Action |
|---|---|---|
| **200** | Success | Mark delivery as sent |
| **400** | Bad request (malformed headers/payload) | Log error, do not retry (fix the request) |
| **403** | Certificate error or provider auth token error | Log error, may indicate expired/revoked certificate |
| **405** | Bad `:method` value (only POST supported) | Bug in implementation |
| **410** | **Device token no longer active for this topic** | **Mark `DeviceRegistration` as inactive, remove from future pushes** |
| **429** | Too many requests for same device token | Back off and retry |
| **500** | Internal server error (Apple's side) | Retry with backoff |
| **503** | Service unavailable / shutting down | Retry with backoff |

On failure, the response body is JSON:
```json
{
    "reason": "BadDeviceToken"
}
```

**Key error reasons:**
- `BadDeviceToken` — Invalid device token format
- `Unregistered` — Device token is no longer active (status 410)
- `ExpiredProviderToken` — Auth token expired (token-based only)
- `BadCertificate` / `BadCertificateEnvironment` — Certificate issues
- `TopicDisallowed` — Topic not allowed for this certificate
- `PayloadEmpty` — Payload is empty (note: for Wallet passes, `{}` is correct, not truly empty)

### Connection Pooling / Persistent Connections for Bulk Sending

**Critical performance consideration**: Apple recommends keeping the HTTP/2 connection open and reusing it. A single HTTP/2 connection can multiplex thousands of requests.

**PHP/curl approach for bulk sending:**

1. **Reuse the curl handle** — Do NOT create a new curl handle per push. Keep one handle and change only `CURLOPT_URL` (the device token part) between pushes.

2. **curl_multi for parallelism** — Use `curl_multi_init()` to send multiple pushes concurrently over multiplexed HTTP/2 streams on a single connection.

3. **In Laravel job context** — Since each `SendApplePushNotificationJob` is a separate queued job, connection reuse across jobs is not straightforward. Options:
   - **Option A (Recommended)**: Batch device tokens in the job. Instead of one job per device, dispatch one job per batch of N device tokens (e.g., 50) that share the same user/certificate. The job opens one connection and sends all N pushes.
   - **Option B**: Use a persistent connection pool managed by a service (e.g., store a `CurlHandle` in a static property on `ApplePushService`). Works within a single Horizon worker process but resets between worker restarts.
   - **Option C**: Accept one connection per job (simplest, but slowest for bulk). Acceptable for single-pass updates; less ideal for bulk.

4. **PHP curl HTTP/2 requirements**: 
   - curl 7.47+ with HTTP/2 support compiled in
   - PHP curl extension with `CURL_HTTP_VERSION_2_0` constant available
   - nghttp2 library (usually bundled with modern curl)
   - Verify with: `curl_version()['features'] & CURL_VERSION_HTTP2`

**Decision: Use Option A (batched jobs) for bulk updates.** For single-pass updates, one connection per job is acceptable.

**Rationale**: Batching amortizes the TLS handshake cost (~1-2s) across many pushes. At 50 pushes/sec rate limit, a batch of 50 tokens completes in ~1 second plus connection overhead. This aligns with the spec's rate limit of 50 pushes/sec/account.

---

## Topic 2: Apple Web Service Protocol for Wallet Passes

### The 5 Required Endpoints

Apple Wallet calls these endpoints automatically when a pass with `webServiceURL` and `authenticationToken` is added to, updated on, or removed from a device.

#### Endpoint 1: Register Device for Push Notifications

```
POST /v1/devices/{deviceLibraryIdentifier}/registrations/{passTypeIdentifier}/{serialNumber}
```

- **Authorization**: `Authorization: ApplePass {authenticationToken}`
- **Request Body**:
  ```json
  {
      "pushToken": "abc123def456..."
  }
  ```
- **Response Codes**:
  - `200 OK` — Registration already exists (and was updated)
  - `201 Created` — New registration created successfully
  - `401 Unauthorized` — Invalid authentication token
- **Implementation**: 
  - Validate `authenticationToken` matches the pass's stored token
  - Upsert into `device_registrations` table: `device_library_identifier`, `push_token`, `pass_type_identifier`, `serial_number`
  - The push token may change for the same device/pass combo — always update it

#### Endpoint 2: Unregister Device

```
DELETE /v1/devices/{deviceLibraryIdentifier}/registrations/{passTypeIdentifier}/{serialNumber}
```

- **Authorization**: `Authorization: ApplePass {authenticationToken}`
- **Request Body**: None
- **Response Codes**:
  - `200 OK` — Successfully unregistered
  - `401 Unauthorized` — Invalid authentication token
  - `404 Not Found` — Registration not found (not standard but reasonable)
- **Implementation**: Delete (or soft-delete / mark inactive) the device registration record

#### Endpoint 3: Get Serial Numbers of Updated Passes

```
GET /v1/devices/{deviceLibraryIdentifier}/registrations/{passTypeIdentifier}?passesUpdatedSince={tag}
```

- **Authorization**: None (no auth header for this endpoint per Apple's protocol)
- **Query Parameter**: `passesUpdatedSince` — A tag (string) previously returned by the server. Used to filter for passes updated after that point.
- **Response Codes**:
  - `200 OK` — There are updated passes
  - `204 No Content` — No updated passes since the given tag
- **Response Body** (200):
  ```json
  {
      "serialNumbers": ["serial1", "serial2"],
      "lastUpdated": "1677123456"
  }
  ```
  - `serialNumbers`: Array of serial numbers for passes that have been updated
  - `lastUpdated`: A tag (opaque string) the device will send in the next request as `passesUpdatedSince`
- **Implementation of `passesUpdatedSince`**: 
  - Use the pass's `updated_at` timestamp (as confirmed in spec assumptions)
  - Store it as a UNIX timestamp string
  - Query: Find all passes registered to this device where `updated_at > fromUnixTimestamp(passesUpdatedSince)`
  - If `passesUpdatedSince` is absent, return ALL serial numbers for that device/passType combo
  - The `lastUpdated` tag returned should be the maximum `updated_at` across the returned passes (as a UNIX timestamp string)

#### Endpoint 4: Get Latest Version of a Pass

```
GET /v1/passes/{passTypeIdentifier}/{serialNumber}
```

- **Authorization**: `Authorization: ApplePass {authenticationToken}`
- **Request Headers**:
  - `If-Modified-Since` — Standard HTTP header. If the pass hasn't been modified since this date, return 304.
- **Response Codes**:
  - `200 OK` — Returns the updated `.pkpass` file
  - `304 Not Modified` — Pass hasn't changed since `If-Modified-Since`
  - `401 Unauthorized` — Invalid authentication token
- **Response Headers** (200):
  - `Content-Type: application/vnd.apple.pkpass`
  - `Last-Modified: {RFC 2822 date}` — Set to the pass's `last_generated_at` or `updated_at`
- **Response Body** (200): The raw `.pkpass` binary data
- **Implementation**:
  - Parse `If-Modified-Since` header, compare with pass's `updated_at`
  - If not modified → 304 with no body
  - If modified → Regenerate the `.pkpass` (or serve the cached one if still current), return with proper headers
  - **Important**: The pass must be regenerated with the updated field values before serving

#### Endpoint 5: Log Errors from Device

```
POST /v1/log
```

- **Authorization**: None
- **Request Body**:
  ```json
  {
      "logs": [
          "Error message 1",
          "Error message 2"
      ]
  }
  ```
- **Response Codes**:
  - `200 OK` — Logs received
- **Implementation**: Store in application logs (e.g., `Log::warning()` channel). Useful for debugging pass issues on devices.

### URL Pattern Summary

All endpoints are relative to the `webServiceURL` configured in the pass. For this application, the base URL will be something like:

```
https://app.example.com/api/apple/v1
```

Full endpoint URLs:
| Method | URL Pattern |
|---|---|
| POST | `{webServiceURL}/v1/devices/{deviceLibraryIdentifier}/registrations/{passTypeIdentifier}/{serialNumber}` |
| DELETE | `{webServiceURL}/v1/devices/{deviceLibraryIdentifier}/registrations/{passTypeIdentifier}/{serialNumber}` |
| GET | `{webServiceURL}/v1/devices/{deviceLibraryIdentifier}/registrations/{passTypeIdentifier}` |
| GET | `{webServiceURL}/v1/passes/{passTypeIdentifier}/{serialNumber}` |
| POST | `{webServiceURL}/v1/log` |

**Note**: The `webServiceURL` in the pass.json should NOT include `/v1` — Apple Wallet appends the full path including `/v1/...`. So `webServiceURL` should be set to `https://app.example.com/api/apple` and the routes registered should match `/api/apple/v1/devices/...` etc.

### Authentication Model

- **Header format**: `Authorization: ApplePass {authenticationToken}`
- The `authenticationToken` is a unique random token generated per pass and included in the signed `pass.json` file
- Minimum 16 characters (Apple requirement)
- The token is validated on endpoints 1, 2, and 4. Endpoints 3 and 5 do NOT receive an auth header.
- Implementation: Look up the pass by `passTypeIdentifier` + `serialNumber`, then compare the provided token with the stored `authentication_token` field on the Pass model.

### `passesUpdatedSince` Tag

- An opaque string returned by the server in the "get serial numbers" response as `lastUpdated`
- The device stores this and sends it back on the next check as the `passesUpdatedSince` query parameter
- **Decision**: Use UNIX timestamp (seconds) of the most recent `updated_at` value
- **Rationale**: Simple, monotonically increasing, no separate versioning system needed (consistent with spec assumptions)
- On first call (no `passesUpdatedSince` param), return ALL serial numbers for the device/passType

### `If-Modified-Since` on Get Latest Pass

- Standard HTTP conditional request header
- Format: RFC 7231 date (e.g., `Wed, 21 Oct 2015 07:28:00 GMT`)
- Laravel's `Request::header('If-Modified-Since')` retrieves it
- Compare against pass's `last_generated_at` datetime
- If the pass has NOT been modified since that date → return `304 Not Modified` with no body
- If modified → return full `.pkpass` response with `Last-Modified` header set

### Response Format Requirements

| Endpoint | Content-Type | Notes |
|---|---|---|
| Register (POST) | None required | Just status code |
| Unregister (DELETE) | None required | Just status code |
| Serial numbers (GET) | `application/json` | JSON body with `serialNumbers` + `lastUpdated` |
| Get pass (GET) | `application/vnd.apple.pkpass` | Binary .pkpass file |
| Log (POST) | None required | Just status code |

---

## Topic 3: Google Wallet Object Update via REST API

### PATCH Endpoint URL Pattern

```
PATCH https://walletobjects.googleapis.com/walletobjects/v1/{objectType}/{resourceId}
```

Where:
- `{objectType}` maps to the pass type:
  - `loyaltyObject` — Loyalty cards
  - `offerObject` — Offers/coupons
  - `eventTicketObject` — Event tickets
  - `boardingPassObject` — Boarding passes (technically `flightObject`)  
  - `transitObject` — Transit passes
  - `genericObject` — Generic passes
- `{resourceId}` — Format: `{issuerId}.{objectSuffix}` (e.g., `3388000000012345.loyalty_pass_abc123`)

**Concrete examples:**
```
PATCH https://walletobjects.googleapis.com/walletobjects/v1/loyaltyObject/3388000000012345.loyalty_abc
PATCH https://walletobjects.googleapis.com/walletobjects/v1/genericObject/3388000000012345.generic_xyz
```

### Required Authentication

- **Service account JWT** → OAuth2 Bearer token
- Flow (already implemented in `GooglePassService::getAccessToken()`):
  1. Create JWT with `iss` = service account email, `scope` = `https://www.googleapis.com/auth/wallet_object.issuer`, `aud` = `https://oauth2.googleapis.com/token`
  2. Sign with RS256 using the service account's private key
  3. Exchange JWT for an access token at `https://oauth2.googleapis.com/token`
  4. Send access token as `Authorization: Bearer {token}` header

### Updatable Fields on an Existing Object

**PATCH supports partial updates** — only include the fields you want to change. Key updatable fields:

| Category | Fields |
|---|---|
| **Text fields** | `header`, `textModulesData[].body`, `textModulesData[].header` |
| **Loyalty-specific** | `loyaltyPoints.balance`, `secondaryLoyaltyPoints` |
| **Links** | `linksModuleData.uris[]` |
| **Barcode** | `barcode.value`, `barcode.alternateText` |
| **Images** | `heroImage`, `logo` (requires accessible URL) |
| **Info module** | `infoModuleData` |
| **Messages** | `messages[]` — Can add notification messages |
| **State** | `state` — `ACTIVE`, `COMPLETED`, `EXPIRED`, `INACTIVE` |
| **Validity** | `validTimeInterval` |

**Important caveats:**
- When PATCHing arrays (e.g., `textModulesData`, `messages`), the **entire array is replaced**, not merged. You must send the complete array.
- **Recommendation**: Do a GET first to retrieve current state, modify the fields, then PATCH. Already noted in Google's best practices.
- Class-level updates (`loyaltyClass`, `genericClass`) affect ALL objects of that class.

### How Google Handles Notifications on Object Update

- **Developer-authored push notifications are NOT supported** by Google Wallet.
- Google provides **automatic built-in notifications** for certain events:
  - Loyalty: No automatic update notification (field changes appear on next app open)
  - Event tickets: Upcoming reminder (3 hours before event)
  - Boarding passes: Upcoming reminder, pass update notification for gate/terminal/time changes
  - Offers: Expiry reminder (48 hours before expiry)
  - Generic: Upcoming reminder (24 hours before time interval start), expiry reminder

- **To trigger a user-visible notification on update**, you can:
  1. Add a `Message` to the object's `messages[]` array — this can appear as a notification
  2. Update the object's class-level `messages[]` — affects all objects of that class
  
- **Practical implication for this app**: Updating a Google Wallet object's fields (e.g., loyalty balance) will update the data, but the user may not see a notification. For critical updates, add a `Message` with `displayInterval` to trigger a notification:
  ```json
  {
      "messages": [{
          "header": "Points Updated",
          "body": "You now have 75 points!",
          "displayInterval": {
              "start": {"date": "2026-02-20T00:00:00Z"},
              "end": {"date": "2026-02-21T00:00:00Z"}
          }
      }]
  }
  ```

### Google's Rate Limits for Object Updates

| Limit | Value |
|---|---|
| **API rate limit** | **20 requests per second** (across all endpoints) |
| **Push messages per object** | **3 per day** per object (partner-triggered) |
| **Recommended timeout** | 10 seconds (99th percentile latency ~5 seconds) |

**Implications for bulk updates:**
- At 20 req/s global rate limit, 1,000 objects takes ~50 seconds
- Must implement rate limiting / throttling in the `UpdateGoogleWalletObjectJob`
- Google has no official SLA; latency depends on external factors (e.g., image hosting)

**Decision**: Rate-limit Google API calls to **15 requests per second** (leaving headroom below the 20/s limit) using Laravel's `RateLimiter`.

**Rationale**: Staying at 75% of the documented limit provides safety margin for retry requests and concurrent operations from other parts of the application.

---

## Topic 4: Per-User Credential Loading in Laravel

### Current State

Both `ApplePassService` and `GooglePassService` currently load credentials from global config in their constructors:

```php
// ApplePassService
public function __construct()
{
    $this->certificatePath = config('passkit.apple.certificate_path');
    $this->certificatePassword = config('passkit.apple.certificate_password');
    // ...
}

// GooglePassService
public function __construct()
{
    $serviceAccountPath = config('passkit.google.service_account_path');
    $this->serviceAccount = json_decode(file_get_contents($serviceAccountPath), true);
    // ...
}
```

These are registered as normal (non-singleton) classes, but the config values are global — they don't vary per user. In the multi-tenant SaaS context, each user has their own Apple certificate (stored in `apple_certificates` table) and Google credentials (stored in `google_credentials` table).

### Pattern Analysis

| Pattern | Description | Pros | Cons |
|---|---|---|---|
| **A. Credentials per-call** | Keep service as singleton-ish, pass credentials to each method | Simple, no state to manage | Every method signature grows; easy to forget passing credentials |
| **B. Factory method** | `ApplePassService::forUser(User $user)` returns a configured instance | Clean API; explicit lifecycle; testable | Slightly more objects; must avoid accidentally reusing instance for wrong user |
| **C. Constructor injection with credentials** | `new ApplePassService($certPath, $password, ...)` | Immutable after construction; clear dependency | Verbose construction; harder with Laravel DI |
| **D. `withCredentials()` fluent setter** | `$service->withCredentials($cert)->generate($pass)` | Familiar Laravel pattern (like `Http::withToken()`) | Mutable state; risk of stale credentials if reused |

### Decision: Pattern B — Static Factory Method (`forUser`)

```php
class ApplePassService
{
    public function __construct(
        protected string $certificatePath,
        protected string $certificatePassword,
        protected string $wwdrCertificatePath,
        protected string $teamIdentifier,
        protected string $passTypeIdentifier,
        protected string $organizationName,
        // ... storage config stays from global config
    ) {}

    public static function forUser(User $user): static
    {
        $cert = $user->appleCertificate; // Active AppleCertificate model
        if (!$cert || !$cert->isValid()) {
            throw new RuntimeException('No valid Apple certificate for user');
        }

        return new static(
            certificatePath: Storage::disk('local')->path($cert->path),
            certificatePassword: Crypt::decryptString($cert->password),
            wwdrCertificatePath: config('passkit.apple.wwdr_certificate_path'),
            teamIdentifier: config('passkit.apple.team_identifier'),
            passTypeIdentifier: $cert->pass_type_identifier ?? config('passkit.apple.pass_type_identifier'),
            organizationName: config('passkit.apple.organization_name'),
        );
    }

    // Backward-compatible factory from global config (for testing / fallback)
    public static function fromConfig(): static
    {
        return new static(
            certificatePath: config('passkit.apple.certificate_path'),
            // ... existing config loading
        );
    }
}
```

Similarly for `GooglePassService`:
```php
class GooglePassService
{
    public static function forUser(User $user): static
    {
        $cred = $user->googleCredential;
        if (!$cred) {
            throw new RuntimeException('No Google credentials for user');
        }

        return new static(
            serviceAccount: json_decode(Crypt::decryptString($cred->private_key), true),
            issuerId: $cred->issuer_id,
        );
    }
}
```

### Rationale

1. **Explicit lifecycle**: Each instance is scoped to one user's credentials. No risk of credential leakage between users.
2. **Immutable**: Once constructed, the service operates with a fixed set of credentials. No mutable state.
3. **Testable**: Easy to construct with test credentials in unit tests. Factory method can be mocked.
4. **Laravel DI compatible**: Jobs can resolve credentials at handle-time:
   ```php
   public function handle(): void
   {
       $service = ApplePassService::forUser($this->pass->user);
       $service->generate($this->pass);
   }
   ```
5. **No singleton confusion**: The service is NOT a singleton — a new instance per user per request. This is correct because each user's certificates are different.
6. **Backward compatible**: The `fromConfig()` factory preserves the old behavior for existing tests and any code not yet migrated.

### Alternatives Considered

1. **Credentials per-call (Option A)**: Rejected because it pollutes every method signature (`generate(Pass $pass, string $certPath, string $certPassword, ...)`) and is error-prone—callers must correctly assemble credentials every time.

2. **Fluent setter (Option D)**: Rejected due to mutable state risk. In queued job context, if a service instance were accidentally shared, one user's credentials could leak to another user's job. Immutable construction via factory eliminates this class of bugs.

3. **Service class per user type (composition)**: Rejected as over-engineered. The certificate/credential pair is the only thing that varies; the business logic is identical.

4. **Global config override via `config()->set()`**: Rejected — this is not thread-safe, would break with Octane/Swoole, and is fundamentally the wrong abstraction for per-user data.

### Migration Strategy

1. Refactor `ApplePassService` constructor to accept parameters (not read from config)
2. Add `static forUser(User $user)` and `static fromConfig()` factory methods
3. Update `GeneratePassJob` to use `ApplePassService::forUser($this->pass->user)` instead of DI
4. Update `PassDownloadController` similarly
5. Same pattern for `GooglePassService`
6. New `ApplePushService` should follow the same pattern from the start (accept credentials in constructor, provide `forUser()` factory)
7. Update all existing tests — swap DI-based service resolution for factory-based construction

### New `ApplePushService` Credential Needs

The new `ApplePushService` will need:
- Certificate `.pem` content (converted from the user's `.p12`)
- Certificate password
- Pass type identifier (used as `apns-topic`)
- Environment flag (sandbox vs production)

This naturally fits the same `forUser()` pattern:
```php
class ApplePushService
{
    public static function forUser(User $user): static
    {
        $cert = $user->appleCertificate;
        return new static(
            pemContent: self::convertP12ToPem($cert->path, $cert->password),
            passTypeIdentifier: $cert->pass_type_identifier,
            environment: config('passkit.apple.apns_environment', 'production'),
        );
    }
}
```

---

## Summary of Key Decisions

| # | Decision | Rationale |
|---|---|---|
| 1 | Use PHP native curl with `CURL_HTTP_VERSION_2_0` for APNS | No external dependency; Laravel HTTP client can wrap it. HTTP/2 required by Apple. |
| 2 | Certificate-based APNS auth (not token/JWT) | Same `.p12` certificate already stored for pass signing. Simpler — one credential serves both purposes. |
| 3 | Batch device tokens per job for bulk push | Amortizes TLS handshake cost; meets 50 push/sec rate limit efficiently. |
| 4 | Use UNIX timestamp as `passesUpdatedSince` tag | Simple, monotonically increasing. Uses existing `updated_at` column — no extra versioning. |
| 5 | Rate-limit Google API calls to 15 req/s | 75% of documented 20/s limit; leaves headroom for retries and concurrent operations. |
| 6 | Static factory `forUser()` pattern for per-user credentials | Immutable instances, explicit lifecycle, no credential leakage, backward compatible, DI-friendly for jobs. |
| 7 | Empty payload `{}` for Apple Wallet push | Apple specification for Wallet passes — the push only signals "come fetch the pass", no content. |
| 8 | `webServiceURL` does NOT include `/v1` | Apple Wallet appends the full path. Base URL should be e.g., `https://app.example.com/api/apple`. |
