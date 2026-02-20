# Tasks: Pass Distribution System

**Input**: Design documents from `/specs/001-pass-distribution/`  
**Prerequisites**: plan.md ✓, spec.md ✓, research.md ✓, data-model.md ✓, contracts/ ✓

**Tests**: REQUIRED for every task (PHPUnit feature tests in `tests/Feature/PassDistribution/`)

**Minimal Test Run**: `php artisan test tests/Feature/PassDistribution/ --filter="PassDistribution"`

**Organization**: Tasks grouped by user story to enable independent implementation and testing. Each user story is an independently testable, deployable increment.

---

## Phase 1: Setup (Database & Model Foundation)

**Purpose**: Create base infrastructure for pass distribution links

- [X] T001 Create database migration for pass_distribution_links in `database/migrations/YYYY_MM_DD_HHMMSS_create_pass_distribution_links_table.php` with schema (id, pass_id, slug, status, last_accessed_at, accessed_count, timestamps) and indexes
- [X] T002 Create PassDistributionLink Eloquent model in `app/Models/PassDistributionLink.php` with relationships (belongsTo Pass), accessors (isActive(), isDisabled(), recordAccess(), url()), and boot method for UUID slug generation
- [X] T003 [P] Create PassDistributionLinkFactory in `database/factories/PassDistributionLinkFactory.php` with states (disabled, accessed) for testing
- [X] T004 [P] Update Pass model in `app/Models/Pass.php` to add hasMany('distributionLinks') relationship

---

## Phase 2: Foundational (Authorization & API Infrastructure)

**Purpose**: Setup authorization gates, validation, and API response structure. BLOCKING: No user story work begins until complete.

- [X] T005 Create StorePassDistributionLinkRequest in `app/Http/Requests/StorePassDistributionLinkRequest.php` with authorize() policy check for createDistributionLink
- [X] T006 [P] Create UpdatePassDistributionLinkRequest in `app/Http/Requests/UpdatePassDistributionLinkRequest.php` with status validation (active|disabled)
- [X] T007 [P] Create PassDistributionLinkService in `app/Services/PassDistributionLinkService.php` with create(), disable(), enable() methods
- [X] T008 Update PassPolicy in `app/Policies/PassPolicy.php` to add gates: createDistributionLink(), viewDistributionLinks(), updateDistributionLink() (accepts user, pass, link)
- [X] T009 Create PassDistributionLinkResource in `app/Http/Resources/PassDistributionLinkResource.php` for consistent JSON serialization (id, pass_id, slug, status, url, last_accessed_at, accessed_count, timestamps)
- [X] T010 [P] Create PassDistributionController in `app/Http/Controllers/PassDistributionController.php` with stubs for show(), index(), store(), update() methods

**Checkpoint**: Foundation ready. All authentication, validation, and response structures in place. User story development can now proceed in parallel.

---

## Phase 3: User Story 1 - Shareable Pass Link (Priority: P1) 🎯 MVP

**Goal**: Issuers can generate unique, shareable links for passes. End users can open links on their device and see device-appropriate add-to-wallet actions.

**Independent Test**: Create a pass, generate its distribution link, open the link on iOS/Android/Desktop, verify correct add-to-wallet action displayed and link access tracked.

### Tests for User Story 1 (REQUIRED) ⚠️

- [X] T011 [P] [US1] Create feature test CreatePassDistributionLinkTest in `tests/Feature/PassDistribution/CreatePassDistributionLinkTest.php` covering authenticated user can POST to create link, link has active status, slug is generated
- [X] T012 [P] [US1] Create feature test ViewPassLinkTest in `tests/Feature/PassDistribution/ViewPassLinkTest.php` covering unauthenticated user can GET /p/{slug}, correct HTTP response, pass data returned, device detected, access recorded

### Implementation for User Story 1

- [X] T013 [US1] Implement PassDistributionController@show() in `app/Http/Controllers/PassDistributionController.php` with: slug lookup via PassDistributionLink, verify link active, verify pass not voided, detect device type from User-Agent, record access via recordAccess(), return Inertia response with pass data and device type
- [X] T014 [US1] Implement device detection logic in `app/Http/Controllers/PassDistributionController.php` private method detectDevice() using User-Agent regex patterns for iOS (iPhone|iPad|iPod), Android, fallback to 'unknown'
- [X] T015 [US1] Create PassLink Inertia page in `resources/js/pages/PassLink.tsx` component receiving pass, device, link_status, add_to_wallet_url props; conditional rendering of Apple vs Google add-to-wallet buttons based on device type; fallback both buttons if unknown
- [X] T016 [US1] Implement PassDistributionController@store() in `app/Http/Controllers/PassDistributionController.php` with: authorize against Pass, call PassDistributionLinkService@create(), return PassDistributionLinkResource in 201 Created response
- [X] T017 [US1] Register routes in `routes/web.php`: GET /p/{slug} (public, no auth), POST /dashboard/passes/{pass}/distribution-links (protected), both route names passed to Wayfinder
- [X] T018 [US1] Update Pass model in `app/Models/Pass.php` to add getOrCreateDistributionLink() helper method returning first active link or creating new one
- [X] T019 [US1] Create PassDistributionLinkFactory state authenticated in `database/factories/PassDistributionLinkFactory.php` to create with specific pass_id for testing

**Checkpoint**: US1 complete. Users can create shareable pass links and open them on any device with correct add-to-wallet action shown. Minimal test run passes.

---

## Phase 4: User Story 2 - QR Code Distribution (Priority: P2)

**Goal**: Pass links are represented as QR codes that issuers can print or display for easy sharing.

**Independent Test**: Generate a QR code for a pass link, verify QR encodes correct URL, scan code and land on correct pass link.

### Tests for User Story 2 (REQUIRED) ⚠️

- [X] T020 [P] [US2] Create feature test QRCodeGenerationTest in `tests/Feature/PassDistribution/QRCodeGenerationTest.php` covering QR code data contains correct pass link URL, QR code rendered on public page, QR can be downloaded/saved
- [X] T021 [P] [US2] Create unit test PassDistributionLinkUrlTest in `tests/Unit/Models/PassDistributionLinkTest.php` covering url() method returns correct format /p/{slug}

### Implementation for User Story 2

- [X] T022 [US2] Create QRCodeDisplay React component in `resources/js/components/QRCodeDisplay.tsx` receiving url, width, height props; uses QRCode.js library to render canvas QR code; includes download button
- [X] T023 [US2] Install QRCode.js npm package via `npm install qrcode` and document in package.json
- [X] T024 [US2] Update PassLink Inertia page in `resources/js/pages/PassLink.tsx` to import and render QRCodeDisplay component with qr_code_data prop (text: pass link URL, width: 200, height: 200)
- [X] T025 [US2] Update PassDistributionController@show() in `app/Http/Controllers/PassDistributionController.php` to pass qr_code_data in Inertia props (text: public link URL)
- [X] T026 [US2] Update PassLink Inertia page in `resources/js/pages/PassLink.tsx` to display download button for QR code as PNG image

**Checkpoint**: US2 complete. Pass links display QR codes that can be scanned to open link or downloaded for print distribution.

---

## Phase 5: User Story 3 - Link Control (Priority: P3)

**Goal**: Issuers can disable or re-enable distribution links to control access without deleting the pass.

**Independent Test**: Disable a link, verify access returns error; re-enable link, verify access restored.

### Tests for User Story 3 (REQUIRED) ⚠️

- [X] T027 [P] [US3] Create feature test DisableEnableLinkTest in `tests/Feature/PassDistribution/DisableEnableLinkTest.php` covering disable link returns 403, cannot access disabled link, re-enable link works, access is restored
- [X] T028 [P] [US3] Create feature test LinkStatusValidationTest in `tests/Feature/PassDistribution/LinkStatusValidationTest.php` covering invalid status returns 422, only active|disabled allowed, authenticated user only

### Implementation for User Story 3

- [X] T029 [US3] Implement PassDistributionController@index() in `app/Http/Controllers/PassDistributionController.php` with: authorize viewDistributionLinks, eager load relationships, return paginated PassDistributionLinkResource collection, order by created_at desc
- [X] T030 [US3] Implement PassDistributionController@update() in `app/Http/Controllers/PassDistributionController.php` with: authorize updateDistributionLink, validate status field via UpdatePassDistributionLinkRequest, call PassDistributionLinkService@disable() or enable(), return updated PassDistributionLinkResource
- [X] T031 [US3] Implement PassDistributionLinkService@disable() in `app/Services/PassDistributionLinkService.php` updating link status to 'disabled'
- [X] T032 [US3] [P] Implement PassDistributionLinkService@enable() in `app/Services/PassDistributionLinkService.php` updating link status to 'active'
- [X] T033 [US3] Update PassDistributionController@show() in `app/Http/Controllers/PassDistributionController.php` to check if link->isDisabled() and return 403 with message "Link has been disabled"
- [X] T034 [US3] Create DistributionPanel Inertia page in `resources/js/pages/Passes/DistributionPanel.tsx` displaying list of links for a pass with: columns (slug, status, created_at, accessed_count, last_accessed_at, actions), "Create Link" button, toggle status buttons (Disable/Enable), link URL copy button
- [X] T035 [US3] Update PassDistributionController@index() route registration in `routes/web.php` with name passes.distribution-links.index
- [X] T036 [US3] Update PassDistributionController@update() route registration in `routes/web.php` with name passes.distribution-links.update and PATCH method
- [X] T037 [US3] Create useDistributionLinks React hook in `resources/js/hooks/useDistributionLinks.ts` for API calls (list, create, update links) with loading states and error handling

**Checkpoint**: US3 complete. Issuers can manage link lifecycle (create, disable, enable) from dashboard with real-time feedback.

---

## Phase 6: Edge Cases & Expiry Handling

**Purpose**: Handle pass expiry and invalid states gracefully

- [X] T038 Create feature test PassExpiryMessageTest in `tests/Feature/PassDistribution/PassExpiryMessageTest.php` covering expired pass shows message on link view, link still accessible, user cannot enroll expired pass
- [X] T039 [P] Create feature test PassVoidedTest in `tests/Feature/PassDistribution/PassVoidedTest.php` covering voided pass returns 410 Gone, link returns error message
- [X] T040 Update PassDistributionController@show() in `app/Http/Controllers/PassDistributionController.php` to check pass->isExpired() and return link_status: 'expired' in props with message in Inertia response
- [X] T041 [P] Update PassDistributionController@show() in `app/Http/Controllers/PassDistributionController.php` to check pass->isVoided() and return 410 Gone response with message
- [X] T042 Update PassLink Inertia page in `resources/js/pages/PassLink.tsx` to display warning banner if link_status is 'expired' with message "This pass has expired and is no longer valid for enrollment."

---

## Phase 7: Integration & Navigation

**Purpose**: Ensure all components integrate and routes work end-to-end

- [X] T043 Update Pass detail page in `resources/js/pages/passes/show.tsx` to add "Share Pass" button linking to distribution panel (uses route helper from Wayfinder)
- [X] T044 Update PassPolicy in `app/Policies/PassPolicy.php` to add viewAny() gate for listing links (minimal: user is authenticated)
- [X] T045 Created distribution-links page component in `resources/js/pages/passes/distribution-links.tsx` for rendering DistributionPanel with pass context
- [X] T046 Create test RoutingTest in `tests/Feature/PassDistribution/RoutingTest.php` covering all named routes resolve correctly with Wayfinder
- [X] T047 [P] Create test WayfinderRouteTest in `tests/Feature/PassDistribution/WayfinderRouteTest.php` covering Wayfinder helpers generate correct URLs (passes.show-by-link, passes.distribution-links.index, etc.)

---

## Phase 8: Documentation & Deployment

**Purpose**: Documentation, testing, and deployment readiness

- [x] T048 Run full feature test suite in terminal: `php artisan test tests/Feature/PassDistribution/` and verify all tests pass (GATE: 100% pass rate required)
- [x] T049 [P] Format PHP code with Pint: `./vendor/bin/pint app/Http/Controllers/PassDistributionController.php app/Models/PassDistributionLink.php app/Services/PassDistributionLinkService.php`
- [x] T050 [P] Format React/TypeScript code with Prettier: `npm run format resources/js/pages/PassLink.tsx resources/js/pages/Passes/DistributionPanel.tsx resources/js/components/QRCodeDisplay.tsx`
- [x] T051 Create migration checklist document in `specs/001-pass-distribution/DEPLOYMENT_CHECKLIST.md` listing pre-deployment verification steps
- [x] T052 Create user documentation in `specs/001-pass-distribution/USER_GUIDE.md` explaining how to use pass distribution links (issuer perspective)
- [x] T053 Test database migration in fresh database: `php artisan migrate:fresh && php artisan migrate` and verify table structure matches schema
- [x] T054 [P] Test factory-generated data: `php artisan tinker` and create sample links via PassDistributionLinkFactory
- [x] T055 Test routes in browser: verify /p/{slug} accessible without auth, /dashboard/passes/{pass}/distribution-links requires auth
- [ ] T056 [P] Verify access tracking: open pass link 3 times, check last_accessed_at and accessed_count updated in database
- [ ] T057 Verify device detection: test from iOS Safari, Chrome Android, Desktop Chrome and confirm correct add-to-wallet action shown
- [ ] T058 [P] Verify QR code: scan generated QR code and confirm lands on correct pass link
- [ ] T059 Verify expiry messaging: set pass status to expired, open link, confirm message displayed
- [ ] T060 Verify link control: disable link, confirm 403 response; re-enable, confirm link works
- [x] T061 Build frontend: `npm run build` and verify no errors or warnings
- [ ] T062 [P] Commit feature branch: `git add -A && git commit -m "feat: pass distribution system (US1-US3)"`
- [ ] T063 [P] Create pull request from `001-pass-distribution` to `main` with description referencing spec and linking to test results
- [ ] T064 Code review checklist: Constitution compliance, test coverage, type safety, no N+1 queries, proper authorization scoping
- [ ] T065 Merge and deploy to production: `git merge --squash && git push origin main`

---

## Dependencies & Execution Order

### User Story Dependencies

```
Setup (T001-T010)
    ↓
Foundational (T005-T010) [blocks all user stories]
    ↓
┌─ US1 (T011-T019) — P1 — Core Feature [⭐ MVP scope]
│       ↓
│   ┌─ US2 (T020-T026) — P2 — QR Distribution [depends on US1]
│   │   
│   └─ US3 (T027-T037) — P3 — Link Control [depends on T006, T008]
│
└─ Edge Cases (T038-T042) [depends on US1]
    ↓
Integration (T043-T047)
    ↓
Documentation & Deployment (T048-T065)
```

### Parallel Execution Opportunities

**Phase 1 (Setup)**: All tasks independent; T001-T004 can run in parallel

**Phase 2 (Foundational)**: T005-T010 mostly independent except:
- T005, T006 depend on T008 (PassPolicy gates definition)
- Can run T005, T006, T007, T009, T010 in parallel; T008 first

**Phase 3 (US1)**: T011-T012 (tests) parallel; T013-T019 sequential based on dependencies
- T011, T012 can run in parallel
- T013, T017 first (controller + routes)
- T014, T015 parallel (device detection + React page)
- T016, T018, T019 follow

**Phase 4 (US2)**: T020-T021 (tests) parallel; T022-T026 sequential
- T020, T021 parallel
- T023 (npm install) first
- T022, T024 parallel
- T025, T026 follow

**Phase 5 (US3)**: T027-T028 (tests) parallel; T029-T037 sequential
- T027, T028 parallel
- T029-T030 (controller methods) first
- T031, T032 (service methods) parallel
- T033-T037 follow

**Phase 8 (Documentation)**: T049-T050, T054, T056, T057-T060 parallelizable

### Critical Path

```
T001 → T002 → T004 → T008 → T013 → T017 → T023 → T029 → T048 → T065
```

### Suggested Sprint Organization

**Sprint 1 (Setup & Foundation)**: T001-T010
- Duration: 2 days
- Deliverable: Database, models, authorization framework in place

**Sprint 2 (US1 - MVP)**: T011-T019
- Duration: 3 days
- Deliverable: Issuers can create and distribute pass links, users see device-appropriate actions
- **Can ship to production** with this scope

**Sprint 3 (US2 & US3)**: T020-T037
- Duration: 3 days
- Deliverable: QR codes and link management dashboard
- **Feature complete**

**Sprint 4 (Polish & Deploy)**: T038-T065
- Duration: 2 days
- Deliverable: Edge case handling, documentation, production deployment

---

## MVP Scope (Minimum Viable Product)

**Recommended for first release**: Phase 1 + Phase 2 + Phase 3 (US1 only)

Tasks: T001-T019

**Delivers**:
- Unique, shareable links for passes in format `/p/{slug}`
- Automatic device detection (iOS → Apple Wallet, Android → Google Pay, Desktop → both)
- Access tracking (last_accessed_at, accessed_count)
- Fully tested (7 feature tests)

**Estimated Effort**: 5 days  
**Risk Level**: Low (no external dependencies, isolated feature)  
**User Value**: High (solves critical distribution gap)

---

## Validation Checklist

- [ ] All tasks have explicit file paths
- [ ] All [US#] labels correctly reference user stories
- [ ] All [P] markers only on parallelizable tasks
- [ ] No orphaned tasks or dependencies
- [ ] Test tasks come before implementation for each story (TDD principle)
- [ ] Deployment is last phase after all testing complete
- [ ] Code formatting tasks (T049-T050) before commit (T062)
- [ ] Constitution compliance tasks embedded in code review (T064)

---

## How to Execute

1. **Start with Phase 1**: `T001` - `T004` (database, model, factory)
   ```bash
   php artisan make:migration create_pass_distribution_links_table
   php artisan make:model PassDistributionLink
   php artisan make:factory PassDistributionLinkFactory
   ```

2. **Move to Phase 2**: `T005` - `T010` (authorization, validation, API)
   ```bash
   php artisan make:request StorePassDistributionLinkRequest
   php artisan make:controller PassDistributionController
   ```

3. **Execute US1 (MVP)**: `T011` - `T019` (tests, then implementation)
   ```bash
   php artisan test tests/Feature/PassDistribution/CreatePassDistributionLinkTest
   ```

4. **Run minimal test suite after each phase**:
   ```bash
   php artisan test tests/Feature/PassDistribution/ --filter="PassDistribution"
   ```

5. **Before merge, run full deployment checklist**: `T048` - `T065`

---

**Status**: ✅ Ready for development  
**Next**: Begin Phase 1 with T001
