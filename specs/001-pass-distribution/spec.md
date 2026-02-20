# Feature Specification: Pass Distribution System

**Feature Branch**: `001-pass-distribution`  
**Created**: February 12, 2026  
**Status**: Draft  
**Input**: User description: "Pass distribution system (Pass URL + device detection + QR)"

## Clarifications

### Session 2026-02-12

- Q: Device detection approach (server-side vs client-side) → A: Hybrid (server detects via User-Agent header, JavaScript enhances for accuracy on client side)
- Q: Pass link URL format → A: `/p/{slug}`
- Q: QR code generation approach → A: JavaScript-based (client-side, e.g., QRCode.js)
- Q: Behavior when pass expires while link exists → A: Link still works but displays expiry message to user

## User Scenarios & Testing *(mandatory)*

<!--
  IMPORTANT: User stories should be PRIORITIZED as user journeys ordered by importance.
  Each user story/journey must be INDEPENDENTLY TESTABLE - meaning if you implement just ONE of them,
  you should still have a viable MVP (Minimum Viable Product) that delivers value.
  
  Assign priorities (P1, P2, P3, etc.) to each story, where P1 is the most critical.
  Think of each story as a standalone slice of functionality that can be:
  - Developed independently
  - Tested independently
  - Deployed independently
  - Demonstrated to users independently
-->

### User Story 1 - Shareable Pass Link (Priority: P1)

As a pass issuer, I want a unique, shareable link for a pass so I can distribute it to end users without manual file handling.

**Why this priority**: This is the core distribution path and unlocks real-world usage.

**Independent Test**: Create a pass, generate its link, open it on a supported device, and verify the correct add-to-wallet action is offered.

**Acceptance Scenarios**:

1. **Given** an active pass, **When** a user opens its link on an iOS device, **Then** the page presents the Apple add-to-wallet action for that pass.
2. **Given** an active pass, **When** a user opens its link on an Android device, **Then** the page presents the Google add-to-wallet action for that pass.

---

### User Story 2 - QR Code Distribution (Priority: P2)

As a pass issuer, I want a QR code for the pass link so I can distribute passes in print or on screens.

**Why this priority**: QR distribution enables offline and physical channel distribution.

**Independent Test**: Generate a QR code for a pass link and confirm a scan opens the same link.

**Acceptance Scenarios**:

1. **Given** an active pass with a shareable link, **When** a QR code for that link is scanned, **Then** the scanner opens the pass link successfully.

---

### User Story 3 - Link Control (Priority: P3)

As a pass issuer, I want to disable or re-enable a pass link so I can control distribution if a link is shared incorrectly.

**Why this priority**: Basic control reduces risk and support burden.

**Independent Test**: Disable a pass link, verify it no longer grants access, then re-enable it.

**Acceptance Scenarios**:

1. **Given** a disabled pass link, **When** a user opens it, **Then** they see a clear message that the link is unavailable.

---

### Edge Cases

<!--
  ACTION REQUIRED: The content in this section represents placeholders.
  Fill them out with the right edge cases.
-->

- Link opened on an unsupported or unknown device type.
- Link opened for a pass that has been expired or voided.
- QR code scanned from a low-quality print or low-light environment.
- Link shared publicly and opened by a large number of users in a short time.

## Requirements *(mandatory)*

<!--
  ACTION REQUIRED: The content in this section represents placeholders.
  Fill them out with the right functional requirements.
-->

### Functional Requirements

- **FR-001**: System MUST generate a unique, unguessable, shareable link for each pass in the format `/p/{slug}`.
- **FR-002**: System MUST detect device type on initial link open using User-Agent header analysis (server-side), allowing JavaScript to enhance accuracy if needed.
- **FR-003**: System MUST present the appropriate add-to-wallet action (Apple Wallet for iOS, Google Pay for Android) based on detected device type.
- **FR-004**: System MUST provide a fallback view offering both Apple and Google options when device type cannot be definitively determined.
- **FR-005**: System MUST generate a QR code (client-side, JavaScript-based) that encodes the pass link.
- **FR-006**: System MUST allow issuers to disable and re-enable a pass link, controlling access without deleting the pass.
- **FR-007**: System MUST display a user-friendly message for disabled, expired, or invalid links, including expiry status if the pass has expired.
- **FR-008**: System MUST prevent link access to voided or deleted passes, displaying a descriptive error message.

### Key Entities *(include if feature involves data)*

- **PassDistributionLink**: Represents a shareable link tied to a specific pass. Attributes: `id`, `pass_id`, `slug` (unique, unguessable), `status` (active/disabled), `created_at`, `last_accessed_at`, `accessed_count`. Expires or displays status message if associated pass has expired or been voided.

## Assumptions

- Pass links are intended to be public but unguessable (UUID or similar slug generation required).
- A pass can be considered active or inactive based on its existing lifecycle status (issued, expired, voided).
- Device detection via User-Agent is primary; JavaScript enhances accuracy but is not required for core functionality.
- JavaScript-based QR code generation (e.g., QRCode.js) is performant enough for pass link URLs.
- Issuers decide how and where to share links and QR codes (off-platform responsibility).
- Expired passes retain their links but show expiry messaging to discourage new enrollment.

## Out of Scope

- Sending links via email, SMS, social platforms, or embedded widgets.
- Enrollment forms or data collection flows.
- One-time-use links and duplicate-detection logic beyond manual link disablement.

## Constitution Check *(mandatory)*

- Confirmed Laravel-first approach (Eloquent, Form Requests, policies, Inertia).
- Confirmed Wayfinder routes are used (no hardcoded URLs).
- Confirmed tests will be added or updated, with a minimal feature-focused test run identified.
- Confirmed authorization and tenant scoping are explicit.
- Confirmed heavy work is queued and N+1 risks are addressed.

## Success Criteria *(mandatory)*

<!--
  ACTION REQUIRED: Define measurable success criteria.
  These must be technology-agnostic and measurable.
-->

### Measurable Outcomes

- **SC-001**: 95% of pass link opens display a valid add-to-wallet option within 2 seconds.
- **SC-002**: 99% of QR code scans successfully open the associated pass link.
- **SC-003**: 90% of users who open a pass link on a supported device can add the pass on the first attempt.
- **SC-004**: Support inquiries about "how to get the pass" decrease by 30% within 60 days of release.
