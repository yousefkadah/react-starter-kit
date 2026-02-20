# Implementation Plan: Account Creation & Wallet Setup

**Branch**: `002-account-wallet-setup` | **Date**: 2026-02-13 | **Spec**: [spec.md](spec.md)

## Summary

Build a guided onboarding system for new accounts with email domain validation (business/consumer approval workflow), step-by-step Apple and Google Wallet setup (CSR generation + certificate upload from the UI), account tier progression (Email Verified → Verified & Configured → Production → Live with manual admin approval for Production tier), optional first-login wizard, and region-based data scoping (EU/US). Users can maintain multiple Apple and Google credentials for test/prod and key rotation scenarios.

**Technical Approach**:
- **Database**: Single PostgreSQL instance; add `region` column to users table with scoped queries; new tables for AppleCertificate, GoogleCredential, BusinessDomain (whitelist), AccountTier, OnboardingStep
- **Backend**: Laravel 11 Eloquent models with one-to-many relationships (multiple certs per user), Form Requests for validation, Policies for authorization, background jobs for email/verification
- **Frontend**: React components for signup flow, account settings with certificate management (upload, renewal), tier roadmap display, onboarding wizard (dismissible)
- **Routing**: Wayfinder type-safe routes for all account endpoints; no hardcoded URLs
- **Testing**: PHPUnit feature tests for signup flows (business/consumer emails), certificate upload/validation, tier progression, admin approval

## Technical Context

**Language/Version**: PHP 8.3 / Laravel 11 (backend) + TypeScript / React 18 (frontend)  
**Primary Dependencies**: Laravel (Eloquent, Inertia, Form Requests, Authorization), React 18, Vite 7.3.1, Wayfinder 0.1.3 (type-safe routes)  
**Storage**: PostgreSQL (single instance with region column; new tables: users.region, AppleCertificate, GoogleCredential, BusinessDomain, AccountTier, OnboardingStep)  
**Testing**: PHPUnit (feature tests) + Pest (unit tests as needed)  
**Target Platform**: Web (Laravel + React monolith)  
**Project Type**: Web (single-repo, multi-file updates to app/, routes/, resources/, database/migrations/, tests/)  
**Performance Goals**: Signup < 2min, Apple setup < 5min, Google setup < 8min; cert expiry notifications within 24 hours  
**Constraints**: Type-safe routing (Wayfinder), no hardcoded URLs, all user data scoped by region, all changes test-backed, Form Requests for validation, Policies for authorization  
**Scale/Scope**: MVP for 100s of beta accounts; 7 user stories, 17 functional requirements, 10 success criteria

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

✅ **Laravel-First Architecture**: Uses Eloquent models (User [extended], AppleCertificate, GoogleCredential, BusinessDomain, AccountTier, OnboardingStep), Form Requests for signup/certificate validation, Policies for user/admin authorization, Inertia pages under resources/js/pages. No new dependencies or base folders. Encryption for sensitive data (cert passwords, private keys).

✅ **Type-Safe Routing & Inertia Navigation**: All routes named via Wayfinder (account.settings, account.certificates.*, admin.approvals.*). No hardcoded URLs. React components use route() helper from @/routes. Inertia Link for navigation.

✅ **Test-Backed Changes**: Every component/endpoint requires feature tests. Minimal test run: `php artisan test tests/Feature/AccountSetup/` covers signup (business/consumer), certificate upload/validation, tier progression, admin approval flows.

✅ **Security & Tenant Isolation**: User data accessed via Policies. All queries scoped by region via ScopedByRegion trait. Input validated via Form Requests. Sensitive data encrypted. Sanctum for API auth.

✅ **Performance & Reliability**: Email verification, domain checks, certificate validation, tier progression, expiry notifications queued as background jobs. Eager-load relationships (N+1 prevention). Storage paths logged and resilient.

---

## Project Structure

### Documentation (this feature)

```
specs/002-account-wallet-setup/
├── plan.md              # This file
├── research.md          # Phase 0 research findings (TO BE GENERATED)
├── data-model.md        # Phase 1 data model (TO BE GENERATED)
├── quickstart.md        # Phase 1 quickstart (TO BE GENERATED)
├── contracts/           # Phase 1 API contracts (TO BE GENERATED)
└── spec.md              # Feature specification (EXISTING)
```

### Source Code (repository root)

```
app/
├── Http/Controllers/
│   ├── AccountController.php (signup, account settings, tier display)
│   ├── CertificateController.php (CSR, cert upload, renewal)
│   └── AdminApprovalController.php (approval queue)
├── Http/Requests/
│   ├── SignupRequest.php
│   ├── UploadAppleCertificateRequest.php
│   ├── UploadGoogleCredentialRequest.php
│   └── ApproveAccountRequest.php
├── Models/
│   ├── User.php (EXTEND: region, tier, industry, approval_status)
│   ├── AppleCertificate.php (NEW)
│   ├── GoogleCredential.php (NEW)
│   ├── BusinessDomain.php (NEW)
│   ├── AccountTier.php (NEW)
│   └── OnboardingStep.php (NEW)
├── Policies/
│   ├── AppleCertificatePolicy.php
│   └── GoogleCredentialPolicy.php
├── Services/
│   ├── AppleCSRService.php
│   ├── GoogleCredentialService.php
│   ├── EmailDomainService.php
│   ├── TierProgressionService.php
│   └── ExpiryNotificationService.php
├── Jobs/
│   ├── ValidateEmailDomainJob.php
│   ├── CheckCertificateExpiryJob.php
│   ├── SendExpiryNotificationJob.php
│   └── AdvanceTierJob.php
└── Traits/
    └── ScopedByRegion.php

database/migrations/
├── [YYYY]_[MM]_[DD]_extend_users_table.php
├── [YYYY]_[MM]_[DD]_create_apple_certificates_table.php
├── [YYYY]_[MM]_[DD]_create_google_credentials_table.php
├── [YYYY]_[MM]_[DD]_create_business_domains_table.php
├── [YYYY]_[MM]_[DD]_create_account_tiers_table.php
└── [YYYY]_[MM]_[DD]_create_onboarding_steps_table.php

database/seeders/
└── BusinessDomainSeeder.php

tests/Feature/AccountSetup/
├── SignupBusinessEmailTest.php
├── SignupConsumerEmailTest.php
├── AppleCertificateUploadTest.php
├── GoogleCredentialUploadTest.php
├── TierProgressionTest.php
└── AdminApprovalTest.php

resources/js/pages/Auth/
└── Signup.tsx

resources/js/pages/Account/
├── Settings.tsx
├── SetupApple.tsx
├── SetupGoogle.tsx
└── TierRoadmap.tsx

resources/js/components/
├── CertificateCard.tsx
├── OnboardingWizard.tsx
├── TierBadge.tsx
└── AdminApprovalQueue.tsx
```

---

## Implementation Phases

### Phase 0: Research & Decision-Making (2-3 hours)

**Goal**: Resolve technical ambiguities and establish patterns.

**Research Tasks**:
1. Apple CSR Generation: PHP OpenSSL approach for generating Certificate Signing Requests
2. Google Service Account Validation: JSON schema validation patterns
3. Region Scoping Architecture: Global scope vs. trait-based filter approach
4. Email Domain Whitelist Caching: Cache TTL strategy for signup performance
5. Certificate Expiry Job Scheduling: Scheduler vs. queue approach
6. Admin Authorization Pattern: Route grouping & middleware strategy

**Deliverable**: `research.md` with decisions, rationales, code patterns, and decision trade-offs documented.

---

### Phase 1: Database Schema & Eloquent Models (6-8 hours)

**Goal**: Create persistent data layer with all models and relationships.

**Tasks (T101-T115)**:
- T101: Extend users table (add region, tier, industry, approval_status fields)
- T102-T106: Create migrations for certificates, credentials, whitelist, tiers, onboarding
- T107-T112: Create Eloquent models with relationships (one-to-many for certificates)
- T113-T115: Seed data, create factories, verify migrations work

**Test Commands**: `php artisan migrate && php artisan tinker` to verify relationships

**Deliverable**: All migrations run successfully, all Eloquent relationships work, factories generate valid test data.

---

### Phase 2: Email Validation & Admin Approval (8-10 hours)

**Goal**: Implement email domain whitelist checking and manual approval queue.

**Tasks (T201-T210)**:
- T201-T202: EmailDomainService (whitelist checking) + validation job
- T203-T204: SignupRequest Form validation + AccountController
- T205-T207: AdminApprovalController + Policy + mail notifications
- T208-T210: Wayfinder routes + feature tests

**Critical Business Logic**:
- Business domains (stripe.com, acme.com, etc.) → instant approval
- Consumer domains (gmail.com, yahoo.com) → approval queue
- Admin approves/rejects → user gets email notification

**Test Commands**: `php artisan test tests/Feature/AccountSetup/SignupEmailTest.php`

**Deliverable**: Email validation works, admin approval queue functional, emails sent.

---

### Phase 3: Frontend - Signup & Account Settings UI (12-14 hours)

**Goal**: Build React components for user-facing signup and settings pages.

**Tasks (T301-T311)**:
- T301: Signup.tsx (email, password, region, industry, country selection)
- T302-T310: Settings page, tier badge, certificate list, roadmap display
- T311: Tailwind styling, responsive mobile design

**Components**:
- Signup.tsx: Registration form with region/industry dropdowns
- AccountSettings.tsx: Main dashboard showing tier, certificates, approval status
- TierBadge.tsx: Visual tier progression indicator
- TierRoadmap.tsx: Tier roadmap with next steps
- CertificateCard.tsx: Reusable certificate display component
- OnboardingWizard.tsx: Dismissible first-login wizard

**Test Commands**: Component tests with React Testing Library

**Deliverable**: All pages render without errors, forms submit, navigation works with Wayfinder routes.

---

### Phase 4: Certificate Management (14-16 hours)

**Goal**: Implement CSR generation, certificate upload & validation for Apple & Google.

**Tasks (T401-T410)**:
- T401: AppleCSRService (CSR generation with PHP OpenSSL)
- T402-T403: Certificate validation services (for Apple .cer and Google JSON)
- T404: CertificateController (CSR generation, cert upload, Google setup endpoints)
- T405-T407: React UI for Apple setup (2-step wizard) and Google setup (5-step wizard)
- T408-T410: Routes, policies, feature tests

**Critical Business Logic**:
- CSR generation and download
- Certificate validation (format, expiry, signature verification)
- Multiple certificates per user (test/prod scenarios)
- Certificate renewal workflow

**Test Commands**: `php artisan test tests/Feature/AccountSetup/CertificateTest.php`

**Deliverable**: CSR downloads, cert upload validates, Google JSON parses, tier progression triggered on completion.

---

### Phase 5: Account Tier & Progression Logic (10-12 hours)

**Goal**: Implement automatic and manual tier progression system.

**Tasks (T501-T509)**:
- T501: TierProgressionService (auto-advance rules, manual approval)
- T502: TierProgressionJob (queued job for tier checks)
- T503-T504: ProductionApprovalController + Policy
- T505-T509: React components for tier roadmap, admin approval queue, feature tests

**Tier Progression Rules**:
- Email Verified: Auto-advance when email approved
- Verified & Configured: Auto-advance when both Apple + Google exist
- Production: Manual user request → admin review → approval (email notification)
- Live: User confirmation + pre-launch checklist

**Test Commands**: `php artisan test tests/Feature/AccountSetup/TierProgressionTest.php`

**Deliverable**: Tier progression logic correct, manual approval queued, emails sent, tier displays in dashboard.

---

### Phase 6: Background Jobs & Region Scoping (10-12 hours)

**Goal**: Implement certificate expiry tracking, notifications, and region data isolation.

**Tasks (T601-T612)**:
- T601-T603: CheckCertificateExpiryJob + SendExpiryNotificationJob (30/7/0 day notifications)
- T604-T605: ScopedByRegion trait + audit existing queries
- T606-T608: OnboardingStepTracker service + wizard state management
- T609-T612: Queue configuration, logging, feature tests

**Critical Background Work**:
- Daily scheduler job checks certificate expiry dates
- Sends emails 30 days, 7 days, 0 days before expiry
- All user queries filtered by region (EU/US) to prevent data leakage
- Onboarding wizard steps auto-complete based on user actions

**Test Commands**: `php artisan test tests/Feature/AccountSetup/JobsTest.php`

**Deliverable**: Jobs run, notifications sent within 24 hours, region scoping verified, wizard tracks progress.

---

### Phase 7: Testing, Documentation & Polish (12-14 hours)

**Goal**: Comprehensive testing, documentation, deployment readiness.

**Tasks (T701-T716)**:
- T701-T703: Feature tests (~15 tests), unit tests (services), integration test (full journey)
- T704: Code formatting with Laravel Pint
- T705-T708: API documentation, user guides, admin guides
- T709-T716: Database seeding, performance testing, security audit, manual QA, deployment checklist

**Test Target**: >80% code coverage, all tests passing, zero high-severity issues

**Test Commands**:
```bash
php artisan test tests/Feature/AccountSetup/ --coverage
./vendor/bin/pint app/ database/ routes/ tests/
```

**Deliverable**: Comprehensive test suite passing, documentation complete, code formatted, manual QA passed, deployment checklist ready.

---

## Timeline Summary

| Phase | Hours | Role | Critical Path |
|-------|-------|------|----------------|
| 0 | 2-3 | Research/Architect | Start |
| 1 | 6-8 | Backend | Phase 0 → 1 |
| 2 | 8-10 | Backend | Phase 1 → 2 |
| 3 | 12-14 | Frontend | Phase 2 → 3 |
| 4 | 14-16 | Full-Stack | Phase 3 → 4 |
| 5 | 10-12 | Backend | Phase 4 → 5 |
| 6 | 10-12 | Backend | Phase 5 → 6 |
| 7 | 12-14 | QA/Tester | Phase 6 → 7 |
| **Total** | **74-89** | **1-2 Engineers** | **Sequential** |

**Recommended Execution**: 
- **Timeline**: 2-3 weeks with 1-2 engineers
- **Parallelization**: Backend & frontend can work in parallel after Phase 2 completes
- **Suggested Teams**: 1 backend engineer (Phases 0-1, 2, 5-6) + 1 frontend engineer (Phases 3-4)

---

## Success Criteria & Gate Checkpoints

| Gate | Criteria | Owner | Status |
|------|----------|-------|--------|
| Phase 0 → 1 | Research.md complete, all decisions documented | Architect | - |
| Phase 1 → 2 | All migrations run, Tinker tests verify relationships | Backend | - |
| Phase 2 → 3 | Email validation tested, controllers passing | Backend | - |
| Phase 3 → 4 | Signup/settings pages render, forms functional | Frontend | - |
| Phase 4 → 5 | CSR/cert validation working, tier progression triggered | Full-Stack | - |
| Phase 5 → 6 | Tier logic correct, admin approval + emails functional | Backend | - |
| Phase 6 → 7 | Jobs running, region scoping verified on all queries | Backend | - |
| Phase 7 → Merge | Tests >80%, docs complete, Pint formatting clean | QA | - |

---

## Key Risks & Mitigation

| Risk | Likelihood | Impact | Mitigation |
|------|-----------|--------|-----------|
| Apple CSR generation fails | Low | High | Research PHP OpenSSL early (Phase 0), have fallback: pre-generated template |
| Google JSON validation incomplete | Medium | Medium | Validate against real Google samples, iterate based on feedback |
| Region scoping breaks existing queries | Medium | High | Audit all queries in Phase 1, test with multiple regions, use global scope carefully |
| Tier progression race conditions | Low | Medium | Use database transactions, queue jobs, test concurrent tier checks |
| Email notifications delayed | Medium | Low | Monitor queue, log failures, add manual "Resend" button in admin |
| Admin approval bottleneck | Low | Medium | Set SLA (24h), monitor queue, escalate delays |

---

## Notes & Assumptions

- **Region Immutability**: Users cannot change region post-signup (per clarification) to avoid data migration
- **Multiple Certificates**: Supported for test/prod and key rotation (one-to-many relationships)
- **Manual Production Approval**: Admin explicitly approves (not automatic)
- **Email Whitelist Caching**: 1-hour TTL to reduce DB load on signup
- **Onboarding Wizard**: Dismissible; auto-completes based on user actions
- **Certificate Encryption**: Passwords/keys encrypted using Laravel Crypt
- **No Third-Party Pass Tools**: Pass generation remains in-house (PHP OpenSSL)
- **Backward Compatibility**: Users table updates are additive; no data loss

---

**Plan Status**: ✅ Complete. Ready for Phase 0 initiation and sequential phase execution per timeline.
