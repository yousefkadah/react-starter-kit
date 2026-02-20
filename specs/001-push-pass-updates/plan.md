# Implementation Plan: Push Notifications & Real-Time Pass Updates

**Branch**: `001-push-pass-updates` | **Date**: February 20, 2026 | **Spec**: [spec.md](spec.md)
**Input**: Feature specification from `/specs/001-push-pass-updates/spec.md`

## Summary

Implement push notifications and real-time pass updates for Apple Wallet and Google Wallet. This includes Apple's Web Service Protocol (5 endpoints for device registration, pass serving, and update signaling), APNS HTTP/2 push notifications, Google Wallet REST API PATCH updates, per-field change messages for lock screen notifications, bulk pass updates with progress tracking, pull-to-refresh support, and an API endpoint for external integrations. The existing `ApplePassService` and `GooglePassService` will be refactored to accept per-user credentials, and a new dedicated push notification queue will be added to Horizon.

## Technical Context

**Language/Version**: PHP 8.3.30, TypeScript (React 19)
**Primary Dependencies**: Laravel 12, Inertia v2, Horizon 5, Sanctum, Fortify, Cashier v16
**Storage**: PostgreSQL (Eloquent ORM), local disk for `.pkpass` files and certificates
**Testing**: PHPUnit 11 via `php artisan test --compact`
**Target Platform**: Linux server (web application), Apple Wallet + Google Wallet (client platforms)
**Project Type**: Web application (Laravel backend + Inertia/React frontend)
**Performance Goals**: Single pass update → device in <30s (Apple) / <60s (Google); API response <500ms; bulk 1K passes in <5min
**Constraints**: 50 APNS pushes/sec/account; 3 Google push messages/object/day; 10KB pass content limit; 90-day audit log retention
**Scale/Scope**: Multi-tenant SaaS; plan limits from 25 to unlimited passes/user; bulk updates up to 10K+ passes per template

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

- **I. Laravel-First Architecture**: ✅ PASS — New `DeviceRegistration`, `PassUpdate`, `BulkUpdateJob` Eloquent models. `UpdatePassRequest` Form Request for validation. `PassUpdatePolicy` for authorization. Inertia renders for dashboard UI. No new base folders — new files go in existing `app/Models/`, `app/Services/`, `app/Jobs/`, `app/Http/Controllers/`, `resources/js/pages/passes/`. No new dependencies — APNS HTTP/2 uses PHP's built-in curl with HTTP/2 support (Laravel HTTP client); Google API uses existing service account JWT auth already in `GooglePassService`.
- **II. Type-Safe Routing & Inertia Navigation**: ✅ PASS — All frontend routes via Wayfinder. New pages under `resources/js/pages/passes/`. Apple Web Service Protocol endpoints are external-facing API routes (no Inertia), registered in `routes/passes.php`.
- **III. Test-Backed Changes**: ✅ PASS — Feature tests for: Apple Web Service Protocol (5 endpoints), pass update API (PATCH), bulk update job processing, APNS push sending (mocked HTTP), Google PATCH (mocked HTTP), device registration lifecycle, auth token validation, HMAC signature verification. Minimal test run: `php artisan test --filter=PushNotification --compact`.
- **IV. Security & Tenant Isolation**: ✅ PASS — Apple Web Service Protocol uses per-pass `authenticationToken` validation. API pass updates use `PassUpdatePolicy` enforcing ownership. `ScopedByRegion` trait already on Pass model. HMAC signature auth validates shared secret per user.
- **V. Performance & Reliability**: ✅ PASS — All push sending, pass regeneration, and bulk updates via queued jobs on dedicated `push-notifications` Horizon queue. Bulk operations chunked with eager loading. Rate limiter at 50/sec/account. Retry with exponential backoff (3 attempts).

**Gate result**: ALL PASS — proceeding to Phase 0.

## Project Structure

### Documentation (this feature)

```text
specs/001-push-pass-updates/
├── plan.md              # This file
├── research.md          # Phase 0 output
├── data-model.md        # Phase 1 output
├── quickstart.md        # Phase 1 output
├── contracts/           # Phase 1 output (OpenAPI)
└── tasks.md             # Phase 2 output (/speckit.tasks)
```

### Source Code (repository root)

```text
app/
├── Http/
│   ├── Controllers/
│   │   ├── Api/
│   │   │   └── PassUpdateController.php          # API PATCH endpoint (Sanctum + HMAC)
│   │   ├── Passes/
│   │   │   ├── PassUpdateDashboardController.php  # Dashboard single + bulk update UI
│   │   │   └── AppleWebServiceController.php      # Apple Web Service Protocol (5 endpoints)
│   ├── Middleware/
│   │   └── VerifyHmacSignature.php                # HMAC-SHA256 auth middleware
│   └── Requests/
│       ├── UpdatePassFieldsRequest.php            # Single pass field update validation
│       └── BulkUpdatePassesRequest.php            # Bulk update validation
├── Jobs/
│   ├── SendApplePushNotificationJob.php           # APNS HTTP/2 push (per device)
│   ├── UpdateGoogleWalletObjectJob.php            # Google REST API PATCH
│   ├── BulkPassUpdateJob.php                      # Orchestrates bulk update + push
│   ├── ProcessPassUpdateJob.php                   # Single pass update + push dispatch
│   └── PrunePassUpdateHistoryJob.php              # 90-day audit log cleanup
├── Models/
│   ├── DeviceRegistration.php                     # Apple device ↔ pass association
│   ├── PassUpdate.php                             # Audit log of field changes
│   └── BulkUpdate.php                             # Bulk update job tracking
├── Policies/
│   └── PassUpdatePolicy.php                       # Authorization for pass updates
├── Services/
│   ├── ApplePassService.php                       # MODIFIED: per-user creds, webServiceURL, authToken
│   ├── GooglePassService.php                      # MODIFIED: per-user creds, PATCH method
│   ├── ApplePushService.php                       # NEW: APNS HTTP/2 client
│   └── PassUpdateService.php                      # NEW: Orchestrates update + push flow
└── Events/
    └── PassUpdatedEvent.php                       # Dispatched on pass field change

resources/js/pages/passes/
├── show.tsx                                       # MODIFIED: add update panel, device status, delivery log
└── components/
    ├── PassUpdatePanel.tsx                        # Field update form
    ├── BulkUpdatePanel.tsx                        # Bulk update form + progress
    └── DeliveryStatusBadge.tsx                    # Push delivery status indicator

routes/
├── passes.php                                     # MODIFIED: add Apple Web Service Protocol routes
└── api.php                                        # MODIFIED: add PATCH /passes/{pass}/fields

tests/Feature/
├── PushNotification/
│   ├── AppleWebServiceProtocolTest.php            # 5 endpoint tests
│   ├── DeviceRegistrationTest.php                 # Registration lifecycle
│   ├── PassUpdateApiTest.php                      # API PATCH + HMAC tests
│   ├── ApplePushNotificationTest.php              # APNS mocked tests
│   ├── GoogleWalletUpdateTest.php                 # Google PATCH mocked tests
│   ├── BulkPassUpdateTest.php                     # Bulk update job tests
│   ├── ChangeMessageTest.php                      # Lock screen message formatting
│   └── PullToRefreshTest.php                      # Pull-to-refresh flow tests

database/migrations/
├── xxxx_create_device_registrations_table.php
├── xxxx_create_pass_updates_table.php
├── xxxx_create_bulk_updates_table.php
├── xxxx_add_authentication_token_to_passes_table.php

config/
└── passkit.php                                    # MODIFIED: add push + web_service config
```

**Structure Decision**: Standard Laravel web application structure. All new code placed within existing directory conventions — no new base folders needed. Backend controllers, models, services, jobs follow established patterns. Frontend pages extend existing `resources/js/pages/passes/` directory.

## Constitution Re-Evaluation (Post-Design)

*Re-evaluated after Phase 1 design (data-model.md, contracts/openapi.yaml, quickstart.md, research.md)*

- **I. Laravel-First Architecture**: ✅ PASS — Data model uses Eloquent models with proper relationships (belongsTo, hasMany). 3 new tables follow existing migration conventions. Form Requests define validation rules. No raw SQL. No new base folders. No new dependencies — APNS uses native PHP curl with HTTP/2; Google uses existing JWT auth. `forUser()` factory pattern follows Laravel service container conventions.
- **II. Type-Safe Routing & Inertia Navigation**: ✅ PASS — API contracts use named Laravel routes. OpenAPI spec defines endpoints registered in `routes/passes.php` and `routes/api.php`. Apple Web Service Protocol endpoints are external API routes (no Inertia). Dashboard pages under `resources/js/pages/passes/` follow Wayfinder.
- **III. Test-Backed Changes**: ✅ PASS — Plan defines 8 test files covering: Apple Web Service Protocol (5 endpoints), device registration lifecycle, API PATCH with HMAC, APNS push (mocked), Google PATCH (mocked), bulk updates, change messages, pull-to-refresh. Minimal test: `php artisan test --filter=PushNotification --compact`.
- **IV. Security & Tenant Isolation**: ✅ PASS — `device_registrations` and `pass_updates` tables include `user_id` for tenant isolation. `PassUpdatePolicy` enforces ownership. `authentication_token` uniquely validates Apple Web Service Protocol requests per pass. HMAC middleware validates server-to-server API calls. No secrets outside config files.
- **V. Performance & Reliability**: ✅ PASS — All push, update, and bulk operations via queued jobs on dedicated `push-notifications` Horizon queue. Bulk operations chunked. Rate limiter enforces 50 APNS/sec, 15 Google/sec. Retry with exponential backoff. `PrunePassUpdateHistoryJob` scheduled daily for 90-day cleanup. Composite indexes on `device_registrations` and `pass_updates` prevent slow queries.

**Post-design gate result**: ALL PASS — no violations detected.

## Complexity Tracking

> No Constitution violations detected — this section is intentionally empty.
