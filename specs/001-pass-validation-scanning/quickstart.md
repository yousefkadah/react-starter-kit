# Quickstart: Pass Validation & Scanning

## Overview
This feature introduces a secure, web-based scanning interface for merchants to validate and redeem digital passes. It uses a unique, long-lived "Scanner URL" per location/tenant to authenticate staff without requiring a full user login. The QR/barcode payload is a lightweight HMAC-signed string to prevent forgery.

## Key Components

1. **ScannerLink Model**: Generates and stores the unique tokens for the web scanner URLs.
2. **ValidateScannerToken Middleware**: Authenticates requests to the scanner interface and API endpoints using the `ScannerLink` token.
3. **Pass Payload Generation**: The existing pass generation logic will be updated to embed an HMAC-signed payload in the barcode.
4. **Scanner API**: Endpoints for validating (`/api/scanner/validate`) and redeeming (`/api/scanner/redeem`) passes.
5. **Web Scanner UI**: A React/Inertia page accessible via `/scanner/{token}` that uses the device camera to scan QR codes and interacts with the Scanner API.
6. **Concurrency Controls**: Pessimistic locking (`lockForUpdate()`) is used during redemption to prevent double-redemptions of single-use passes.

## Development Workflow

1. **Database Migrations**: Create the `scanner_links` and `scan_events` tables, and update the `passes` table with new fields (`usage_type`, `status`, `custom_redemption_message`, `redeemed_at`).
2. **Models & Relationships**: Create `ScannerLink` and `ScanEvent` models, and update the `Pass` model.
3. **Middleware**: Implement `ValidateScannerToken` to handle authentication for the scanner routes.
4. **API Controllers**: Implement the validation and redemption logic, ensuring proper error handling (e.g., generic "Invalid Pass" for cross-tenant scans) and concurrency controls.
5. **Frontend UI**: Build the web scanner interface using a React QR code scanning library (e.g., `html5-qrcode` or `react-qr-reader`), handling offline states and manual entry fallbacks.
6. **Testing**: Write comprehensive feature tests covering successful scans, invalid signatures, cross-tenant scans, double-redemption attempts, and offline behavior.

## Testing Strategy

- **Unit Tests**: Verify the HMAC payload generation and validation logic.
- **Feature Tests**:
  - Test the `ValidateScannerToken` middleware.
  - Test the `/api/scanner/validate` endpoint with valid, invalid, expired, and cross-tenant payloads.
  - Test the `/api/scanner/redeem` endpoint, specifically focusing on the pessimistic locking to prevent concurrent redemptions.
  - Test the web scanner UI rendering and interactions using Inertia assertions.
