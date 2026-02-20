# Feature Specification: Account Creation & Wallet Setup

**Feature Branch**: `002-account-wallet-setup`  
**Created**: February 13, 2026  
**Status**: Draft  
**Input**: User description: "Account Creation & Wallet Setup - Add guided onboarding flow for Apple & Google authentication, email validation, region/industry selection, and account tier progression system"

## Clarifications

### Session 2026-02-13

- Q: Can users have multiple Apple certificates/Google credentials? → A: Yes (Option B). Users can manage multiple certificates simultaneously for test/prod and key rotation scenarios.
- Q: Admin approval workflow for Production tier advancement? → A: Manual review (Option A). Users submit requests that go into admin queue; admins manually review and approve/reject with email notifications.
- Q: Account tier naming alignment with PassKit? → A: Keep custom names (Option A). Use: Email Verified → Verified & Configured → Production → Live (not PassKit's naming).
- Q: Email domain validation mechanism? → A: Whitelist-based (Option A). Maintain `business_domains` table; matching domains auto-approve; others go to approval queue.
- Q: Data region enforcement approach? → A: Single DB + region column (Option B). Add `region` column to users table; filter queries with `WHERE region = 'EU'` or `WHERE region = 'US'`.

---

## User Scenarios & Testing

### User Story 1 - Email Validation & Account Signup (Priority: P1)

New users need to create accounts with proper email domain validation. Business email addresses (company domains) are instantly activated, while consumer emails (Gmail, Yahoo, Outlook personal) require manual approval to prevent spam.

**Why this priority**: This is the foundational feature - without proper account creation with email domain validation, new users cannot access the platform or any wallet setup features.

**Independent Test**: A new user can sign up with a business email and immediately access the dashboard, while a user with a consumer email receives an approval message and cannot proceed until manually verified by an admin.

**Acceptance Scenarios**:

1. **Given** a new user visits the signup page, **When** they enter a business email (e.g., acme.com, stripe.com), **Then** the account is created and immediately activated without approval
2. **Given** a new user enters a consumer email (gmail.com, yahoo.com, outlook.com), **When** they attempt to sign up, **Then** they receive a message: "Personal email addresses require manual approval. We've sent a request to our team."
3. **Given** an admin sees a pending account approval, **When** they click "Approve", **Then** the user receives an email notification activating their account
4. **Given** a user attempts to access the dashboard before approval, **When** they log in, **Then** they see a "Pending Approval" message and cannot access any features

---

### User Story 2 - Apple Wallet Onboarding (Priority: P1)

Users need a guided step-by-step flow to set up Apple Wallet integration. This includes generating a Certificate Signing Request (CSR), uploading the Apple certificate, and validating the setup - without requiring manual config file edits.

**Why this priority**: Apple Wallet is the primary wallet platform. Users must be able to self-service the setup without contacting support or editing config files.

**Independent Test**: A user can complete the entire Apple Wallet setup flow (CSR generation → certificate upload → validation) from the UI and immediately issue test Apple passes.

**Acceptance Scenarios**:

1. **Given** a user completes email verification, **When** they visit Account Settings > Wallet Setup, **Then** they see an "Apple Wallet" section with a "Get Started" button
2. **Given** the user clicks "Get Started", **When** they complete the CSR generation step, **Then** they receive a `cert.certSigningRequest` file download with instructions to upload to Apple Developer Portal
3. **Given** the user returns with their Apple `.cer` file, **When** they upload it to the platform, **Then** the system validates the certificate and stores it securely
4. **Given** the certificate is successfully uploaded, **When** the user views the certificate details, **Then** they see: certificate status (Active), expiration date, and a "Renew" button for 30+ days before expiry
5. **Given** the user attempts to issue an Apple pass before setup, **When** they click "Issue Apple Pass", **Then** they see: "Apple Wallet not configured. Complete setup in Account Settings."
6. **Given** Apple Wallet is configured, **When** the user issues a pass, **Then** the pass is successfully created and they can test it on a device

---

### User Story 3 - Google Wallet Onboarding (Priority: P1)

Users need step-by-step guidance to set up Google Wallet integration by creating a Google Cloud project, enabling the Wallet API, and uploading a service account key - all from the UI.

**Why this priority**: Google Wallet is the second primary platform. Like Apple, users need self-service UI setup without manual config editing.

**Independent Test**: A user can complete the Google Wallet setup flow (project creation → API enablement → service account upload) from the UI and immediately issue test Google passes.

**Acceptance Scenarios**:

1. **Given** Apple Wallet is working, **When** the user scrolls down in Account Settings > Wallet Setup, **Then** they see a "Google Wallet" section with setup instructions
2. **Given** the user clicks "Start Google Wallet Setup", **When** they follow the 5-step guide, **Then** each step has a green checkmark as they complete it (Create GCP Project → Enable Wallet API → Create Service Account → Download JSON Key → Upload to PassKit)
3. **Given** the user uploads their Google service account JSON, **When** the system validates it, **Then** it checks: file is valid JSON, required fields present (type, project_id, private_key_id, etc.), and schema matches Google's format
4. **Given** the user returns to Wallet Setup after uploading, **When** they view Google Wallet details, **Then** they see: Issuer ID, credential status (Active), last rotated date, and a "Rotate Credentials" button
5. **Given** Google Wallet is configured, **When** the user issues a pass, **Then** the pass is successfully created with a Google Save URL

---

### User Story 4 - Account Tier Progression (Priority: P2)

Accounts should progress through tiers as they activate features. The tier system should be visible in the dashboard and unlock features based on account maturity (Email Verified → Certificates Added → Production Approved → Live).

**Why this priority**: Understanding account tier progression helps users know what's possible and what's next. This increases engagement and guides users toward production readiness.

**Independent Test**: A user can see their current account tier in the settings panel, understand the path to the next tier (next requirement), and automatically progress to the next tier when that requirement is met.

**Acceptance Scenarios**:

1. **Given** a user completes email verification, **When** they view Account Settings, **Then** they see: "Tier: Email Verified ✓" with a roadmap showing: "Add Apple Certificate → Add Google Credentials → Go Live"
2. **Given** the user uploads both Apple and Google certificates, **When** they view their tier again, **Then** it updates to: "Tier: Verified & Configured ✓"
3. **Given** a user is in the "Verified & Configured" tier, **When** they view the tier roadmap, **Then** they see: "Next Step: Request Production Status" with a button to submit for review
4. **Given** an admin approves production status, **When** the user logs in next, **Then** their tier updates to: "Tier: Production" and they see expanded limits (more passes, CSV import, API access)
5. **Given** a user is in Production tier, **When** they click "Go Live", **Then** they see: "Launch Confirmation" dialog and after confirmation, the tier becomes "Tier: Live" with a checkbox list of pre-launch verification items

---

### User Story 5 - Data Region & Industry Selection (Priority: P2)

During signup, users should select their data region (EU or US) and industry. These choices affect data storage location and provide tailored support/documentation.

**Why this priority**: GDPR compliance requires explicit data region choice. Industry selection enables better support and onboarding materials.

**Independent Test**: A user can select EU/US region and industry during signup, and their choice persists in Account Settings and affects API endpoint region.

**Acceptance Scenarios**:

1. **Given** a user fills the signup form, **When** they reach the "Account Settings" step, **Then** they see: "Data Region" (EU/US) and "Industry" (dropdown with 10+ options) fields
2. **Given** the user selects "EU" region, **When** they create their account, **Then** their account data is stored in the EU region (`pub1.pskt.io` equivalent)
3. **Given** the user selects an industry (e.g., "Retail"), **When** they complete signup, **Then** they receive a welcome email with industry-specific documentation links
4. **Given** a user views Account Settings, **When** they look at "Data Region", **Then** they see: their selected region and a note: "Cannot be changed after account creation" to prevent data migration issues
5. **Given** a user with EU region selected, **When** they make API calls, **Then** the API responses reference the EU region in the x-region header

---

### User Story 6 - Certificate Renewal & Expiry Alerts (Priority: P2)

The system should track Apple certificate expiry dates and notify users when renewal is needed (30 days before, 7 days before, at expiry).

**Why this priority**: Expired certificates are a major support issue. Proactive notifications prevent pass issuance failures in production.

**Independent Test**: A user receives email notifications at 30 days, 7 days, and at expiry of their Apple certificate, and can renew from the UI with one click.

**Acceptance Scenarios**:

1. **Given** a non-expiring certificate is uploaded, **When** the system checks certificate dates, **Then** it calculates expiry date and stores it in the database
2. **Given** 30 days before expiry, **When** a daily cron job runs, **Then** the user receives an email: "Your Apple certificate expires in 30 days. Renew now to avoid service interruption."
3. **Given** the user views their certificate in Account Settings, **When** the expiry is within 30 days, **Then** the certificate row shows: yellow warning icon + "Expires in 21 days" text + "Renew Now" button
4. **Given** the user clicks "Renew Now", **When** they download a new CSR, **Then** they receive step-by-step instructions to upload the new certificate before the old one expires
5. **Given** a certificate expires and is no longer valid, **When** a user attempts to issue an Apple pass, **Then** they receive an error: "Apple certificate expired. Renew in Account Settings to continue."

---

### User Story 7 - Onboarding Wizard (Priority: P3)

New users should see a guided onboarding wizard on their first login that walks them through the critical setup steps in order.

**Why this priority**: Nice-to-have for UX improvement. Reduces support questions but not blocking if not implemented.

**Independent Test**: A new user completes the onboarding wizard and all required steps are checked off.

**Acceptance Scenarios**:

1. **Given** a new user logs in for the first time, **When** they land on the dashboard, **Then** a "Getting Started" wizard modal appears with 5 steps: Email Verified ✓ → Set Up Apple → Set Up Google → Add User Profile → Create First Pass
2. **Given** the user is viewing the wizard, **When** they click "Skip Tour", **Then** it closes and they can access the dashboard normally
3. **Given** the user completes each setup step (outside the wizard), **When** they return to the dashboard, **Then** the wizard automatically marks those steps as complete with a checkmark
4. **Given** all steps are complete, **When** the wizard detects this, **Then** it shows: "Setup Complete! You're ready to issue passes." with a "Dismiss" button

---

### Edge Cases

- What happens if a user's email domain verification service is down? → System should queue verification as a background job and allow signup to proceed with temporary "pending verification" status
- What if a user uploads an invalid or expired Apple certificate? → System validates before storing and returns: "Invalid certificate: [specific error - expired, wrong format, wrong type]"
- What if a user creates an account with EU region but later needs US region? → Currently blocked per requirements, but should display this clearly during signup
- What if a user's Google service account credentials are rotated externally? → System should validate on next API call, show error, and prompt re-upload
- What if an admin approves a production account but the user doesn't have both Apple and Google set up? → Should require both before production approval to prevent incomplete setup

---

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST validate email domains during signup using whitelist approach; emails matching `business_domains` table are auto-approved; all others queued for manual admin approval
- **FR-002**: System MUST queue consumer email accounts for manual admin approval before account activation; admins review requests and approve/reject via admin panel with email notification to user
- **FR-003**: System MUST provide a UI-based CSR generation tool for Apple Wallet setup without requiring server config files
- **FR-004**: System MUST accept and validate Apple `.cer` certificate uploads and store them securely (encrypted at rest); **users may have multiple certificates** for test/prod scenarios
- **FR-005**: System MUST provide step-by-step UI guidance for Google Wallet setup (GCP project creation, API enabling, service account JSON upload)
- **FR-006**: System MUST validate Google service account JSON files against Google's schema before accepting uploads; **users may have multiple Google credentials** for key rotation and multi-project support
- **FR-007**: System MUST track and display certificate expiry dates in the account settings dashboard
- **FR-008**: System MUST send email notifications 30 days, 7 days, and at the moment of certificate expiry
- **FR-009**: System MUST implement account tier progression: Email Verified → Verified & Configured → Production → Live
- **FR-010**: System MUST automatically advance account tier when requirements are met (e.g., both Apple and Google credentials present)
- **FR-011**: System MUST require explicit manual admin approval before advancing from Verified & Configured to Production tier; users submit request, admins manually review and approve/reject
- **FR-012**: System MUST display account tier and next-step roadmap in Account Settings
- **FR-013**: System MUST allow users to select data region (EU/US) during signup and persist this choice in `region` column on users table; enforce region on all queries via WHERE filtering
- **FR-014**: System MUST allow users to select industry (dropdown: Retail, Hospitality, Transportation, etc.) and tailor onboarding based on selection
- **FR-015**: System MUST prevent data region changes after account creation; region is immutable once set
- **FR-016**: System MUST show a "Getting Started" wizard on first login with checkboxes for critical setup steps
- **FR-017**: System MUST automatically mark wizard steps as complete based on stored account configuration

### Key Entities *(include if feature involves data)*

- **User**: Represents an account holder with email, tier, region (EU/US - immutable), industry, approval status, created_at. Has one-to-many relationships with AppleCertificate and GoogleCredential (supports multiple).
- **AppleCertificate** (multiple per user): Represents uploaded Apple certificate with path, password (encrypted), expiry_date, valid_from, created_at, renewable (false: current, true: renewal in progress). User can have multiple for test/prod scenarios.
- **GoogleCredential** (multiple per user): Represents Google service account JSON with issuer_id, private_key (encrypted), expiry_date, last_rotated_at, created_at. User can have multiple for key rotation and multi-project support.
- **AccountTier**: Represents user's progression level (Email_Verified, Verified_And_Configured, Production, Live) with unlock criteria and auto-advancement rules.
- **BusinessDomain**: Whitelist table with domain names (e.g., acme.com, stripe.com). Email signup checks if user's domain matches this table for auto-approval.
- **OnboardingStep**: Represents setup steps (Email Verified, Apple Setup, Google Setup, User Profile, First Pass) with completion status and timestamp.

## Constitution Check *(mandatory)*

✅ **Laravel-first**: Uses Eloquent models (User, AppleCertificate, GoogleCredential, BusinessDomain, AccountTier, OnboardingStep), Form Requests for validation (email domain, certificate upload), policies for authorization (users edit own account, admins approve tiers), Inertia for frontend

✅ **Database design**: Single PostgreSQL instance with `region` column on users table; all queries filtered by region via scopes. BusinessDomain table maintains whitelist of auto-approved domains.

✅ **Multiple certificates**: AppleCertificate and GoogleCredential are one-to-many with User; users can have multiple of each for test/prod and key rotation scenarios

✅ **Wayfinder routes**: All routes use named routes via Wayfinder (account.settings, account.certificates.create, account.certificates.upload, admin.approvals, etc.)

✅ **Testing**: Minimal test run: `php artisan test tests/Feature/AccountSetup/` covering signup with business/consumer emails, certificate upload (multiple), tier progression, manual approval

✅ **Authorization**: Users can only view/edit their own account and certificates via policies; admin-only actions (approve consumer emails, approve production tier, manage whitelist) are gated to authenticated admins

✅ **Queued work**: Email verification, domain whitelist checking, certificate validation, tier progression checks, expiry notifications should be queued as background jobs

✅ **N+1 prevention**: User queries should eager-load AppleCertificate, GoogleCredential, OnboardingSteps relationships

✅ **Region scoping**: All user/pass data queries include region filter to prevent cross-region data leakage

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: New users can complete account signup with email validation in under 2 minutes
- **SC-002**: Users can set up Apple Wallet from start (CSR generation) to finish (certificate uploaded) in under 5 minutes via UI
- **SC-003**: Users can set up Google Wallet from start (GCP project creation) to finish (service account upload) in under 8 minutes with provided instructions
- **SC-004**: 100% of account tier progressions occur automatically when requirements are met (both certificates uploaded → tier advances)
- **SC-005**: Certificate expiry notifications are delivered within 24 hours of each trigger (30 days, 7 days, 0 days remaining)
- **SC-006**: Support ticket volume related to certificate setup and account approval decreases by 80% post-launch
- **SC-007**: 95% of new users complete the onboarding wizard within their first week
- **SC-008**: Invalid certificate uploads are rejected with specific, actionable error messages 100% of the time
- **SC-009**: Account data storage location (EU/US) is correctly enforced and verifiable in API responses for 100% of accounts
- **SC-010**: Zero data region migration issues (region cannot be changed post-creation)

## Assumptions

1. **Email Verification**: Uses whitelist-based approach; `business_domains` table maintains list of auto-approved domains (company domains); can be extended with pattern matching if needed
2. **Admin Panel**: Assumes an admin panel exists or will be built for approving consumer emails and production tier requests (with email notification flow)
3. **Encryption**: Assumes Apple certificate passwords and Google service account private keys will be encrypted at rest using Laravel's encryption
4. **Background Jobs**: Assumes Laravel queue is available (Redis or database-backed) for async email notifications, expiry checks, and domain whitelist updates
5. **Apple Developer Account**: Assumes users will have their own Apple Developer account ($99/year enrollment by them)
6. **Google Cloud Project**: Assumes users will create their own GCP project and service account (no cost if free tier limits aren't exceeded)
7. **Certificate Lifespan**: Assumes Apple certificates are 1-year validity (industry standard) and should be renewed annually
8. **Multiple Certificates**: Users can maintain multiple Apple and Google credentials simultaneously for test/prod or key rotation scenarios
9. **Industry List**: Assumes a static list of ~15-20 industries is sufficient; can be expanded later
10. **Data Region**: Single PostgreSQL instance with region column on users table; all queries filtered via WHERE region = 'EU'/'US' scopes; region is immutable post-account-creation
11. **Onboarding Wizard**: Assumes wizard is optional and can be dismissed; users with existing setup skip wizard automatically
12. **Admin Approval**: Manual review process; admins have dedicated approval queue in admin panel with user details and certificate info visible for manual decision
