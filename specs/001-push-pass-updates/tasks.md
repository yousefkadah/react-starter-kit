# Tasks: Push Notifications & Real-Time Pass Updates

**Input**: Design documents from `/specs/001-push-pass-updates/`
**Prerequisites**: plan.md (required), spec.md (required for user stories), research.md, data-model.md, contracts/

**Tests**: Tests are REQUIRED for any change unless a governance-approved waiver is documented in spec.md.

**Organization**: Tasks are grouped by user story to enable independent implementation and testing of each story.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (e.g., US1, US2, US3)
- Include exact file paths in descriptions

## Path Conventions

- **Backend**: `app/`, `database/`, `routes/`, `config/`, `tests/` at repository root
- **Frontend**: `resources/js/pages/`, `resources/js/components/`

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Config, migrations, and shared model foundations that all user stories depend on

- [X] T001 Add `push` and `web_service` keys to `config/passkit.php` with APNS environment, rate limits, retry backoff, and web service base URL
- [X] T002 Create migration to add `authentication_token` column to `passes` table in `database/migrations/xxxx_add_authentication_token_to_passes_table.php`
- [X] T003 Create backfill migration to generate `authentication_token` for all existing passes in `database/migrations/xxxx_backfill_authentication_tokens_on_passes.php`
- [X] T004 [P] Create `device_registrations` table migration in `database/migrations/xxxx_create_device_registrations_table.php` with columns and indexes per data-model.md
- [X] T005 [P] Create `pass_updates` table migration in `database/migrations/xxxx_create_pass_updates_table.php` with columns and indexes per data-model.md
- [X] T006 [P] Create `bulk_updates` table migration in `database/migrations/xxxx_create_bulk_updates_table.php` with columns and indexes per data-model.md
- [X] T007 Run `php artisan migrate` and verify all 4 new migrations apply cleanly

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Models, service refactors, and core services that MUST be complete before ANY user story can be implemented

**⚠️ CRITICAL**: No user story work can begin until this phase is complete

### Models

- [X] T008 [P] Create `DeviceRegistration` model in `app/Models/DeviceRegistration.php` with fillable, casts, relationships (`pass()`, `user()`), `HasFactory` trait, and `scopeActive()` query scope
- [X] T009 [P] Create `PassUpdate` model in `app/Models/PassUpdate.php` with fillable, casts (json `fields_changed`), relationships (`pass()`, `user()`, `bulkUpdate()`), `HasFactory` trait
- [X] T010 [P] Create `BulkUpdate` model in `app/Models/BulkUpdate.php` with fillable, casts (json `filters`), relationships (`user()`, `passTemplate()`, `passUpdates()`), `HasFactory` trait, and `scopeInProgress()` query scope for mutex check
- [X] T011 Update `Pass` model in `app/Models/Pass.php` — add `authentication_token` to `$fillable` and `$hidden`, add `deviceRegistrations()` and `passUpdates()` relationships, add `hasRegisteredDevices(): bool` helper method
- [X] T012 Update `PassTemplate` model in `app/Models/PassTemplate.php` — add `bulkUpdates()` relationship and `hasBulkUpdateInProgress(): bool` mutex helper method
- [X] T013 Update `User` model in `app/Models/User.php` — add `deviceRegistrations()`, `passUpdates()`, and `bulkUpdates()` relationships

### Factories

- [X] T014 [P] Create `DeviceRegistrationFactory` in `database/factories/DeviceRegistrationFactory.php`
- [X] T015 [P] Create `PassUpdateFactory` in `database/factories/PassUpdateFactory.php`
- [X] T016 [P] Create `BulkUpdateFactory` in `database/factories/BulkUpdateFactory.php`

### Service Refactors (Per-User Credentials)

- [X] T017 Refactor `ApplePassService` in `app/Services/ApplePassService.php` — add static `forUser(User $user)` factory method that loads credentials from `apple_certificates` table, inject `webServiceURL` and `authenticationToken` into `buildPassJson()` output
- [X] T018 Refactor `GooglePassService` in `app/Services/GooglePassService.php` — add static `forUser(User $user)` factory method that loads credentials from `google_credentials` table, add `patchObject(string $objectId, array $updates): array` method for Google REST API PATCH

### New Core Services

- [X] T019 Create `ApplePushService` in `app/Services/ApplePushService.php` — APNS HTTP/2 client using Laravel HTTP facade with curl options, `forUser(User $user)` factory, `sendPush(string $deviceToken, string $passTypeIdentifier): bool` method, handles APNS response codes (200, 410 → deactivate registration, 429 → retry)
- [X] T020 Create `PassUpdateService` in `app/Services/PassUpdateService.php` — orchestrator: accepts pass + field updates, validates fields against template field map, validates 10KB limit, persists `PassUpdate` record, regenerates `.pkpass`/Google object, dispatches push notification jobs, dispatches `PassUpdatedEvent`

### Policy & Middleware

- [X] T021 [P] Create `PassUpdatePolicy` in `app/Policies/PassUpdatePolicy.php` — `update(User $user, Pass $pass): bool` enforces ownership, `viewHistory(User $user, Pass $pass): bool`
- [X] T022 [P] Create `VerifyHmacSignature` middleware in `app/Http/Middleware/VerifyHmacSignature.php` — validates `X-Signature` header as HMAC-SHA256 of request body using user's shared secret, register in `bootstrap/app.php`

### Event

- [X] T023 Create `PassUpdatedEvent` in `app/Events/PassUpdatedEvent.php` — dispatched after pass fields are updated, carries pass ID, changed fields, and update source

### Form Requests

- [X] T024 [P] Create `UpdatePassFieldsRequest` in `app/Http/Requests/UpdatePassFieldsRequest.php` — validates `fields` (required array, min:1), optional `change_messages` (array of strings, max:256)
- [X] T025 [P] Create `BulkUpdatePassesRequest` in `app/Http/Requests/BulkUpdatePassesRequest.php` — validates `pass_template_id` (required, exists scoped to user), `field_key` (required string), `field_value` (required), optional `filters`

**Checkpoint**: Foundation ready — all models, services, policies, and middleware in place. User story implementation can begin.

---

## Phase 3: User Story 1 — Update a Single Pass Field and Push to Device (Priority: P1) 🎯 MVP

**Goal**: Operator updates a pass field via the dashboard, the system regenerates the pass, sends APNS push (Apple) or patches Google Wallet object, and records the delivery status.

**Independent Test**: Update a single pass's field, verify the pass is regenerated, push notification dispatched, and delivery status tracked.

### Tests for User Story 1 ⚠️

- [X] T026 [P] [US1] Create `ApplePushNotificationTest` in `tests/Feature/PushNotification/ApplePushNotificationTest.php` — tests: APNS push sends empty payload, handles 200/410/429 response codes, deactivates registration on 410, uses per-user certificate, rate limiting respected
- [X] T027 [P] [US1] Create `GoogleWalletUpdateTest` in `tests/Feature/PushNotification/GoogleWalletUpdateTest.php` — tests: Google PATCH sends correct payload, uses per-user service account, handles success/failure, 3 push/day limit per object
- [X] T028 [P] [US1] Create `PassUpdateDashboardTest` in `tests/Feature/PushNotification/PassUpdateDashboardTest.php` — tests: dashboard update triggers regeneration, dispatches push jobs, records PassUpdate record, rejects voided pass updates, rejects exceeding 10KB, shows warning for no registered devices

### Implementation for User Story 1

- [X] T029 [US1] Create `ProcessPassUpdateJob` in `app/Jobs/ProcessPassUpdateJob.php` — queued on `push-notifications`, accepts pass + field changes, calls `PassUpdateService`, dispatches platform-specific push jobs, updates `PassUpdate` delivery status
- [X] T030 [US1] Create `SendApplePushNotificationJob` in `app/Jobs/SendApplePushNotificationJob.php` — queued on `push-notifications`, sends APNS push via `ApplePushService`, handles response codes, marks device inactive on 410, retries up to 3× with exponential backoff [30s, 120s, 600s]
- [X] T031 [US1] Create `UpdateGoogleWalletObjectJob` in `app/Jobs/UpdateGoogleWalletObjectJob.php` — queued on `push-notifications`, calls `GooglePassService::patchObject()`, rate limited to 15 req/s, retries up to 3× with exponential backoff
- [X] T032 [US1] Create `PassUpdateDashboardController` in `app/Http/Controllers/Passes/PassUpdateDashboardController.php` — `update(UpdatePassFieldsRequest $request, Pass $pass)` dispatches `ProcessPassUpdateJob`, returns updated pass data; `history(Pass $pass)` returns paginated `PassUpdate` records via Inertia
- [X] T033 [US1] Add pass update routes to `routes/passes.php` — `PATCH /passes/{pass}/update` and `GET /passes/{pass}/updates` within auth middleware group
- [X] T034 [US1] Update Horizon config in `config/horizon.php` — add `push-notifications` queue supervisor with appropriate process count, retry, and timeout settings

### Run Tests

- [X] T035 [US1] Run `php artisan test --compact --filter=ApplePushNotification` and verify all tests pass
- [X] T036 [US1] Run `php artisan test --compact --filter=GoogleWalletUpdate` and verify all tests pass
- [X] T037 [US1] Run `php artisan test --compact --filter=PassUpdateDashboard` and verify all tests pass

**Checkpoint**: Single pass update works end-to-end — field update → regenerate → push → delivery status tracked

---

## Phase 4: User Story 2 — Apple Device Registration and Web Service Protocol (Priority: P1)

**Goal**: Implement all 5 Apple Web Service Protocol endpoints so Apple Wallet devices can register for push updates, check for updated passes, and download the latest pass version.

**Independent Test**: Simulate Apple Wallet API calls to register a device, list updated passes, download latest pass, and unregister.

### Tests for User Story 2 ⚠️

- [X] T038 [P] [US2] Create `AppleWebServiceProtocolTest` in `tests/Feature/PushNotification/AppleWebServiceProtocolTest.php` — tests: register device (201 new, 200 existing), unregister device (200), get updated passes (200 with serial numbers, 204 no updates), get latest pass (200 with .pkpass, 304 not modified, 401 bad token), error logging (200)
- [X] T039 [P] [US2] Create `DeviceRegistrationTest` in `tests/Feature/PushNotification/DeviceRegistrationTest.php` — tests: registration lifecycle (create, update push token, deactivate, delete), auth token validation rejects mismatched tokens, rejects non-existent serial numbers with 401, tenant isolation (different user's pass rejected)

### Implementation for User Story 2

- [X] T040 [US2] Create `AppleWebServiceController` in `app/Http/Controllers/Passes/AppleWebServiceController.php` — implements 5 endpoints per contracts/openapi.yaml: `registerDevice()` (POST), `unregisterDevice()` (DELETE), `getUpdatedPasses()` (GET with passesUpdatedSince), `getLatestPass()` (GET returns .pkpass, supports If-Modified-Since/304), `logErrors()` (POST)
- [X] T041 [US2] Add Apple Web Service Protocol routes to `routes/passes.php` — 5 routes under `/api/apple/v1/` prefix, no auth middleware (uses ApplePass auth token validation), routes MUST NOT be inside auth middleware group
- [X] T042 [US2] Implement authentication token validation in `AppleWebServiceController` — extract token from `Authorization: ApplePass {token}` header, validate against `passes.authentication_token`, return 401 if invalid
- [X] T043 [US2] Ensure `ApplePassService::buildPassJson()` includes `webServiceURL` (from config) and `authenticationToken` (from pass model) in generated pass.json

### Run Tests

- [X] T044 [US2] Run `php artisan test --compact --filter=AppleWebServiceProtocol` and verify all tests pass
- [X] T045 [US2] Run `php artisan test --compact --filter=DeviceRegistration` and verify all tests pass

**Checkpoint**: Apple Web Service Protocol fully implemented — devices can register, check updates, download passes, and unregister

---

## Phase 5: User Story 3 — Lock Screen Change Messages (Priority: P2)

**Goal**: When a pass field is updated, customers see a contextual notification message (e.g., "You now have 75 points!") on their lock screen instead of a generic update.

**Independent Test**: Configure a change message on a field, update that field, verify the generated pass.json includes the `changeMessage` key with the formatted string.

### Tests for User Story 3 ⚠️

- [X] T046 [P] [US3] Create `ChangeMessageTest` in `tests/Feature/PushNotification/ChangeMessageTest.php` — tests: change message with `%@` placeholder formats correctly, multiple change messages on multiple fields, field without change message updates silently, change message included in regenerated pass.json, Google Wallet changes trigger system notification (no custom message)

### Implementation for User Story 3

- [X] T047 [US3] Update `PassUpdateService` in `app/Services/PassUpdateService.php` — accept optional `change_messages` parameter, store change messages per field, pass them to `ApplePassService` during pass regeneration
- [X] T048 [US3] Update `ApplePassService::buildPassJson()` in `app/Services/ApplePassService.php` — inject `changeMessage` key with `%@` placeholder into each field definition in pass.json when a change message is configured for that field
- [X] T049 [US3] Update `UpdatePassFieldsRequest` in `app/Http/Requests/UpdatePassFieldsRequest.php` — ensure `change_messages` validation allows per-field message templates with `%@` placeholder

### Run Tests

- [X] T050 [US3] Run `php artisan test --compact --filter=ChangeMessage` and verify all tests pass

**Checkpoint**: Change messages render correctly in pass.json — lock screen shows formatted text on field updates

---

## Phase 6: User Story 4 — Bulk Pass Updates (Priority: P2)

**Goal**: Operator updates a field across all passes of a template at once. System processes asynchronously with progress tracking, rate limiting, and retry logic.

**Independent Test**: Create multiple passes from one template, trigger bulk update, verify all passes updated, push notifications sent, progress tracked, and rate limits enforced.

### Tests for User Story 4 ⚠️

- [X] T051 [P] [US4] Create `BulkPassUpdateTest` in `tests/Feature/PushNotification/BulkPassUpdateTest.php` — tests: bulk update queues job and returns 202, progress tracking (total/processed/failed counts), rate limiting enforced at 50 APNS/sec, retry on individual failures, template mutex rejects concurrent bulk update (409), filters by status/platform, handles mixed Apple/Google passes

### Implementation for User Story 4

- [X] T052 [US4] Create `BulkPassUpdateJob` in `app/Jobs/BulkPassUpdateJob.php` — queued on `push-notifications`, chunks passes by template, dispatches `ProcessPassUpdateJob` per pass, rate limits at 50/sec via `RateLimiter`, updates `BulkUpdate` model progress (processed_count, failed_count), marks completed/failed on finish
- [X] T053 [US4] Create `BulkUpdateController` in `app/Http/Controllers/Passes/PassUpdateDashboardController.php` — add `bulkUpdate(BulkUpdatePassesRequest $request)` to create `BulkUpdate` record, enforce template mutex, dispatch `BulkPassUpdateJob`, return 202; add `bulkUpdateStatus(BulkUpdate $bulkUpdate)` to return progress
- [X] T054 [US4] Add bulk update routes to `routes/passes.php` — `POST /passes/bulk-update` and `GET /passes/bulk-update/{bulkUpdate}` within auth middleware group
- [X] T055 [US4] Configure rate limiter for bulk push in `bootstrap/app.php` or `app/Providers/AppServiceProvider.php` — register `push-notifications` rate limiter at 50/sec per user

### Run Tests

- [X] T056 [US4] Run `php artisan test --compact --filter=BulkPassUpdate` and verify all tests pass

**Checkpoint**: Bulk updates work end-to-end — template-wide field change → chunked processing → individual push dispatch → progress tracking

---

## Phase 7: User Story 5 — Pull-to-Refresh Support (Priority: P3)

**Goal**: When a customer manually refreshes their pass in Apple Wallet, the wallet checks for updates and downloads the latest version without a push notification.

**Independent Test**: Update a pass on the server, simulate a pull-to-refresh request (GET latest pass with If-Modified-Since), verify updated .pkpass served or 304 returned.

### Tests for User Story 5 ⚠️

- [X] T057 [P] [US5] Create `PullToRefreshTest` in `tests/Feature/PushNotification/PullToRefreshTest.php` — tests: GET latest pass returns updated .pkpass when modified, GET returns 304 when not modified (If-Modified-Since >= updated_at), Last-Modified header present in response, passesUpdatedSince filter correctly returns only updated serial numbers

### Implementation for User Story 5

- [X] T058 [US5] Update `AppleWebServiceController::getLatestPass()` in `app/Http/Controllers/Passes/AppleWebServiceController.php` — add `If-Modified-Since` header parsing, compare against pass `updated_at`, return 304 if not modified, set `Last-Modified` response header
- [X] T059 [US5] Update `AppleWebServiceController::getUpdatedPasses()` in `app/Http/Controllers/Passes/AppleWebServiceController.php` — parse `passesUpdatedSince` query parameter as UNIX timestamp, query passes where `updated_at > timestamp`, return serial numbers and new `lastUpdated` tag

### Run Tests

- [X] T060 [US5] Run `php artisan test --compact --filter=PullToRefresh` and verify all tests pass

**Checkpoint**: Pull-to-refresh works — Apple Wallet can check for updates and download latest pass without push

---

## Phase 8: User Story 6 — Pass Update via API (Priority: P2)

**Goal**: External systems update pass fields via REST API with Sanctum token or HMAC signature authentication, triggering the same push flow as dashboard updates.

**Independent Test**: Send PATCH request to `/api/passes/{pass}/fields` with valid auth, verify pass updated, push dispatched, and correct response returned.

### Tests for User Story 6 ⚠️

- [X] T061 [P] [US6] Create `PassUpdateApiTest` in `tests/Feature/PushNotification/PassUpdateApiTest.php` — tests: PATCH with Sanctum token updates pass and returns 200, PATCH with valid HMAC signature accepted, PATCH with invalid HMAC rejected, 403 for other user's pass, 422 for invalid field values, 409 for voided pass, 422 for exceeding 10KB, response includes delivery status fields, update history GET endpoint returns paginated records

### Implementation for User Story 6

- [X] T062 [US6] Create `PassUpdateController` in `app/Http/Controllers/Api/PassUpdateController.php` — `update(UpdatePassFieldsRequest $request, Pass $pass)` dispatches `ProcessPassUpdateJob`, returns `PassUpdateResponse`; `history(Pass $pass)` returns paginated `PassUpdate` records as JSON resource
- [X] T063 [US6] Create `PassUpdateResource` in `app/Http/Resources/PassUpdateResource.php` — transforms `PassUpdate` model to API response matching contracts/openapi.yaml schema
- [X] T064 [US6] Add API routes to `routes/api.php` — `PATCH /api/passes/{pass}/fields` with `auth:sanctum` + optional HMAC middleware, `GET /api/passes/{pass}/updates` with `auth:sanctum`
- [X] T065 [US6] Register HMAC middleware route group in `routes/api.php` — apply `VerifyHmacSignature` middleware as optional (request uses either Sanctum OR HMAC, not both required)

### Run Tests

- [X] T066 [US6] Run `php artisan test --compact --filter=PassUpdateApi` and verify all tests pass

**Checkpoint**: External API works — PATCH and GET endpoints available with dual auth, same push flow as dashboard

---

## Phase 9: Frontend Dashboard UI

**Purpose**: Inertia/React UI components for pass update panel, delivery status, and bulk update progress

- [X] T067 [P] [US1] Create `PassUpdatePanel` component in `resources/js/pages/passes/components/PassUpdatePanel.tsx` — form for editing pass fields with submit via Inertia `useForm`, shows warning banner when no devices registered (FR-022), disables submit for voided passes
- [X] T068 [P] [US4] Create `BulkUpdatePanel` component in `resources/js/pages/passes/components/BulkUpdatePanel.tsx` — form for bulk update with template selector, field picker, progress bar polling via Inertia polling, shows mutex warning if bulk update in progress
- [X] T069 [P] [US1] Create `DeliveryStatusBadge` component in `resources/js/pages/passes/components/DeliveryStatusBadge.tsx` — badge showing Apple/Google delivery status (pending/sent/delivered/failed/skipped) with appropriate colors
- [X] T070 [US1] Update `show.tsx` in `resources/js/pages/passes/show.tsx` — add PassUpdatePanel, delivery status log (recent PassUpdate records), device registration count, integrate DeliveryStatusBadge
- [X] T071 [US4] Update `show.tsx` or create bulk update section — add BulkUpdatePanel with template-level bulk update trigger, show active bulk update progress if one is in progress
- [X] T072 Generate Wayfinder route definitions by running `php artisan wayfinder:generate` for new pass update and bulk update routes

---

## Phase 10: Polish & Cross-Cutting Concerns

**Purpose**: Operational jobs, cleanup, final validation

- [X] T073 Create `PrunePassUpdateHistoryJob` in `app/Jobs/PrunePassUpdateHistoryJob.php` — deletes `pass_updates` records older than 90 days, scheduled daily in `routes/console.php`
- [X] T074 Register `PrunePassUpdateHistoryJob` schedule in `routes/console.php` — `Schedule::job(PrunePassUpdateHistoryJob::class)->daily()`
- [X] T075 Register `PassUpdatePolicy` in `app/Providers/AuthServiceProvider.php` or via automatic discovery — bind to `Pass` model for update/viewHistory gates
- [X] T076 [P] Run `vendor/bin/pint --dirty --format agent` to format all new and modified PHP files
- [X] T077 [P] Run `npx eslint resources/js/pages/passes/` to lint all new and modified TypeScript files
- [X] T078 Run full push notification test suite: `php artisan test --compact --filter=PushNotification`
- [X] T079 Run `npm run build` and verify no frontend build errors
- [X] T080 Verify all existing tests still pass: `php artisan test --compact`

**Checkpoint**: Feature complete — all stories implemented, tested, formatted, and validated

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies — start immediately
- **Foundational (Phase 2)**: Depends on Phase 1 (migrations must be applied) — BLOCKS all user stories
- **User Story 1 (Phase 3)**: Depends on Phase 2 — single pass update is MVP
- **User Story 2 (Phase 4)**: Depends on Phase 2 — can run in parallel with US1 (different files)
- **User Story 3 (Phase 5)**: Depends on Phase 2 + US1 (extends PassUpdateService)
- **User Story 4 (Phase 6)**: Depends on Phase 2 + US1 (uses ProcessPassUpdateJob)
- **User Story 5 (Phase 7)**: Depends on US2 (extends AppleWebServiceController)
- **User Story 6 (Phase 8)**: Depends on Phase 2 + US1 (uses PassUpdateService)
- **Frontend (Phase 9)**: Depends on US1 + US4 controllers being in place
- **Polish (Phase 10)**: Depends on all phases being complete

### User Story Dependencies

- **US1 (P1)**: After Foundational → Can start immediately — core MVP
- **US2 (P1)**: After Foundational → Can run in parallel with US1 (different controllers/routes)
- **US3 (P2)**: After US1 → Extends PassUpdateService and ApplePassService
- **US4 (P2)**: After US1 → Uses ProcessPassUpdateJob for individual pass dispatch
- **US5 (P3)**: After US2 → Extends AppleWebServiceController (If-Modified-Since/304)
- **US6 (P2)**: After US1 → Uses PassUpdateService (different controller, different route file)

### Within Each User Story

- Tests MUST be written and FAIL before implementation
- Models → Services → Jobs → Controllers → Routes
- Core implementation before integration
- Story complete before moving to next priority

### Parallel Opportunities

- **Phase 1**: T004, T005, T006 can run in parallel (different migration files)
- **Phase 2**: T008, T009, T010 in parallel (different model files); T014, T015, T016 in parallel (different factories); T021, T022 in parallel (different files); T024, T025 in parallel (different request files)
- **Phase 3 + Phase 4**: US1 and US2 can run in parallel (different controllers, different route sections)
- **Phase 5 + Phase 6 + Phase 8**: US3, US4, and US6 can run in parallel after US1 completes (different files)
- **Phase 9**: T067, T068, T069 can run in parallel (different component files)
- **Phase 10**: T076, T077 can run in parallel (different toolchains)

---

## Parallel Example: User Stories 1 & 2 (after Foundational)

```bash
# In parallel — different files, no shared dependencies:
# Developer A (US1):
  T026: ApplePushNotificationTest
  T027: GoogleWalletUpdateTest
  T028: PassUpdateDashboardTest
  T029: ProcessPassUpdateJob
  T030: SendApplePushNotificationJob
  T031: UpdateGoogleWalletObjectJob
  T032: PassUpdateDashboardController
  T033: Dashboard pass update routes

# Developer B (US2):
  T038: AppleWebServiceProtocolTest
  T039: DeviceRegistrationTest
  T040: AppleWebServiceController
  T041: Apple Web Service Protocol routes
  T042: Auth token validation
  T043: webServiceURL + authenticationToken in pass.json
```

---

## Implementation Strategy

### MVP First (User Stories 1 + 2 Only)

1. Complete Phase 1: Setup (migrations + config)
2. Complete Phase 2: Foundational (models, services, policies, middleware)
3. Complete Phase 3: User Story 1 — single pass update + push
4. Complete Phase 4: User Story 2 — Apple device registration + Web Service Protocol
5. **STOP and VALIDATE**: Both P1 stories independently testable
6. Deploy/demo if ready — passes can be updated and pushed to devices

### Incremental Delivery

1. Setup + Foundational → Foundation ready
2. US1 + US2 → **MVP: Pass updates work end-to-end**
3. US3 (Change Messages) → Lock screen notifications
4. US4 (Bulk Updates) → Template-wide mass updates
5. US5 (Pull-to-Refresh) → Graceful fallback mechanism
6. US6 (API) → External integrations enabled
7. Frontend + Polish → Complete feature

### Suggested MVP Scope

**Phase 1 + Phase 2 + Phase 3 + Phase 4** — User Stories 1 & 2 (both P1). This delivers the core capability: update a pass field and have it pushed to Apple/Google devices, with full Apple Web Service Protocol support.

---

## Notes

- [P] tasks = different files, no dependencies
- [Story] label maps task to specific user story for traceability
- Each user story should be independently completable and testable
- Verify tests fail before implementing
- Commit after each task or logical group
- All APNS push sending uses mocked HTTP in tests (never call real Apple endpoints)
- Google PATCH uses mocked HTTP in tests
- Run `vendor/bin/pint --dirty --format agent` after PHP changes
- Run `php artisan wayfinder:generate` after adding new routes
