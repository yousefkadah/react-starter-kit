# Feature Specification: Push Notifications & Real-Time Pass Updates

**Feature Branch**: `001-push-pass-updates`  
**Created**: February 20, 2026  
**Status**: Draft  
**Input**: User description: "Push Notifications & Real-Time Pass Updates — Apple APNS push notifications, Google Wallet change notifications, real-time field updates via API, bulk updates, lock screen change messages, pull-to-refresh support"

---

## User Scenarios & Testing

### User Story 1 — Update a Single Pass Field and Push to Device (Priority: P1)

A business operator needs to update a customer's pass in real time — for example, changing a loyalty balance from 50 to 75 points — and have that change appear on the customer's phone within seconds, including a lock screen notification.

**Why this priority**: This is the foundational capability. Without the ability to update a pass and push the change, all other stories (bulk updates, change messages) are impossible. A pass platform that can't update passes after issuance has severely limited value.

**Independent Test**: Can be fully tested by updating a single pass's field via the dashboard or API, then verifying the updated pass is served to the device and a push notification is sent.

**Acceptance Scenarios**:

1. **Given** a pass has been installed on a customer's Apple device, **When** the operator updates a field value (e.g., loyalty balance) via the dashboard, **Then** the system regenerates the pass, sends an APNS push notification, and the customer's wallet refreshes to show the new value within 30 seconds.
2. **Given** a pass has been saved to a customer's Google Wallet, **When** the operator updates a field value via the dashboard, **Then** the system patches the Google Wallet object via the REST API, and the customer sees the updated value in their Google Wallet app.
3. **Given** the operator updates a field, **When** the push notification is sent, **Then** the system records the update timestamp, delivery status, and the fields that were changed.
4. **Given** the operator updates a field on a pass that exists on both Apple and Google platforms, **When** the update is submitted, **Then** both platforms receive the update and the dashboard shows the delivery status for each platform.
5. **Given** the push notification delivery fails (e.g., expired push token, invalid certificate), **When** the delivery failure is detected, **Then** the system logs the failure, marks the device registration as inactive, and notifies the operator of the delivery issue.

---

### User Story 2 — Apple Device Registration and Web Service Protocol (Priority: P1)

When a customer adds an Apple Wallet pass to their device, Apple Wallet contacts the server to register the device for future push updates. This registration stores the device's push token, enabling the server to notify Apple when pass content changes.

**Why this priority**: Co-equal with Story 1 — Apple push notifications fundamentally depend on the device registration protocol. Without this, there is no way to know which devices hold which passes.

**Independent Test**: Can be tested by installing a pass on an Apple device and verifying the device registration API receives and stores the device identifier and push token.

**Acceptance Scenarios**:

1. **Given** a pass includes a `webServiceURL` and `authenticationToken`, **When** a customer adds the pass to Apple Wallet, **Then** the device sends a registration request to the server with its `deviceLibraryIdentifier` and `pushToken`, and the server stores this registration.
2. **Given** a device is registered for a pass, **When** the customer removes the pass from their wallet, **Then** the device sends an unregistration request, and the server removes the device registration.
3. **Given** a device requests the list of updated passes since a given tag, **When** the server receives the request, **Then** it returns all serial numbers of passes that have been updated since that tag, along with a new tag.
4. **Given** a device requests the latest version of a pass, **When** the server receives the request with a valid authentication token, **Then** it returns the freshly generated `.pkpass` file.
5. **Given** a request arrives with an invalid or mismatched authentication token, **When** the server validates the request, **Then** it rejects the request with an unauthorized response.

---

### User Story 3 — Lock Screen Change Messages (Priority: P2)

When a pass field is updated, customers see a contextual notification message on their lock screen — for example, "You now have 75 points!" — rather than a generic "Pass Updated" notification.

**Why this priority**: Change messages dramatically improve user engagement. Without them, customers see no meaningful feedback when a pass updates, reducing the perceived value of wallet passes.

**Independent Test**: Can be tested by configuring a change message format on a field, updating that field's value, and verifying the device displays the formatted message on the lock screen.

**Acceptance Scenarios**:

1. **Given** the operator has configured a change message template on the "balance" field (e.g., "You now have %@ points!"), **When** the balance is updated from 50 to 75, **Then** the customer's Apple device shows "You now have 75 points!" on the lock screen.
2. **Given** a field has no change message configured, **When** the field value is updated, **Then** the pass updates silently without a lock screen notification.
3. **Given** the operator configures change messages on multiple fields, **When** multiple fields are updated in a single operation, **Then** each field with a change message generates its respective lock screen notification.
4. **Given** a Google Wallet pass has a field updated, **When** the Google Wallet object is patched, **Then** the user receives a system-generated change notification in Google Wallet (Google manages the notification text based on what changed).

---

### User Story 4 — Bulk Pass Updates (Priority: P2)

An operator needs to update all passes created from a specific template at once — for example, changing the promotion text on all 5,000 active coupon passes, or updating the store hours across all store card passes.

**Why this priority**: Without bulk updates, operators must update passes one at a time, which is impractical for businesses with hundreds or thousands of active passes. This is a major usability requirement.

**Independent Test**: Can be tested by creating multiple passes from the same template, then updating a field at the template level and verifying all child passes are updated and push notifications are sent.

**Acceptance Scenarios**:

1. **Given** 500 active passes exist for a template, **When** the operator triggers a bulk update on a field (e.g., change promotion text), **Then** the system queues the updates and sends push notifications to all registered devices, processing in the background without blocking the dashboard.
2. **Given** a bulk update is in progress, **When** the operator views the dashboard, **Then** they see a progress indicator showing how many passes have been updated out of the total, and the estimated completion time.
3. **Given** a bulk update is running, **When** some individual push deliveries fail, **Then** the system retries failed deliveries (up to 3 attempts with exponential backoff) and reports the final success/failure counts.
4. **Given** the operator selects specific filters (e.g., only passes in "active" status, or only passes for a specific platform), **When** the bulk update is submitted, **Then** only matching passes are updated.
5. **Given** a bulk update affecting thousands of passes, **When** the update is processed, **Then** the system rate-limits push notifications to avoid exceeding platform limits while completing all updates within a reasonable timeframe.

---

### User Story 5 — Pull-to-Refresh Support (Priority: P3)

When a customer manually pulls to refresh their pass in Apple Wallet, the wallet contacts the server to check for an updated version of the pass, even if no push notification was sent.

**Why this priority**: This is a complementary mechanism to push. It ensures customers can get the latest pass data even if a push notification was missed or if the device was offline during the push.

**Independent Test**: Can be tested by updating a pass on the server, then performing a pull-to-refresh on a device and verifying the pass updates without an explicit push.

**Acceptance Scenarios**:

1. **Given** a pass has been updated on the server, **When** a customer pulls to refresh in Apple Wallet, **Then** the device requests the latest version of the pass, receives the updated `.pkpass` file, and displays the new content.
2. **Given** a pass has NOT been updated since the device's last known tag, **When** the device checks for updates, **Then** the server returns a "not modified" response, and the device does not re-download the pass.

---

### User Story 6 — Pass Update via API (Priority: P2)

External systems (CRMs, POS systems, custom apps) need to update pass fields programmatically through the API, triggering the same push notification flow as dashboard updates.

**Why this priority**: API-driven updates enable integrations and automation — the primary way most high-volume businesses will update passes.

**Independent Test**: Can be tested by sending a PATCH request to the pass update API endpoint with updated field values, and verifying the pass is regenerated and push sent.

**Acceptance Scenarios**:

1. **Given** a valid Sanctum API token or a valid HMAC-signed request with a pass serial number, **When** an external system sends a PATCH request with updated field values, **Then** the system updates the pass data, regenerates platform-specific files, sends push notifications to all registered devices, and returns a success response with the updated pass details.
2. **Given** the API request includes invalid field values (e.g., non-numeric value for a numeric field, text exceeding limits), **When** the server validates the request, **Then** it returns a validation error with specific details about which fields failed.
3. **Given** the API request targets a pass that belongs to a different user, **When** the server validates authorization, **Then** it rejects the request with a forbidden response.
4. **Given** the updated pass data would exceed the 10KB content size limit, **When** the server validates the update, **Then** it rejects the request with an error explaining the size constraint.
5. **Given** a server-to-server integration uses HMAC signature authentication, **When** it sends a request with a valid signature computed from the request body and a shared secret, **Then** the system authenticates and processes the request identically to a Sanctum-authenticated request.

---

### Edge Cases

- What happens when a device push token has expired or been revoked? The system marks the registration as inactive and removes it from future push attempts. APNS feedback is processed to clean up stale tokens.
- What happens when both Apple and Google versions of the same pass are updated simultaneously? Both platform updates are queued independently and processed in parallel. Failure on one platform does not block the other.
- What happens when the operator's Apple certificate has expired? The system prevents push attempts, displays a warning on the dashboard, and falls back to pull-to-refresh for pass updates. Certificate expiry is already tracked.
- What happens during a bulk update if the operator starts another bulk update on the same template? The system rejects ALL concurrent bulk updates on the same template (regardless of which field is targeted) while one is in progress, to prevent race conditions during `.pkpass` regeneration. The operator must wait for the current bulk update to complete before starting another.
- What happens if the server receives a device registration for a pass serial number that doesn't exist? The server returns a 401 Unauthorized response and does not create a registration.
- What happens when a voided pass receives an update request? The system rejects the update — voided passes cannot receive field updates or push notifications.
- What happens when a pass update is sent but no devices are registered? The update is saved to the database (so future pull-to-refresh returns the latest data), but no push notifications are sent. The dashboard shows the Update button enabled with a warning banner: "No devices registered — update will be saved but not pushed to any device." After submission, the dashboard indicates that 0 devices were notified.

---

## Requirements

### Functional Requirements

- **FR-001**: System MUST implement Apple's Web Service Protocol — 5 endpoints: device registration, device unregistration, list updated passes, get latest pass, and error logging.
- **FR-002**: System MUST store device registrations, including device library identifier, push token, pass type identifier, and serial number, with proper scoping to the pass owner.
- **FR-003**: System MUST generate and store a unique authentication token per pass, included in the signed `.pkpass` file's `authenticationToken` field.
- **FR-004**: System MUST include a `webServiceURL` in every generated Apple pass, pointing to the server's Web Service Protocol endpoints.
- **FR-005**: System MUST send empty push notifications to Apple's APNS service (HTTP/2, `api.push.apple.com:443`) using the pass owner's Apple certificate when a pass is updated.
- **FR-006**: System MUST update Google Wallet objects by patching the existing object via Google's REST API using the pass owner's service account credentials when a pass is updated.
- **FR-007**: System MUST support per-field change message templates (using `%@` placeholder for Apple and platform-appropriate format for Google) that generate lock screen notifications when the corresponding field value changes.
- **FR-008**: System MUST provide a pass update mechanism (both dashboard UI and API) that accepts partial field updates, validates them, stores the new values, and triggers platform-specific push/update flows.
- **FR-009**: System MUST support bulk updates — updating a specified field across all active passes of a given template, processed asynchronously via queued jobs.
- **FR-010**: System MUST track push notification delivery status per device per update — including sent, delivered, failed, and retried states.
- **FR-011**: System MUST retry failed push deliveries up to 3 times with exponential backoff (30 seconds, 2 minutes, 10 minutes).
- **FR-012**: System MUST enforce the 10KB content size limit on pass data and reject updates that would exceed it.
- **FR-013**: System MUST support pull-to-refresh by serving the latest generated `.pkpass` file when a device requests it, and tracking a modification tag to enable "not modified" responses.
- **FR-014**: System MUST validate all Web Service Protocol requests using the pass's authentication token, rejecting unauthorized requests.
- **FR-015**: System MUST clean up stale device registrations when Apple reports an invalid push token or when a device explicitly unregisters.
- **FR-016**: System MUST prevent updates to voided passes.
- **FR-017**: System MUST rate-limit bulk push notifications to 50 pushes per second per account (Apple APNS) to avoid exceeding platform-imposed sending limits. Google Wallet PATCH requests are limited to 3 push messages per object per day.
- **FR-018**: System MUST log all pass update events (who updated, what changed, when, delivery results) for audit and troubleshooting purposes.
- **FR-019**: System MUST enforce a per-template mutex on bulk updates — rejecting any new bulk update request on a template that already has a bulk update in progress, regardless of which field the new request targets.
- **FR-020**: System MUST retain pass update history (audit log of field changes, old/new values, delivery status) for 90 days. Records older than 90 days are pruned automatically; aggregate statistics may be preserved for analytics.
- **FR-021**: System MUST support two authentication methods for the pass update API: (1) Laravel Sanctum bearer tokens for interactive/mobile clients, and (2) HMAC-SHA256 signature authentication (using a shared secret and request body) for server-to-server integrations.
- **FR-022**: System MUST show the Update button enabled on passes with no registered devices, accompanied by a warning banner ("No devices registered — update saved, no push sent"). The update is persisted so pull-to-refresh will serve the latest data when a device eventually adds the pass.

### Key Entities

- **Device Registration**: Represents the association between a physical device and a pass. Stores the device library identifier (Apple assigns this per-device), push token (used to send APNS notifications), pass type identifier, serial number, and active/inactive status.
- **Pass Update**: Represents a change to a pass's field values. Stores the pass reference, the fields that changed, old and new values, who initiated the update, and the resulting push delivery status per device.
- **Change Message Template**: A per-field configuration that defines the lock screen notification text shown when that field's value changes. Uses `%@` as a placeholder for the new value on Apple.
- **Bulk Update Job**: Represents an asynchronous bulk operation that updates a specified field across multiple passes of a template. Tracks total count, processed count, failed count, and completion status.

---

## Clarifications

### Session 2026-02-20

- Q: What should the concrete rate limit be for bulk push operations? → A: 50 pushes/second per account (balanced throughput; 10K passes ≈ 3.5 min)
- Q: Should concurrent bulk updates on different fields of the same template be allowed? → A: No — block ALL concurrent bulk updates on the same template to prevent race conditions during pass regeneration
- Q: How long should pass update history (audit log) be retained? → A: 90 days, then auto-pruned (aggregate stats preserved for analytics)
- Q: Should the pass update API support HMAC signature auth in addition to Sanctum tokens? → A: Yes — support both Sanctum tokens (interactive clients) and HMAC-SHA256 signatures (server-to-server integrations) from the start
- Q: What happens when operator views a pass with no registered devices — disable Update button or show warning? → A: Show Update button enabled with warning banner ("No devices registered — update saved, no push sent")

---

## Assumptions

- **APNS authentication**: The same Apple pass type certificate (`.p12`) already stored per user in the `apple_certificates` table is used for both pass signing and APNS push authentication. No separate push certificate is required.
- **Google update mechanism**: Updating a Google Wallet object via the REST API PATCH endpoint automatically triggers Google's built-in change notification to the user. No separate push mechanism is needed for Google.
- **Push notification content**: Apple APNS push notifications for Wallet are empty (zero-length payload) — the notification tells Apple Wallet to pull the latest pass from the server. The actual content update is delivered via the pass download.
- **Rate limits**: Bulk push operations are rate-limited to 50 APNS pushes per second per account. Google limits partner-triggered push messages to 3 per day per object. At 50/sec, a bulk update of 10,000 passes completes push delivery in approximately 3.5 minutes.
- **Web Service URL**: A single configurable base URL is used for the Web Service Protocol endpoints (e.g., `https://app.example.com/api/apple/v1`). All passes for all users share the same base URL, with authentication tokens used for per-pass authorization.
- **Multi-tenant credential loading**: The Apple pass service and Google pass service will be updated to load user-specific credentials (from `apple_certificates` and `google_credentials` tables) rather than relying solely on global configuration. This is required since each user has their own Apple certificate and Google service account.
- **Pass content limit**: Apple enforces a 10KB limit on the pass JSON content (excluding images). The system validates updates against this limit.
- **Modification tag**: The system uses the pass's `updated_at` timestamp as the modification tag for Apple's "list updated passes" endpoint, rather than maintaining a separate versioning system.

---

## Constitution Check

- **Laravel-first approach**: Device registrations via Eloquent model, Form Request validation for pass update API, policies for authorization, Inertia for dashboard UI.
- **Wayfinder routes**: All frontend references to pass update endpoints will use Wayfinder-generated route functions — no hardcoded URLs.
- **Tests**: Feature tests for all 5 Apple Web Service Protocol endpoints, pass update API, bulk update jobs, APNS push sending (mocked), Google PATCH (mocked), device registration lifecycle, authentication token validation. Minimal test run: `php artisan test --filter=PushNotification`.
- **Authorization and tenant scoping**: Device registration endpoints validate authentication tokens per pass. Pass update endpoints enforce ownership via policies. The `ScopedByRegion` trait already applied to the Pass model ensures cross-tenant isolation.
- **Heavy work queued**: All push notification sending, pass regeneration, and bulk updates processed via queued jobs using Horizon. No push is sent synchronously.
- **N+1 addressed**: Bulk operations chunked with eager loading of device registrations and pass relationships. Device registrations queried by pass relationship, not individual lazy loads.

---

## Success Criteria

### Measurable Outcomes

- **SC-001**: Pass field updates appear on a customer's device within 30 seconds of the operator pressing "Update" (Apple) or 60 seconds (Google), measured from submission to device display.
- **SC-002**: Bulk updates of 1,000 passes complete processing and push delivery within 5 minutes under normal load.
- **SC-003**: The Apple Web Service Protocol endpoints pass Apple's Wallet conformance requirements — devices successfully register, receive push notifications, and download updated passes in the standard flow.
- **SC-004**: Lock screen change messages correctly display formatted text (e.g., "You now have 75 points!") on at least 95% of update deliveries where a change message is configured.
- **SC-005**: Failed push notification deliveries are automatically retried and stale device tokens are cleaned up, maintaining a device registry accuracy of 95% or higher (fewer than 5% stale registrations at any point).
- **SC-006**: The API pass update endpoint responds within 500ms (excluding the background push delivery), enabling real-time integrations to update passes without perceivable delay.
- **SC-007**: Operators can track the status of every pass update (pending, sent, delivered, failed) from the dashboard, providing full visibility into the push delivery pipeline.
