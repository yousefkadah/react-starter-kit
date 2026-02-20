# Quickstart: Push Notifications & Real-Time Pass Updates

**Branch**: `001-push-pass-updates`

---

## Prerequisites

Before starting implementation, ensure:

1. **PHP 8.3** with curl HTTP/2 support (`curl_version()['features'] & CURL_VERSION_HTTP2`)
2. **PostgreSQL** running with existing migrations applied
3. **Redis** running (Horizon queue)
4. **Apple certificate** uploaded via Account Setup (002 spec) — stored in `apple_certificates` table
5. **Google credential** uploaded via Account Setup — stored in `google_credentials` table

## Development Setup

```bash
# 1. Switch to feature branch
git checkout 001-push-pass-updates

# 2. Run new migrations
php artisan migrate

# 3. Build frontend assets
npm run dev

# 4. Start Horizon (queue workers)
php artisan horizon

# 5. Run feature tests
php artisan test --filter=PushNotification --compact
```

## Implementation Order (Recommended)

### Phase 1: Foundation (P1 stories)

1. **Migration: `authentication_token` on passes** — Add column + backfill existing passes  
2. **Migration: `device_registrations` table** — Create table for Apple device tracking
3. **Migration: `pass_updates` table** — Audit log for field changes
4. **Migration: `bulk_updates` table** — Bulk operation tracking
5. **Refactor `ApplePassService`** — Add `forUser()` factory, inject `webServiceURL` + `authenticationToken` into pass.json
6. **Refactor `GooglePassService`** — Add `forUser()` factory, add `patchObject()` method
7. **`AppleWebServiceController`** — Implement all 5 Apple Web Service Protocol endpoints
8. **`ApplePushService`** — New service: APNS HTTP/2 client using per-user certificates
9. **`PassUpdateService`** — Orchestrator: update fields → regenerate pass → dispatch push jobs
10. **`ProcessPassUpdateJob`** — Queue job for single pass update + push dispatch
11. **`SendApplePushNotificationJob`** — Queue job for individual APNS push
12. **`UpdateGoogleWalletObjectJob`** — Queue job for Google REST API PATCH

### Phase 2: Enhanced Features (P2 stories)

13. **Change message templates** — Per-field `changeMessage` support in pass.json generation
14. **`BulkPassUpdateJob`** — Orchestrates bulk updates with chunking + rate limiting
15. **`BulkUpdate` model + API** — Dashboard progress tracking, mutex enforcement
16. **HMAC signature middleware** — `VerifyHmacSignature` for server-to-server API auth
17. **API `PassUpdateController`** — PATCH endpoint with Sanctum + HMAC dual auth
18. **Dashboard UI** — Pass update panel, delivery status, bulk update progress

### Phase 3: Refinement (P3 + operational)

19. **Pull-to-refresh** — Mostly covered by Web Service Protocol endpoints; add `If-Modified-Since` / `304` handling
20. **`PrunePassUpdateHistoryJob`** — Scheduled 90-day cleanup
21. **Horizon queue config** — Add dedicated `push-notifications` queue
22. **Retry + exponential backoff** — Configure on push notification jobs

## Key Configuration

Add to `config/passkit.php`:

```php
'push' => [
    'apns_environment' => env('APNS_ENVIRONMENT', 'production'), // 'production' or 'sandbox'
    'rate_limit_per_second' => 50,
    'max_retries' => 3,
    'retry_backoff' => [30, 120, 600], // seconds
],

'web_service' => [
    'base_url' => env('PASSKIT_WEB_SERVICE_URL', ''), // e.g., https://app.example.com/api/apple
],
```

## Minimal Test Run

```bash
# Run all push notification tests
php artisan test --filter=PushNotification --compact

# Run specific test groups
php artisan test --filter=AppleWebServiceProtocol --compact
php artisan test --filter=PassUpdateApi --compact
php artisan test --filter=BulkPassUpdate --compact
```

## Key Technical Decisions

| Decision | Details |
|----------|---------|
| **APNS auth** | Certificate-based (same `.p12` as pass signing), not token-based JWT |
| **APNS payload** | Empty `{}` — signals Wallet to pull latest pass from server |
| **Credential loading** | Static factory `ApplePassService::forUser($user)` — immutable, per-user instances |
| **Rate limiting** | 50 APNS pushes/sec/account; 15 Google API calls/sec (75% of limit) |
| **Modification tag** | `updated_at` UNIX timestamp — no separate versioning |
| **webServiceURL** | Does NOT include `/v1` — Apple appends the path |
| **Audit retention** | 90 days, auto-pruned daily |
| **Bulk mutex** | Per-template lock — no concurrent bulk updates on same template |
