# Tasks: Pass Validation & Scanning

**Input**: Design documents from `/specs/001-pass-validation-scanning/`
**Prerequisites**: plan.md (required), spec.md (required for user stories), research.md, data-model.md, contracts/

**Tests**: Tests are REQUIRED for any change unless a governance-approved waiver is documented in spec.md.

**Organization**: Tasks are grouped by user story to enable independent implementation and testing of each story.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (e.g., US1, US2, US3)
- Include exact file paths in descriptions

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Project initialization, migrations, models, middleware

- [X] T001 Create `scanner_links` migration in `database/migrations/`
- [X] T002 Create `scan_events` migration in `database/migrations/`
- [X] T003 Create migration to add `usage_type`, `status`, `custom_redemption_message`, `redeemed_at` to passes table in `database/migrations/`
- [X] T004 Create `ScannerLink` model in `app/Models/ScannerLink.php` and factory in `database/factories/ScannerLinkFactory.php`
- [X] T005 Create `ScanEvent` model in `app/Models/ScanEvent.php` and factory in `database/factories/ScanEventFactory.php`
- [X] T006 Update `Pass` model in `app/Models/Pass.php` with new fields, casts, and relationships
- [X] T007 Create `ValidateScannerToken` middleware in `app/Http/Middleware/ValidateScannerToken.php` and register in `bootstrap/app.php`

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Core services and routing that MUST be complete before any user story

- [X] T008 [P] Implement HMAC payload generation and validation in `app/Services/PassPayloadService.php`
- [X] T009 [P] Create `ScannerLinkPolicy` in `app/Policies/ScannerLinkPolicy.php`
- [X] T010 Create `ScannerController` to render web scanner UI at `/scanner/{token}` in `app/Http/Controllers/Scanner/ScannerController.php` and register route in `routes/web.php`

**Checkpoint**: Foundation ready — user story implementation can begin

---

## Phase 3: User Story 1 — Web-based Pass Scanning & Validation (P1) 🎯 MVP

**Goal**: Merchants scan customer passes via camera or manual entry to verify authenticity and status.

### Tests for US1 (REQUIRED) ⚠️

- [X] T011 [P] [US1] Feature test for `ValidatePassController` (valid, invalid signature, expired, cross-tenant) in `tests/Feature/Scanner/ValidatePassTest.php`
- [X] T012 [P] [US1] Feature test for `ValidateScannerToken` middleware in `tests/Feature/Scanner/ScannerAuthenticationTest.php`

### Implementation for US1

- [X] T013 [P] [US1] Create `ValidatePassRequest` in `app/Http/Requests/Scanner/ValidatePassRequest.php`
- [X] T014 [US1] Create `ValidatePassController` with `/api/scanner/validate` endpoint in `app/Http/Controllers/Scanner/ValidatePassController.php` and register route in `routes/api.php`
- [X] T015 [P] [US1] Create `Scanner/Index.tsx` React page with camera scanning in `resources/js/pages/Scanner/Index.tsx`
- [X] T016 [US1] Implement API integration in `Scanner/Index.tsx` to call `/api/scanner/validate`
- [X] T017 [US1] Implement UI states for Valid, Invalid, Voided, Pass Not Found in `Scanner/Index.tsx`
- [X] T018 [US1] Implement manual entry fallback UI in `Scanner/Index.tsx`
- [X] T019 [US1] Implement offline detection and error display in `Scanner/Index.tsx`

**Checkpoint**: US1 fully functional and testable

---

## Phase 4: User Story 2 — Single-Use Coupon Redemption (P1)

**Goal**: Merchants redeem single-use coupons with auto-void and double-redemption prevention.

### Tests for US2 (REQUIRED) ⚠️

- [X] T020 [P] [US2] Feature test for `RedeemPassController` (successful redemption, already redeemed, concurrency/locking) in `tests/Feature/Scanner/RedeemPassTest.php`

### Implementation for US2

- [X] T021 [P] [US2] Create `RedeemPassRequest` in `app/Http/Requests/Scanner/RedeemPassRequest.php`
- [X] T022 [US2] Create `RedeemPassController` with pessimistic locking at `/api/scanner/redeem` in `app/Http/Controllers/Scanner/RedeemPassController.php` and register route in `routes/api.php`
- [X] T023 [US2] Update `Scanner/Index.tsx` to show "Redeem" button for single-use passes and handle redemption API response
- [X] T024 [US2] Implement "already redeemed" UI state in `Scanner/Index.tsx`

**Checkpoint**: US2 fully functional and testable

---

## Phase 5: User Story 3 — Multi-Use Loyalty Scanning (P2)

**Goal**: Merchants scan loyalty passes to log visits without voiding.

### Tests for US3 (REQUIRED) ⚠️

- [X] T025 [P] [US3] Feature test for multi-use pass scanning and event logging in `tests/Feature/Scanner/RedeemPassTest.php`

### Implementation for US3

- [X] T026 [US3] Update `RedeemPassController` to handle `multi_use` passes (log `ScanEvent` without voiding)
- [X] T027 [US3] Update `Scanner/Index.tsx` to show "Log Visit" button for multi-use passes

**Checkpoint**: US3 fully functional and testable

---

## Phase 6: User Story 4 — Custom Redemption Messages (P3)

**Goal**: Merchants see custom instructions on successful scan/redemption.

### Tests for US4 (REQUIRED) ⚠️

- [X] T028 [P] [US4] Feature test for custom redemption messages in `tests/Feature/Scanner/ValidatePassTest.php` and `tests/Feature/Scanner/RedeemPassTest.php`

### Implementation for US4

- [X] T029 [US4] Update `Scanner/Index.tsx` to display `custom_redemption_message` upon successful scan/redemption

**Checkpoint**: US4 fully functional and testable

---

## Phase 7: Polish & Cross-Cutting

**Purpose**: Admin UI, formatting, final validation

- [X] T030 Add admin UI to generate and manage `ScannerLink` URLs in `resources/js/pages/Settings/ScannerLinks.tsx` and `app/Http/Controllers/Settings/ScannerLinkController.php`
- [X] T031 Add scan event history display in `resources/js/pages/Passes/Show.tsx`
- [X] T032 Run Laravel Pint (`vendor/bin/pint --dirty --format agent`)
- [X] T033 Run full test suite (`php artisan test --compact`)

## Dependencies

- Phase 1 & 2 must be completed before any User Story
- Phase 3 (US1) must be completed before Phase 4 (US2) and Phase 5 (US3)
- Phase 4 (US2) and Phase 5 (US3) can be implemented in parallel after Phase 3
- Phase 6 (US4) depends on Phase 3 and Phase 4
- Phase 7 should be completed last
