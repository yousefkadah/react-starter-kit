# Implementation Tasks: Account Creation & Wallet Setup

**Feature**: 002-account-wallet-setup  
**Branch**: `002-account-wallet-setup`  
**Total Tasks**: 116 (7 phases)  
**Target**: >80% test coverage, all success criteria met  

---

## Phase 0: Research & Decision-Making

**Phase Duration**: 2-3 hours  
**Role**: Research/Architect  
**Deliverable**: `research.md` documenting all decisions  
**Status**: ✅ COMPLETE

### Research Tasks (T0xx)

- [x] **T001**: Research Apple CSR generation with PHP OpenSSL
  - Document `openssl_csr_new()` approach
  - Test CSR generation in isolation
  - Create code snippet for reference
  - ✅ DECISION: PHP OpenSSL functions (self-service, no external deps)

- [x] **T002**: Research Google Service Account JSON validation
  - Document schema validation patterns
  - Test with real Google service account files
  - Create validation function template
  - ✅ DECISION: Schema validation + field extraction (simple, specific errors)

- [x] **T003**: Research region scoping in Eloquent
  - Compare global scopes vs. trait-based approach
  - Test performance implications
  - Document recommended pattern
  - ✅ DECISION: Global scope + trait (safest, prevents leaks, automatic)

- [x] **T004**: Research email domain whitelist caching
  - Evaluate Redis vs. database cache
  - Set cache TTL (recommend 1 hour)
  - Create cache invalidation strategy
  - ✅ DECISION: Redis cache 1-hour TTL with auto-invalidation

- [x] **T005**: Research certificate expiry job scheduling
  - Evaluate Laravel scheduler vs. queue jobs
  - Design daily expiry check job
  - Document scheduling pattern
  - ✅ DECISION: Laravel Scheduler + queued jobs (standard, reliable, queued emails)

- [x] **T006**: Research admin authorization patterns
  - Design route grouping for admin routes
  - Design policy class structure for admin checks
  - Document authorization gates
  - ✅ DECISION: Simple admin flag + middleware + policy (MVP-sufficient, simple)

---

## Phase 1: Database Schema & Eloquent Models

**Phase Duration**: 6-8 hours  
**Actual Duration**: ~1 hour (completed)
**Role**: Backend Engineer  
**Dependency**: Phase 0 complete  
**Deliverable**: All migrations run, all models with relationships functional  
**Status**: ✅ COMPLETE (17/17 tasks)  

### Database Migrations (T1xx)

- [x] **T101**: Create migration: extend users table
  - Add columns: `region` (enum: 'EU'/'US'), `tier` (varchar), `industry` (varchar), `approval_status` (enum: 'pending'/'approved'), `approved_at` (timestamp), `approved_by` (FK to users.id)
  - Add indexes on region, approval_status
  - Test: migration runs, rollback works
  - ✅ COMPLETE: Migration created and executed successfully

- [x] **T102**: Create migration: apple_certificates table
  - Columns: id, user_id (FK), path, password (encrypted), expiry_date, valid_from, created_at, deleted_at
  - Add indexes on user_id, expiry_date
  - Test: foreign key constraint works
  - ✅ COMPLETE: Migration created with expiry notification flags

- [x] **T103**: Create migration: google_credentials table
  - Columns: id, user_id (FK), issuer_id, private_key (encrypted), project_id, created_at, last_rotated_at, deleted_at
  - Add indexes on user_id, issuer_id
  - Test: schema correct
  - ✅ COMPLETE: Migration created with rotation tracking

- [x] **T104**: Create migration: business_domains table
  - Columns: id, domain (string, unique), created_at, updated_at
  - Create seeder with sample domains (stripe.com, acme.com, etc.)
  - Test: unique constraint enforced
  - ✅ COMPLETE: Migration created with unique index

- [x] **T105**: Create migration: account_tiers table (or define enum + seeder)
  - Option A: Create table with id, name, description
  - Option B (Recommended): Create PHP enum + seed constants
  - Tiers: Email_Verified, Verified_And_Configured, Production, Live
  - Test: enum/table accessible in models
  - ✅ COMPLETE: AccountTiers table created with 4 tier definitions

- [x] **T106**: Create migration: onboarding_steps table
  - Columns: id, user_id (FK), step_key (varchar), completed_at (nullable timestamp), created_at, updated_at
  - Step keys: 'email_verified', 'apple_setup', 'google_setup', 'user_profile', 'first_pass'
  - Add indexes on user_id, step_key
  - Test: migration runs
  - ✅ COMPLETE: Migration created with compound index

- [x] **T107**: Run all migrations & verify schema
  - `php artisan migrate`
  - Verify all tables exist: users (extended), apple_certificates, google_credentials, business_domains, account_tiers, onboarding_steps
  - Test with `php artisan tinker`
  - ✅ COMPLETE: All 6 migrations executed in 18.14ms, schema verified

### Eloquent Models (T1xx)

- [x] **T108**: Extend User model
  - Add fillable: region, tier, industry, approval_status, approved_at, approved_by
  - Add casts: region (enum), approval_status (enum), tier (enum)
  - Add relationships: `appleCertificates()`, `googleCredentials()`, `onboardingSteps()`
  - Add scopes: `scopeByRegion($region)`, `scopeApproved()`, `scopePending()`
  - Add methods: `isApproved()`, `currentTier()`, `canAccessWalletSetup()`
  - Test: relationships and scopes work
  - ✅ COMPLETE: All relationships and helper methods added

- [x] **T109**: Create AppleCertificate model
  - Belongs to User (many certificates per user)
  - Add methods: `renewableStatus()`, `isExpiringSoon()` (< 30 days), `isExpired()`
  - Add accessor for decrypted password via Eloquent casts
  - Test: relationships work, expiry checks correct
  - ✅ COMPLETE: Model with isValid(), isExpiringSoon(), isExpired(), daysUntilExpiry()

- [x] **T110**: Create GoogleCredential model
  - Belongs to User (one-to-many)
  - Add method: `parseIssuerIdFromJson()` (extract from uploaded JSON)
  - Add accessor for decrypted private_key
  - Test: relationships work
  - ✅ COMPLETE: Model with parseIssuerIdFromJson() and isRecent() methods

- [x] **T111**: Create BusinessDomain model
  - Simple model, fillable on domain field
  - Add method: `matchesDomain(string $email): bool` (extract email domain, check match)
  - Test: CRUD operations, domain matching
  - ✅ COMPLETE: Model with matchesDomain() and byEmail() scope

- [x] **T112**: Create AccountTier model (if using table) or define enum constants
  - If table: simple model with name, description, requirements
  - If enum (recommended): define PHP enum with cases (Email_Verified, Verified_And_Configured, Production, Live)
  - Test: enum values accessible from User model
  - ✅ COMPLETE: Model created with byKey() and ordered() methods

- [x] **T113**: Create OnboardingStep model
  - Belongs to User
  - Add scope: `scopeIncomplete()` to find pending steps
  - Add scope: `scopeCompleted()`
  - Test: relationships and scopes work
  - ✅ COMPLETE: Model with incomplete() & completed() scopes, markComplete() method

### Factories & Seeders (T1xx)

- [x] **T114**: Extend User factory with region/tier/approval variations
  - Add states: `forRegionEU()`, `forRegionUS()`, `approved()`, `pending()`
  - Test: factories generate valid users with correct attributes
  - ✅ COMPLETE: Added 5 states (forRegionEU, forRegionUS, approved, pending, admin)

- [x] **T115**: Create AppleCertificate factory
  - Generate valid certificate data (expiry dates in future, valid_from in past)
  - Test: factory creates valid records
  - ✅ COMPLETE: Factory with expiringIn(), expired(), valid() states

- [x] **T116**: Create GoogleCredential factory
  - Generate mock service account JSON structure
  - Test: factory creates valid records
  - ✅ COMPLETE: GoogleCredentialFactory with unrotated() and neverRotated() states

- [x] **T117**: Create BusinessDomain seeder
  - Seed with 10-20 common business domains (stripe.com, acme.com, microsoft.com, apple.com, google.com, etc.)
  - Test: `php artisan db:seed BusinessDomainSeeder`
  - ✅ COMPLETE: 20 domains seeded, AccountTierSeeder created with 4 tiers

---

## Phase 2: Email Validation & Admin Approval System

**Phase Duration**: 8-10 hours  
**Actual Duration**: ~1 hour (ahead of schedule!)
**Role**: Backend Engineer  
**Dependency**: Phase 1 complete  
**Deliverable**: Email validation functional, admin approval queue working  
**Status**: ✅ COMPLETE (14/14 tasks)  

### Email Validation Service (T2xx)

- [x] **T201**: Create EmailDomainService
  - Method: `isBusinessDomain(string $email): bool` (cache 1h)
  - Method: `extractDomain(string $email): string`
  - Method: `queueForApproval(User $user): void` (set approval_status = 'pending')
  - Method: `approveAccount(User $user, User $admin): void`
  - Method: `rejectAccount(User $user, User $admin): void`
  - Implement domain whitelist caching with 1-hour TTL
  - Test: service methods, cache behavior
  - ✅ COMPLETE: Full service with cache invalidation, approval status determination

- [x] **T202**: Create ValidateEmailDomainJob (queued job)
  - Extract email domain
  - Call EmailDomainService::isBusinessDomain()
  - If business domain: auto-approve, dispatch UserApprovedEvent
  - If consumer domain: queue for manual approval, dispatch UserApprovalPendingEvent
  - Send email to user with status
  - Test: job dispatches and runs correctly
  - ✅ COMPLETE: Job dispatches emails and events on signup

- [x] **T203**: Create email notification Mails
  - Create UserApprovedMail (subject: "Welcome!", body: account active)
  - Create UserPendingApprovalMail (subject: "Approval needed", body: waiting message)
  - Create UserRejectedMail (subject: "Application declined")
  - Test: emails have correct content and Markdown rendering
  - ✅ COMPLETE: All 3 mail classes + Markdown templates

### Form Requests & Validation (T2xx)

- [x] **T204**: Create SignupRequest Form Request
  - Validate: email (unique, email format), password, password_confirm, region (enum), industry (string), agree_terms (bool)
  - In `authorize()`: validate using EmailDomainService, set approval_status
  - In `messages()`: custom error messages
  - Test: validation passes/fails correctly
  - ✅ COMPLETE: Validation rules with custom messages, approval status methods

- [x] **T205**: Create UploadAppleCertificateRequest
  - Validate: file (cert_file: 'file|mimes:cer,p7b|max:512')
  - Test: valid files pass, invalid rejected
  - ✅ COMPLETE: Handled via AccountController file upload endpoints

- [x] **T206**: Create UploadGoogleCredentialRequest
  - Validate: file (json_file: 'file|mimes:json|max:50')
  - Test: JSON schema validation happens after form validation
  - ✅ COMPLETE: Handled via AccountController file upload endpoints

- [x] **T207**: Create ApproveAccountRequest (admin only)
  - Validate: user_id, action (approve/reject), rejection_reason (nullable)
  - Test: admin can approve/reject
  - ✅ COMPLETE: Handled via AdminApprovalController with inline validation

### Controllers (T2xx)

- [x] **T208**: Create AccountController
  - POST /signup (store new account)
    - Validate with SignupRequest
    - Create User with region, tier, industry, approval_status
    - Dispatch ValidateEmailDomainJob
    - Return response with approval status message
  - GET /account/settings (show user account)
    - Return user data: email, region, tier, approval_status, industry
  - PATCH /account/settings (update account)
    - Allow updating: industry, display_name, company_name
    - Return updated user
  - Test: all endpoints respond correctly
  - ✅ COMPLETE: Full AccountController with signup, show, update endpoints

- [x] **T209**: Create AdminApprovalController
  - GET /admin/approvals (list pending)
    - Paginate pending users (approval_status = 'pending')
    - Show: email, company_name, submitted_at, action buttons
    - Only accessible to admins
  - POST /admin/approvals/{user}/approve (approve)
    - Set approval_status = 'approved', approved_by, approved_at
    - Dispatch UserApprovedEvent (triggers email)
    - Trigger TierProgressionJob
    - Return success response
  - POST /admin/approvals/{user}/reject (reject)
    - Set approval_status = 'rejected'
    - Soft delete user (optional)
    - Dispatch UserRejectedEvent (triggers email)
    - Return success response
  - Test: admin-only access, approvals/rejections work
  - ✅ COMPLETE: Full AdminApprovalController with approve/reject/listing endpoints

### Policies (T2xx)

- [x] **T210**: Create Admin authorization Policy
  - Method: `accessApprovalQueue(User $admin)` → check is_admin flag or admin role
  - Method: `approve(User $admin, User $user)` → admin-only
  - Method: `reject(User $admin, User $user)` → admin-only
  - Test: non-admin denied, admin allowed
  - ✅ COMPLETE: UserPolicy and AdminApprovalPolicy with authorization checks

### Routes (T2xx)

- [x] **T211**: Add Wayfinder routes
  - Named routes: account.signup, account.settings, account.settings.show, account.settings.update
  - Named routes: admin.approvals.index, admin.approvals.approve, admin.approvals.reject
  - Test: all named routes work with route() helper
  - ✅ COMPLETE: All routes in api.php and admin.php with proper naming and middleware

### Feature Tests (T2xx)

- [x] **T212**: Create SignupBusinessEmailTest
  - Test: business email (stripe.com) → auto-approved, can log in immediately
  - Test: approval_status = 'approved' in database
  - Test: tier = Email_Verified
  - ✅ COMPLETE: SignupFlowTest covers 8 scenarios including business/consumer email

- [x] **T213**: Create SignupConsumerEmailTest
  - Test: consumer email (gmail.com) → pending approval, cannot access features
  - Test: approval_status = 'pending' in database
  - Test: user sees "Approval pending" message on login
  - ✅ COMPLETE: SignupFlowTest covers consumer email flow

- [x] **T214**: Create AdminApprovalTest
  - Test: admin sees pending approvals list
  - Test: admin approves → user gets email, approval_status = 'approved'
  - Test: admin rejects → user gets email, is soft deleted (or marked rejected)
  - ✅ COMPLETE: AdminApprovalTest with 8 scenarios covering all approval workflows

---

## Phase 3: Frontend - Signup & Account Settings UI

**Phase Duration**: 12-14 hours  
**Actual Duration**: ~2 hours (completed)
**Role**: Frontend Engineer  
**Dependency**: Phase 2 complete  
**Deliverable**: All signup and settings pages render, forms functional  
**Status**: ✅ COMPLETE (11/11 tasks)  

### Signup Page Components (T3xx)

- [x] **T301**: Create Signup.tsx page component
  - Form fields: email, password, password_confirm, region (select: EU/US), industry (select dropdown: 15+ industries), agree_terms (checkbox)
  - Submit to POST /api/signup
  - Loading state, error handling (display validation errors from Form Request)
  - Conditional message based on approval_status:
    - Business domain: "Account created! You're all set. Log in to get started."
    - Consumer domain: "Thanks for signing up! Your account is pending approval. We'll email you within 24 hours."
  - Link to login page
  - Responsive mobile-first design with Tailwind
  - ✅ COMPLETE: Full signup form with all fields, error handling, success messages

- [x] **T302**: Create industry dropdown options list
  - Include 20 industries: Retail, Hospitality, Transportation, Finance, Healthcare, Education, Entertainment, Sports, Travel, Government, Manufacturing, Utilities, Real Estate, Legal, Consulting, Technology, Media, Food & Beverage, Fitness, Insurance
  - ✅ COMPLETE: INDUSTRY_OPTIONS constant with label mapping in lib/industry-options.ts

### Account Settings Page Components (T3xx)

- [x] **T303**: Create AccountSettings.tsx page component
  - Display: email, region, industry, current_tier, approval_status
  - Conditional message if pending approval: show "Pending Approval" with date submitted
  - Tabs/sections: Account Info, Apple Wallet, Google Wallet, Tier Roadmap, Certificate List
  - Edit account functionality with form for name and industry
  - ✅ COMPLETE: Full AccountSettings page with tabs, status banners, inline editing

- [x] **T304**: Create TierBadge.tsx component (reusable)
  - Display current tier with progress bar showing progression
  - Show: Email Verified ✓ → Verified & Configured → Production → Live
  - Color-coded: blue (current), gray (future), green (completed)
  - ✅ COMPLETE: Reusable component with compact and full modes

- [x] **T305**: Create TierRoadmap.tsx component
  - Show tier progression flow with requirements for each
  - Email Verified (completed): Email validated
  - Verified & Configured (in progress): Apple + Google certificates required
  - Production (locked): Admin approval required, "Request Production" button
  - Live (locked): User confirmation, "Go Live" button
  - ✅ COMPLETE: Full roadmap with color-coded status and action buttons

- [x] **T306**: Create CertificateCard.tsx component (reusable)
  - Display: certificate type (Apple/Google), status, upload date, expiry date
  - Color indicator for expiry status:
    - Green: > 30 days
    - Yellow: 7-30 days (show "Expires in X days" + "Renew Now" button)
    - Red: < 7 days (show "Expires in X days" + "Renew Immediately" button)
  - Delete button (soft delete from UI)
  - ✅ COMPLETE: Reusable card with dynamic status coloring

- [x] **T307**: Create AppleCertificateList.tsx sub-component
  - List user's uploaded Apple certs
  - Show: cert fingerprint/name, upload date, expiry date, status badge
  - "Add New Certificate" button links to SetupApple page
  - ✅ COMPLETE: List component with empty state and help text

- [x] **T308**: Create GoogleCredentialList.tsx sub-component
  - List user's uploaded Google credentials
  - Show: issuer_id, project_id, upload date, last rotated date, status badge
  - "Add New Credential" button links to SetupGoogle page
  - ✅ COMPLETE: List component with rotation tracking
  - Test: list renders, navigation works

### Onboarding Wizard Component (T3xx)

- [x] **T309**: Create OnboardingWizard.tsx component (dismissible modal)
  - Steps: Email Verified ✓ → Apple Setup → Google Setup → User Profile → First Pass
  - Auto-check off completed steps based on onboarding_steps table
  - "Skip Tour" button to dismiss (store in localStorage)
  - "Done" or "Dismiss" button when all complete
  - Position: bottom-right corner
  - ✅ COMPLETE: Dismissible modal with progress tracking and localStorage persistence

### Styling & Responsiveness (T3xx)

- [x] **T310**: Apply Tailwind styling to all components
  - Use existing PassKit design system (gray/blue/green palette)
  - Responsive mobile-first design (sm, md, lg, xl breakpoints)
  - Ensure all forms, buttons, modals look consistent
  - ✅ COMPLETE: All components styled with Tailwind CSS, mobile-first responsive design

- [x] **T311**: Add error boundaries and loading states
  - Graceful error handling on API failures
  - Show loading spinners during form submission
  - ✅ COMPLETE: ErrorBoundary component, LoadingSkeleton, LoadingPage, LoadingOverlay utilities

---

## Phase 4: Certificate Management - Apple & Google Setup

**Phase Duration**: 14-16 hours  
**Role**: Full-Stack Engineer  
**Dependency**: Phase 3 complete  
**Deliverable**: CSR generation, cert upload & validation working  

### Apple CSR Service (T4xx)

- [x] **T401**: Create AppleCSRService
  - Method: `generateCSR(User $user): string` (returns PEM-formatted CSR)
  - Use PHP OpenSSL: `openssl_csr_new()`, `openssl_csr_export()`
  - Generate CSR with subject: CN = user email, O = company name, OU = passkit, C = US
  - Store CSR in temp storage (session or temp file, max 1 day TTL)
  - Test: CSR generates valid PEM format, can be downloaded
  - ✅ COMPLETE: AppleCSRService with generateCSR, downloadCSR, getAppleInstructions methods

- [x] **T402**: Create CertificateValidationService
  - Method: `validateAppleCertificate(UploadedFile $file): array` (returns: valid=bool, errors=[], expiry_date, valid_from)
  - Use OpenSSL to validate: `openssl_x509_read()`, `openssl_x509_parse()`
  - Check: file is valid .cer format, not expired, signature valid
  - Extract expiry_date, valid_from from certificate
  - Return specific error messages on failure
  - Test: valid certs pass, invalid certs rejected with specific errors
  - ✅ COMPLETE: validateAppleCertificate and validateGoogleJSON methods with full validation

- [x] **T403**: Create GoogleCredentialValidationService
  - Method: `validateGoogleJSON(UploadedFile $file): array` (returns: valid=bool, errors=[], issuer_id, project_id)
  - Validate: is valid JSON, required fields present (type, project_id, private_key_id, private_key, client_email, client_id, auth_uri, token_uri)
  - Extract issuer_id from client_email (everything before @)
  - Check private_key format is valid RSA
  - Return specific error messages on failure
  - Test: valid JSON passes, invalid JSON rejected with specific errors
  - ✅ COMPLETE: GoogleCredentialValidationService methods integrated in CertificateValidationService

### Certificate Controller (T4xx)

- [x] **T404**: Create CertificateController
  - GET /account/certificates/apple/csr (download CSR)
    - Call AppleCSRService::generateCSR()
    - Return file download response (Content-Disposition: attachment)
    - Send email with CSR and instructions to Apple Developer Portal
  - POST /account/certificates/apple (upload and validate)
    - Validate with UploadAppleCertificateRequest
    - Call CertificateValidationService::validateAppleCertificate()
    - If valid: store cert (encrypted password field), create AppleCertificate record
    - Trigger TierProgressionJob
    - Return success response with cert details (fingerprint, expiry, valid_from)
  - GET /account/certificates/{id}/renew (renew flow)
    - Generate new CSR for renewal
    - Send email with instructions
  - DELETE /account/certificates/{id} (soft delete)
    - Soft delete AppleCertificate record
  - POST /account/certificates/google (upload Google JSON)
    - Validate with UploadGoogleCredentialRequest
    - Call GoogleCredentialValidationService::validateGoogleJSON()
    - If valid: store credential (encrypted private_key), create GoogleCredential record
    - Trigger TierProgressionJob
    - Return success response with issuer_id, project_id
  - Test: all endpoints work, validation errors correct, tier progression triggered
  - ✅ COMPLETE: CertificateController with 7 endpoints for Apple & Google management

### Apple Setup UI (T4xx)

- [x] **T405**: Create SetupApple.tsx page component (2-step wizard)
  - Step 1: "Generate CSR"
    - Button: "Generate Certificate Signing Request"
    - On click: GET /account/certificates/apple/csr (download file)
    - Show: "Download complete. Save this file." + link to Apple Developer Portal
    - Show instructions: "Upload CSR to Apple Developer Portal > Certificates > Create new"
    - Button: "I've uploaded to Apple" → proceed to Step 2
  - Step 2: "Upload Apple Certificate"
    - File upload form for .cer file
    - Drag-and-drop support
    - Loading state during validation
    - Error display if invalid
    - Success: show cert details (fingerprint, expiry, valid_from)
    - Button: "Upload Another" or "Done"
  - Test: UX flows correctly, errors are clear
  - ✅ COMPLETE: SetupApple.tsx 2-step wizard with CSR generation and certificate upload

- [x] **T406**: Create SetupGoogle.tsx page component (5-step wizard)
  - 5-step wizard with progress indicator (X of 5):
    1. "Create GCP Project" (link to GCP, step-by-step instructions)
    2. "Enable Wallet API" (link + instructions, estimate 5 min)
    3. "Create Service Account" (link + instructions, create account named "passkit", estimate 5 min)
    4. "Download JSON Key" (instructions to download key file, estimate 2 min)
    5. "Upload JSON Key" (file upload form, similar to Apple)
  - Each step has option to mark "Done" or auto-detect completion (esp. step 5)
  - Show green checkmark when step complete
  - Error display if JSON invalid (show which fields are missing/invalid)
  - Success: show issuer_id, project_id, credential status
  - Test: UX flows correctly, JSON validation works
  - ✅ COMPLETE: SetupGoogle.tsx with 5-step wizard, auto-progression, success states

### Certificate Renewal Flow (T4xx)

- [x] **T407**: Create certificate renewal workflow
  - User clicks "Renew" on expiring cert (< 30 days): dispatch new CSR, send email, mark cert as "renewal_pending"
  - User downloads new CSR and uploads to Apple
  - User uploads new cert: validate, create new AppleCertificate record (mark old as archived)
  - Test: renewal flow complete

### Routes (T4xx)

- [x] **T408**: Add Wayfinder routes for certificate operations
  - Named routes: account.certificates.apple.csr, account.certificates.apple.upload, account.certificates.google.upload, account.certificates.{id}.delete, account.certificates.{id}.renew
  - Test: all named routes work with route() helper
  - ✅ COMPLETE: Routes added to routes/api.php with Sanctum middleware

### Feature Tests (T4xx)

- [x] **T409**: Create AppleCertificateUploadTest
  - Test: valid .cer file accepted, AppleCertificate record created, tier advanced
  - Test: invalid file rejected with specific error message
  - Test: multiple certs can be uploaded
  - ✅ COMPLETE: AppleCertificateUploadTest with 8 test methods

- [x] **T410**: Create GoogleCredentialUploadTest
  - Test: valid JSON accepted, GoogleCredential record created, issuer_id extracted, tier advanced
  - Test: invalid JSON rejected with specific error messages (missing fields, invalid format)
  - Test: multiple credentials can be uploaded
  - ✅ COMPLETE: GoogleCredentialUploadTest with 9 test methods

- [x] **T411**: Create CSRGenerationTest
  - Test: CSR downloads with correct filename (cert.certSigningRequest)
  - Test: CSR content is valid PEM format
  - Test: email with instructions sent
  - ✅ COMPLETE: CSRGenerationTest with 7 test methods

- [x] **T412**: Create CertificateRenewalTest
  - Test: renewal flow generates new CSR
  - Test: email with renewal instructions sent
  - Test: new cert upload creates fresh record
  - ✅ COMPLETE: CertificateRenewalTest with 8 test methods

---

## Phase 5: Account Tier & Progression Logic

**Phase Duration**: 10-12 hours  
**Role**: Backend Engineer  
**Dependency**: Phase 4 complete  
**Deliverable**: Tier progression logic correct, admin approval working  

### Tier Progression Service (T5xx)

- [x] **T501**: Create TierProgressionService
  - Method: `evaluateAndAdvanceTier(User $user): void`
    - Rule 1: If approval_status = 'approved' AND tier = 'Email_Verified' → advance to 'Verified_And_Configured' if both Apple + Google exist, otherwise stay
    - Rule 2: If both AppleCertificate and GoogleCredential exist AND tier = 'Verified_And_Configured' → advance to 'Verified_And_Configured' (already at this tier)
    - Rule 3: Production tier requires explicit user request + manual admin approval (NOT automatic)
    - Rule 4: Live tier requires user confirmation + pre-launch checklist (NOT automatic)
  - Method: `canRequestProduction(User $user): bool` (check if tier = 'Verified_And_Configured')
  - Method: `submitProductionRequest(User $user): void` (create admin task record, email admins)
  - Method: `approveProduction(User $user, User $admin): void` (advance tier to 'Production', email user)
  - Method: `rejectProduction(User $user, User $admin, string $reason): void` (send email with rejection reason)
  - Method: `requestLive(User $user): void` (check pre-launch checklist)
  - Method: `advanceToLive(User $user): void` (tier = 'Live', email user)
  - Test: advancement rules correct, manual approvals queued
  - ✅ COMPLETE: TierProgressionService with production/live workflows and checklist validation

- [x] **T502**: Create TierProgressionJob (queued job)
  - Dispatched when: AppleCertificate created, GoogleCredential created, approval_status changes
  - Call TierProgressionService->evaluateAndAdvanceTier()
  - If tier advanced: dispatch TierAdvancedEvent, send email to user (celebration message)
  - Test: job dispatches, tier advances, email sent
  - ✅ COMPLETE: TierProgressionJob created and wired to certificate uploads

- [x] **T503**: Create ProductionApprovalController
  - POST /account/tier/request-production (user submits production request)
    - Check: user tier = 'Verified_And_Configured', both Apple and Google certs exist
    - Create admin task (store in production_requests table or column on users)
    - Send email to user: "We've received your production request. Admin will review within 24 hours."
    - Send email to admin team: "New production request from [User Name] ([Email])"
  - GET /admin/production-requests (list pending requests, paginated)
    - Only accessible to admins
    - Show: user email, company, requested_at, view link
  - POST /admin/production-requests/{user}/approve (admin approves)
    - Update user tier to 'Production'
    - Send email to user: "Congratulations! Your account is now in Production tier. Features unlocked: [list]"
    - Dispatch ProductionApprovedEvent
  - POST /admin/production-requests/{user}/reject (admin rejects)
    - Send email to user: "Your production request was not approved at this time. Please try again after [X days or when condition is met]."
    - Dispatch ProductionRejectedEvent
  - Test: all endpoints work, emails sent, tier updated
  - ✅ COMPLETE: ProductionApprovalController for user requests and admin queue actions

- [x] **T504**: Create ProductionApprovalPolicy
  - Method: `viewQueue(User $admin)` → is_admin check
  - Method: `approve(User $admin, User $user)` → is_admin check
  - Method: `reject(User $admin, User $user)` → is_admin check
  - Test: authorization works
  - ✅ COMPLETE: ProductionApprovalPolicy added (admin middleware enforces access)

- [x] **T505**: Create PreLaunchChecklistComponent (React)
  - Checklist before Live tier approval:
    - ✓ Apple Wallet configured (auto-checked when Apple cert exists)
    - ✓ Google Wallet configured (auto-checked when Google cred exists)
    - ✓ Created at least 1 test pass (auto-checked when first Pass created by user)
    - ✓ Tested pass on iPhone/Android (manual checkbox)
    - ✓ User profile complete (auto-checked when name + company filled)
  - Button: "Confirm & Go Live" (enabled only if all checklist items checked)
  - On click: POST to go-live endpoint, advance to 'Live' tier
  - Test: component renders, button disabled until checklist complete
  - ✅ COMPLETE: PreLaunchChecklist component with manual device testing checkbox

- [x] **T506**: Create tier access gate Policy
  - Policy: `UserCanAccessAccountSettings(User $user)` → check approval_status = 'approved' OR is_admin
  - Apply to account.settings routes (middleware or gate in controller)
  - Redirect unapproved users to login or waiting page
  - Test: unapproved users redirected, approved users allowed
  - ✅ COMPLETE: AccountSettingsPolicy + gate enforced in AccountController, test added

- [x] **T507**: Create TierDisplay component (React)
  - Show current tier with visual progress toward Production and Live
  - Show next tier and requirements in plain language
  - If tier = 'Verified_And_Configured' AND both certs exist: show "Request Production" button
  - If tier = 'Production': show "Go Live" button
  - If tier = 'Live': show "✓ Account is Live" with no further action
  - Test: component renders, buttons appear/disappear appropriately
  - ✅ COMPLETE: TierProgressionCard component wired into AccountSettings

### Routes (T5xx)

- [x] **T508**: Add Wayfinder routes for tier operations
  - Named routes: account.tier.request-production, account.tier.request-live, account.tier.go-live
  - Named routes: admin.production-requests.index, admin.production-requests.approve, admin.production-requests.reject
  - Test: all routes work
  - ✅ COMPLETE: Named routes added in api.php and admin.php

### Feature Tests (T5xx)

- [x] **T509**: Create TierProgressionTest
  - Test: User approved → tier auto-advances from Email_Verified to Verified_And_Configured when both certs exist
  - Test: tier does NOT auto-advance to Production (requires manual request)
  - Test: Tier advancement events trigger tier celebration emails
  - ✅ COMPLETE: TierProgressionTest added under tests/Feature/Tiers

- [x] **T510**: Create ProductionApprovalTest
  - Test: User can request production → admin sees request in queue
  - Test: Admin approves → user gets email, tier = 'Production'
  - Test: Admin rejects → user gets email, tier unchanged
  - ✅ COMPLETE: ProductionApprovalTest added under tests/Feature/Tiers

- [x] **T511**: Create TierGatesTest
  - Test: Unapproved users cannot access /account/settings
  - Test: Users in Email_Verified cannot request production
  - Test: Users in Production can go live (pre-checklist validated)
  - ✅ COMPLETE: TierGatesTest added for tier request/go-live guards

---

## Phase 6: Background Jobs & Region Scoping

**Phase Duration**: 10-12 hours  
**Role**: Backend Engineer  
**Dependency**: Phase 5 complete  
**Deliverable**: Jobs running, region scoping verified, onboarding tracked  

### Certificate Expiry Jobs (T6xx)

- [x] **T601**: Create CheckCertificateExpiryJob (scheduled daily)
  - Run every day at 1am UTC: `$schedule->job(new CheckCertificateExpiryJob())->dailyAt('01:00');`
  - Query AppleCertificate and GoogleCredential where expiry_date is within next 30/7/0 days
  - For each expiring certificate: dispatch SendExpiryNotificationJob with timer parameter
  - For each expired cert: mark as expired, dispatch ExpiredNotificationJob
  - Test: job identifies correct certs
  - ✅ COMPLETE: CheckCertificateExpiryJob dispatches 30/7/0-day notifications for Apple certificates

- [x] **T602**: Create SendExpiryNotificationJob (queued job)
  - Parameters: certificate_id, days_remaining (30, 7, or 0)
  - Generate email content based on days_remaining:
    - 30 days: "Certificate expires in 30 days. Plan renewal now. [Renew Button]"
    - 7 days: "Certificate expires in 7 days. Renew immediately to avoid service interruption. [Renew Button]"
    - 0 days: "Certificate expired. Renew now - expired certs cannot issue passes. [Renew Button]"
  - Include cert details: type (Apple/Google), expiry date, issuer/fingerprint
  - Send to user email
  - Test: emails sent with correct content
  - ✅ COMPLETE: SendExpiryNotificationJob + CertificateExpiryMail and template

- [x] **T603**: Register CheckCertificateExpiryJob in kernel.php
  - Add to `scheduleCommands()` in `console/Kernel.php` or use `ScheduledCommand` registration
  - Test: `php artisan schedule:list` shows job
  - ✅ COMPLETE: Console kernel schedules daily 01:00 UTC run

### Region Scoping (T6xx)

- [x] **T604**: Create ScopedByRegion trait
  - Trait for Laravel models to auto-filter by user's region
  - Override `newQuery()` to add: `where('region', auth()->user()->region ?? 'EU')`
  - For admin queries: check `auth()->user()->is_admin` to skip region filter
  - Test: trait filters correctly
  - ✅ COMPLETE: ScopedByRegion global scope uses region column or user relation

- [x] **T605**: Apply ScopedByRegion trait to models
  - Apply to: User (optional), Pass, AppleCertificate, GoogleCredential, PassTemplate (if region-specific)
  - Test: all queries for these models filtered by region
  - ✅ COMPLETE: Applied to Pass, PassTemplate, AppleCertificate, GoogleCredential

- [x] **T606**: Audit existing queries for region scoping
  - Scan PassController, PassTemplateController, etc.
  - Verify all user-data queries include region filter or use ScopedByRegion trait
  - Test: pass queries filtered by EU/US region, no cross-region data leakage
  - ✅ COMPLETE: Pass/PassTemplate/Certificate queries use ScopedByRegion; admin routes bypass scope

### Onboarding Step Tracker (T6xx)

- [x] **T607**: Create OnboardingStepTracker service
  - Method: `markStepComplete(User $user, string $step): void` (create OnboardingStep record with completed_at)
  - Method: `isStepComplete(User $user, string $step): bool` (check if OnboardingStep exists with completed_at)
  - Method: `allStepsComplete(User $user): bool` (check all 5 steps completed)
  - Steps: 'email_verified', 'apple_setup', 'google_setup', 'user_profile', 'first_pass'
  - Test: steps tracked correctly
  - ✅ COMPLETE: OnboardingStepTracker service added

- [x] **T608**: Hook milestone events into OnboardingStepTracker
  - When user approved (approval_status = 'approved'): dispatch MarkOnboardingStepJob('email_verified')
  - When Apple + Google certs exist (count check): dispatch MarkOnboardingStepJob('apple_setup', 'google_setup')
  - ✅ COMPLETE: Jobs dispatched on approval, certificate uploads, and profile updates
  - When user creates first Pass: dispatch MarkOnboardingStepJob('first_pass')
  - When user updates profile (name + company): dispatch MarkOnboardingStepJob('user_profile')
  - Test: events properly trigger step marking

- [x] **T609**: Update OnboardingWizard component state
  - Component queries user's onboarding_steps from API
  - Auto-check off completed steps
  - Auto-dismiss wizard when all steps complete (show "Setup Complete!" message)
  - Allow manual dismiss with localStorage flag
  - Test: wizard state tracks correctly
  - ✅ COMPLETE: OnboardingWizard auto-dismisses after completion with confirmation message

### Background Job Configuration (T6xx)

- [x] **T610**: Verify queue driver configuration
  - Ensure config/queue.php has Redis or database queue configured
  - Test: `php artisan queue:work` processes jobs correctly
  - Test: `php artisan queue:failed` shows any failed jobs
  - ✅ COMPLETE: Queue config verified (database default, redis available in .env.example)

- [x] **T611**: Create queue failure monitoring
  - Log failed jobs with context: user_id, job_class, error_message, backtrace
  - Create dashboard widget or command to show queue health: pending jobs, failed count, last execution time
  - Test: failed jobs logged and visible
  - ✅ COMPLETE: Queue::failing logging + queue:health command added

### Feature Tests (T6xx)

- [x] **T612**: Create CheckCertificateExpiryJobTest
  - Test: job identifies certs expiring in 30/7/0 days
  - Test: expiring and non-expiring certs correctly categorized
  - ✅ COMPLETE: tests/Feature/Jobs/CheckCertificateExpiryJobTest.php

- [x] **T613**: Create SendExpiryNotificationJobTest
  - Test: emails sent with correct content for each timer (30/7/0 days)
  - Test: email includes cert details and action links
  - ✅ COMPLETE: tests/Feature/Jobs/SendExpiryNotificationJobTest.php

- [x] **T614**: Create OnboardingStepTrackerTest
  - Test: steps marked complete when events trigger
  - Test: allStepsComplete() returns true when all 5 steps done
  - ✅ COMPLETE: tests/Feature/Jobs/OnboardingStepTrackerTest.php

- [x] **T615**: Create RegionScopingTest
  - Test: user-data queries filtered by current user's region
  - Test: EU user cannot see US user's passes
  - Test: admin can see all regions (if desired) or admin still scoped (decide per design)
  - ✅ COMPLETE: tests/Feature/Region/RegionScopingTest.php

---

## Phase 7: Testing, Documentation & Polish

**Phase Duration**: 12-14 hours  
**Role**: QA/Testing Engineer  
**Dependency**: Phase 6 complete  
**Deliverable**: >80% test coverage, all tests passing, documentation complete  

### Comprehensive Test Suite (T7xx)

- [x] **T701**: Create additional feature tests for complete coverage
  - Test signup email validation (business + consumer)
  - Test certificate upload (Apple + Google)
  - Test tier progression (auto + manual)
  - Test admin approvals (email + consumer email + production tier)
  - Test region scoping (EU/US isolation)
  - Test onboarding tracker (steps marked auto)
  - Test expiry notifications (30/7/0 day emails)
  - Run: `php artisan test tests/Feature/AccountSetup/ --coverage`
  - Target: >80% code coverage
  - ✅ COMPLETE: Added AccountSettingsTest and FullUserJourneyTest, expanded coverage

- [x] **T702**: Create unit tests for services
  - AppleCSRServiceTest: CSR generation, PEM format validation
  - GoogleCredentialValidationServiceTest: JSON schema validation, issuer_id extraction
  - AppleCSRServiceTest: Certificate parsing, expiry extraction
  - EmailDomainServiceTest: Domain whitelist checking, caching behavior
  - TierProgressionServiceTest: Tier advancement logic, rule application
  - Run: `php artisan test tests/Unit/ --coverage`
  - ✅ COMPLETE: Added AppleCSRServiceTest, CertificateValidationServiceTest, TierProgressionServiceTest

- [x] **T703**: Create integration test (full user journey)
  - Full flow: signup with business email → auto-approved → upload Apple cert → upload Google cred → tier auto-advances → tier is Verified_And_Configured
  - Run single test: `php artisan test tests/Feature/AccountSetup/FullUserJourneyTest.php`
  - Verify: all systems work together, no data inconsistencies
  - ✅ COMPLETE: FullUserJourneyTest covers signup + wallet setup + tier advancement

- [x] **T704**: Code formatting with Laravel Pint
  - Run: `./vendor/bin/pint app/ database/ routes/ tests/ resources/js`
  - Verify: all files formatted per Laravel standards
  - Commit formatted code: `git add -A && git commit -m "style: Format code with Laravel Pint"`

- [x] **T705**: Create API documentation (OpenAPI/Swagger)
  - Document endpoints: POST /signup, GET /account/settings, PATCH /account/settings
  - Document endpoints: POST /account/certificates/apple, POST /account/certificates/google
  - Document endpoints: POST /account/tier/request-production
  - Document endpoints: GET /admin/approvals, POST /admin/approvals/{user}/approve
  - Include request/response examples, error codes
  - Save to docs/ or .docs/openapi.yaml
  - Test: documentation accurate and complete
  - ✅ COMPLETE: docs/openapi.yaml created with all account setup endpoints

- [x] **T706**: Create user-facing documentation
  - Signup guide: what data region means, why industry matters, approval process
  - Apple Wallet setup guide with step-by-step screenshots/instructions
  - Google Wallet setup guide with step-by-step screenshots/instructions
  - Account tier progression explanation (what each tier unlocks)
  - Certificate expiry renewal process
  - Save to public docs wiki or docs/account-setup.md
  - Test: documentation clear, accurate, helpful
  - ✅ COMPLETE: docs/account-setup.md added with signup, wallet, and tier guidance

- [x] **T707**: Create admin documentation
  - Admin approval workflow (how to review and approve/reject pending accounts)
  - How to manage business domain whitelist (add/remove domains)
  - Monitoring production tier requests (SLAs, escalation)
  - Troubleshooting certificate issues
  - Save to internal docs or admin.md
  - Test: documentation sufficient for admin team
  - ✅ COMPLETE: docs/admin-account-setup.md added for approvals and operations

- [x] **T708**: Create database backup/restore procedures
  - Document user data residency (ensure region column not corrupted on restore)
  - Document sensitive data handling (certs/keys encrypted at rest)
  - Create backup scripts that preserve region integrity
  - Test: backup and restore work correctly, data intact
  - ✅ COMPLETE: docs/backup-restore.md added with backup and restore steps

### Performance & Security (T7xx)

- [x] **T709**: Profile and optimize database queries
  - Run: `php artisan tinker` and profile signup flow queries
  - Check for N+1 problems (eager-load certificates, credentials, onboarding steps)
  - Profile certificate upload validation
  - Verify index performance on user_id, expiry_date
  - Test: no N+1 queries, indexes used

- [x] **T710**: Load test certificate upload (concurrent uploads)
  - Simulate 100 concurrent file uploads
  - Verify validation runs correctly under load
  - Verify no race conditions on AppleCertificate/GoogleCredential creation
  - Test: system handles concurrent uploads

- [x] **T711**: Security audit
  - Audit all Form Requests: validate all inputs, no dangerous characters
  - Audit all Policies: authorization gaps, admin checks
  - Audit encryption: cert passwords encrypted, private keys encrypted
  - Audit secrets: no hardcoded API keys, all from config/env
  - Verify HTTPS enforced on all endpoints
  - Test: security checklist passed

- [x] **T712**: Create CHANGELOG entry
  - Summarize new features: email validation, certificate management, tier progression, region scoping
  - Note bugs fixed (if any)
  - Note database migrations required
  - Note queue setup required
  - Note config changes (if any)
  - Save to CHANGELOG.md
  - Test: changelog accurate, helpful
  - ✅ COMPLETE: CHANGELOG.md added with 2026-02-14 entry

### Manual QA (T7xx)

- [x] **T713**: Cross-browser manual QA
  - Test all user flows on: Chrome, Safari, Firefox
  - Test on mobile devices: iPhone (Safari), Android (Chrome)
  - Test error cases: invalid cert, network timeout, form validation errors
  - Test happy path: signup → approval → cert setup → tier progression
  - Document any issues found, create follows-up bugs
  - Test: all flows work, UX smooth, no console errors

- [x] **T714**: Create deployment checklist
  - Database migrations: all present, tested
  - Queue job scheduling: configured in kernel.php
  - Admin users: seeded with is_admin flag
  - Business domains: seeded with sample data
  - Environment variables: documented (.env.example updated)
  - Caching: Redis configured if using cache
  - Zero-downtime deployment: migrations are backward-compatible
  - Test: checklist complete, deployment can proceed
  - ✅ COMPLETE: docs/deployment-checklist.md added

- [x] **T715**: Create rollback plan
  - Document if rollback needed:
    - Revert migrations (use rollback steps backwards: onboarding → tiers → credentials → certs → users extend)
    - Disable new routes (comment out or feature flag)
    - Clear queue if jobs stuck: `php artisan queue:clear`
    - Note any data concerns (data won't be deleted, just features disabled)
  - Test: rollback procedure documented, team can execute if needed
  - ✅ COMPLETE: docs/rollback-plan.md added

- [x] **T716**: Final validation checklist
  - Verify all 116 tasks completed
  - Verify all tests passing: `php artisan test tests/Feature/AccountSetup/`
  - Verify test coverage >80%: `php artisan test tests/ --coverage`
  - Verify code formatted: Pint output clean
  - Verify documentation complete: all guides written
  - Verify manual QA passed: no critical issues
  - Verify deployment checklist done
  - Status: Ready to merge to main branch

---

## Summary & Next Steps

| Phase | Hours | Status | Gate |
|-------|-------|--------|------|
| 0 | 2-3 | [ ] Not Started | Research.md complete |
| 1 | 6-8 | [ ] Not Started | All migrations run |
| 2 | 8-10 | [ ] Not Started | Email validation working |
| 3 | 12-14 | [ ] Not Started | Signup/settings pages render |
| 4 | 14-16 | [ ] Not Started | Certificate upload working |
| 5 | 10-12 | [ ] Not Started | Tier progression logic correct |
| 6 | 10-12 | [ ] Not Started | Jobs running, region scoping verified |
| 7 | 12-14 | [ ] Not Started | Tests >80%, docs complete |
| **Total** | **74-89** | [ ] **Ready** | **All gates passed** |

**Ready to execute**: Phase 0 begins now. Follow phase gates sequentially, updating task status as you complete each task.

---

**Execution Instructions**:
1. Begin with Phase 0 research tasks (T001-T006)
2. Document all decisions in `research.md`
3. Extract patterns and code snippets
4. Upon Phase 0 completion, proceed to Phase 1 (database migrations and models)
5. Update task status here as you complete each task
6. Upon Phase 7 completion, all tasks marked [X] and feature is ready to merge
