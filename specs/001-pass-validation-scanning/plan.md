# Implementation Plan: Pass Validation & Scanning

**Branch**: `001-pass-validation-scanning` | **Date**: 2026-02-21 | **Spec**: [spec.md](spec.md)
**Input**: Feature specification from `/specs/001-pass-validation-scanning/spec.md`

**Note**: This template is filled in by the `/speckit.plan` command. See `.specify/templates/commands/plan.md` for the execution workflow.

## Summary

Implement a secure, web-based scanning interface for merchants to validate and redeem digital passes. The solution uses a unique, long-lived "Scanner URL" per location/tenant to authenticate staff without requiring a full user login. The QR/barcode payload is a lightweight HMAC-signed string to prevent forgery. Concurrency controls (pessimistic locking) are used to prevent double-redemptions of single-use passes.

## Technical Context

**Language/Version**: PHP 8.3, React 19, TypeScript
**Primary Dependencies**: Laravel 12, Inertia v2, Tailwind CSS v4, html5-qrcode (or similar React QR scanner)
**Storage**: PostgreSQL (via Eloquent)
**Testing**: PHPUnit
**Target Platform**: Web browser (mobile and desktop)
**Project Type**: Web application (Laravel + React/Inertia)
**Performance Goals**: < 2 seconds validation time, 500 concurrent requests/min
**Constraints**: Offline scanning shows immediate error, concurrency controls for single-use passes
**Scale/Scope**: Web-based scanner, API endpoint, single-use/multi-use logic, custom messages

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

- [x] Laravel-First Architecture: uses Eloquent, Form Requests, policies, and Inertia renders; no new dependencies or base folders without approval.
- [x] Type-Safe Routing & Inertia Navigation: Wayfinder routes used, no hardcoded URLs, pages live under `resources/js/pages`.
- [x] Test-Backed Changes: test plan added with minimal test run documented.
- [x] Security & Tenant Isolation: authorization and scoping are explicit (via `ValidateScannerToken` middleware).
- [x] Performance & Reliability: heavy work queued, N+1 avoided, storage paths resilient.

## Project Structure

### Documentation (this feature)

```text
specs/001-pass-validation-scanning/
├── plan.md              # This file (/speckit.plan command output)
├── research.md          # Phase 0 output (/speckit.plan command)
├── data-model.md        # Phase 1 output (/speckit.plan command)
├── quickstart.md        # Phase 1 output (/speckit.plan command)
├── contracts/           # Phase 1 output (/speckit.plan command)
└── tasks.md             # Phase 2 output (/speckit.tasks command - NOT created by /speckit.plan)
```

### Source Code (repository root)

```text
app/
├── Http/
│   ├── Controllers/
│   │   └── Scanner/
│   │       ├── ScannerController.php
│   │       ├── ValidatePassController.php
│   │       └── RedeemPassController.php
│   ├── Middleware/
│   │   └── ValidateScannerToken.php
│   └── Requests/
│       └── Scanner/
│           ├── ValidatePassRequest.php
│           └── RedeemPassRequest.php
├── Models/
│   ├── ScannerLink.php
│   ├── ScanEvent.php
│   └── Pass.php (updated)
└── Policies/
    └── ScannerLinkPolicy.php

database/
├── migrations/
│   ├── [timestamp]_create_scanner_links_table.php
│   ├── [timestamp]_create_scan_events_table.php
│   └── [timestamp]_add_validation_fields_to_passes_table.php
└── factories/
    ├── ScannerLinkFactory.php
    └── ScanEventFactory.php

resources/
└── js/
    └── pages/
        └── Scanner/
            └── Index.tsx

routes/
├── web.php (updated with /scanner/{token})
└── api.php (updated with /api/scanner/*)

tests/
└── Feature/
    └── Scanner/
        ├── ScannerAuthenticationTest.php
        ├── ValidatePassTest.php
        └── RedeemPassTest.php
```

**Structure Decision**: Standard Laravel web application structure with Inertia.js frontend. Added a dedicated `Scanner` namespace in Controllers, Requests, and Tests to keep the scanning logic isolated from the main admin dashboard.

## Complexity Tracking

> **Fill ONLY if Constitution Check has violations that must be justified**

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|-------------------------------------|
| None | N/A | N/A |
