# Feature Specification: Pass Validation & Scanning

**Feature Branch**: `001-pass-validation-scanning`  
**Created**: 2026-02-21  
**Status**: Draft  
**Input**: User description: "Pass Validation & Scanning
- **Gap Section**: §4 Pass Acceptance & Validation
- **Priority**: Critical (#2)
- **Why**: Passes can't be verified or redeemed at point of sale without scanning
- **Scope**: Barcode/QR scanning endpoint, web-based validation, single-use coupon redemption, multi-use loyalty scanning, auto-void after redemption, custom redemption messages"

## User Scenarios & Testing *(mandatory)*

## Clarifications

### Session 2026-02-21
- Q: How do merchants/staff authenticate to the web-based scanning interface? → A: A unique, long-lived "Scanner URL" or token is generated per location/tenant that doesn't require a full user login.
- Q: What data is encoded in the pass's QR/barcode to identify it during scanning? → A: A cryptographically signed token (e.g., JWT) containing the Pass ID to prevent forgery.
- Q: What should happen if a staff member scans a valid pass that belongs to a different tenant/merchant? → A: Display a generic "Invalid Pass" error to avoid leaking information.
- Q: How should the web scanner handle temporary loss of internet connection? → A: Show an "Offline - Cannot Validate" error immediately (strict validation).

### User Story 1 - Web-based Pass Scanning & Validation (Priority: P1)

Merchants and staff need a web-based interface accessed via a unique, long-lived Scanner URL to scan customer passes (via device camera or manual entry) to verify their authenticity and current status.

**Why this priority**: Core functionality; without the ability to read and validate the pass payload, no further actions (like redemption) can occur.

**Independent Test**: Can be fully tested by presenting a valid pass QR code to the web scanner and verifying that the system correctly identifies the pass and displays its current status.

**Acceptance Scenarios**:

1. **Given** a valid, active pass, **When** the merchant scans the QR/barcode, **Then** the system displays the pass details and a "Valid" status.
2. **Given** an expired or voided pass, **When** the merchant scans it, **Then** the system displays an "Invalid" or "Voided" status with the reason.
3. **Given** a barcode from an unrecognized system, **When** the merchant scans it, **Then** the system displays a "Pass Not Found" error.

---

### User Story 2 - Single-Use Coupon Redemption (Priority: P1)

Merchants need to redeem single-use coupons so that they cannot be used again by the customer, preventing fraud and double-dipping.

**Why this priority**: Critical for business value; single-use offers must be strictly enforced to prevent financial loss.

**Independent Test**: Can be fully tested by scanning a single-use coupon, clicking "Redeem", and verifying that a subsequent scan of the same coupon is rejected.

**Acceptance Scenarios**:

1. **Given** a valid single-use coupon pass, **When** the merchant scans and confirms redemption, **Then** the system marks the pass as redeemed, displays a success message, and auto-voids the pass to prevent future use.
2. **Given** an already redeemed coupon, **When** the merchant scans it, **Then** the system shows it was already redeemed, displays the time of original redemption, and prevents a second redemption.

---

### User Story 3 - Multi-Use Loyalty Scanning (Priority: P2)

Merchants need to scan multi-use loyalty passes to record a visit or transaction without voiding the pass, allowing the customer to continue using it.

**Why this priority**: Important for retention programs, but secondary to the strict security requirements of single-use coupons.

**Independent Test**: Can be fully tested by scanning a loyalty pass multiple times and verifying that each scan is recorded while the pass remains active.

**Acceptance Scenarios**:

1. **Given** a valid loyalty pass, **When** the merchant scans and logs a visit, **Then** the system records the scan event, displays a success message, and keeps the pass active for future use.

---

### User Story 4 - Custom Redemption Messages (Priority: P3)

Merchants need to see specific instructions or custom messages upon successful scan/redemption to know exactly what product, discount, or service to provide the customer.

**Why this priority**: Enhances the operational experience but is not strictly required for the technical validation of the pass.

**Independent Test**: Can be fully tested by configuring a custom message on a pass template, redeeming a pass of that type, and verifying the message appears on the merchant's screen.

**Acceptance Scenarios**:

1. **Given** a pass with a custom redemption message configured, **When** the merchant successfully scans or redeems it, **Then** the system prominently displays the custom message on the validation screen.

---

### Edge Cases

- **Cross-Tenant Scanning**: If a staff member scans a valid pass belonging to a different tenant/merchant, the system displays a generic "Invalid Pass" error to avoid leaking information.
- **Concurrent Redemptions**: The system handles concurrent redemption attempts for the same single-use coupon (e.g., scanned simultaneously at two registers) by implementing database locks (FR-007).
- **Offline Scanning**: If the scanner device loses internet connection during the validation process, the system shows an "Offline - Cannot Validate" error immediately (strict validation).
- **Camera Failure**: If the system handles poorly lit or damaged screens that prevent camera scanning, it falls back to manual code entry (FR-008).

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST provide a secure, web-based scanning interface accessible via standard mobile and desktop browsers.
- **FR-002**: System MUST expose a secure integration service for validating pass payloads (QR/barcode data) that can be consumed by the web scanner or third-party POS systems.
- **FR-003**: System MUST differentiate between single-use (e.g., coupons) and multi-use (e.g., loyalty cards) pass types during the validation process.
- **FR-004**: System MUST automatically void single-use passes immediately upon successful redemption.
- **FR-005**: System MUST record an immutable audit trail of all scan and redemption events, including timestamp, result, and merchant/device context.
- **FR-006**: System MUST allow administrators to configure custom redemption messages per pass template or individual pass.
- **FR-007**: System MUST implement concurrency controls (e.g., database locks) to prevent duplicate redemptions of single-use passes.
- **FR-008**: System MUST provide a manual entry fallback for pass validation when camera scanning fails.
- **FR-009**: System MUST generate a unique, long-lived "Scanner URL" or token per location/tenant to allow staff access to the web-based scanning interface without requiring a full user login.
- **FR-010**: System MUST encode a cryptographically signed token (e.g., JWT) containing the Pass ID in the QR/barcode payload to prevent forgery and enumeration.
- **FR-011**: System MUST display a generic "Invalid Pass" error when a staff member scans a valid pass belonging to a different tenant/merchant to prevent information leakage.
- **FR-012**: System MUST show an "Offline - Cannot Validate" error immediately if the scanner device loses internet connection during the validation process, enforcing strict validation.

### Assumptions & Dependencies

- **Assumption**: Merchants have internet-connected devices (smartphones, tablets, or computers) capable of running a modern web browser.
- **Assumption**: The system relies on standard QR code and barcode formats supported by common web-based scanning libraries.
- **Dependency**: Requires the existing pass generation and distribution system to embed verifiable payloads in the barcodes.

### Key Entities *(include if feature involves data)*

- **Pass**: Represents the digital pass. Needs attributes to track its lifecycle state (e.g., active, redeemed, voided), its usage type (single vs. multi-use), any custom redemption messages, and the cryptographically signed token payload.
- **ScanEvent / RedemptionRecord**: Represents a single instance of a pass being scanned or redeemed. Tracks the pass ID, timestamp, outcome (success/failure/already_redeemed), and the context of the scan (user/merchant performing the scan).

## Constitution Check *(mandatory)*

- Confirm Laravel-first approach (Eloquent, Form Requests, policies, Inertia).
- Confirm Wayfinder routes are used (no hardcoded URLs).
- Confirm tests will be added/updated and the minimal test run is identified.
- Confirm authorization and tenant scoping are explicit.
- Confirm heavy work is queued and N+1 risks are addressed.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Merchants can successfully scan and validate a pass in under 2 seconds on a standard 4G/LTE mobile connection.
- **SC-002**: 100% of single-use coupons are successfully voided and prevented from secondary use after initial redemption, with zero double-redemptions allowed.
- **SC-003**: The web-based scanner successfully decodes at least 95% of presented, undamaged QR codes and barcodes on the first attempt.
- **SC-004**: The system can handle 500 concurrent scan/validation requests per minute without degrading response times beyond 3 seconds.
