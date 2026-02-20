# Changelog

All notable changes to this project will be documented in this file.

## 2026-02-14

### Added
- Account signup with email domain validation and approval workflow.
- Apple CSR generation and certificate upload endpoints.
- Google Wallet credential upload and rotation instructions.
- Tier progression system with production approval and go-live gating.
- Region scoping for user-owned data.
- Certificate expiry notifications and queue health monitoring.

### Changed
- Users table extended with region, tier, and approval fields.
- Added onboarding wizard tracking and completion steps.

### Operational
- New background jobs for certificate expiry checks.
- Scheduler entry for daily expiry checks.
