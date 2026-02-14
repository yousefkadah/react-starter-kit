# Rollback Plan

Use this plan if the feature needs to be disabled after deployment.

## Application rollback

1. Revert application code to the previous release.
2. Disable new routes if needed.
3. Stop or pause queue workers if jobs are failing.

## Database rollback

Rollback in reverse order:

1. Drop onboarding_steps
2. Drop account_tiers
3. Drop business_domains
4. Drop google_credentials
5. Drop apple_certificates
6. Remove user table extensions (region, tier, approval_status)

Use the migration rollback commands carefully in production.

## Data integrity

- Do not delete user data unless required by policy.
- Preserve certificate and credential records for auditing.

## Communication

- Notify support and stakeholders.
- Provide guidance to users if any setup actions are temporarily unavailable.
