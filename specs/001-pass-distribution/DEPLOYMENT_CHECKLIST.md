# Pass Distribution — Deployment Checklist

## Pre-deployment

- [ ] All 39 distribution tests pass: `php artisan test tests/Feature/PassDistribution/ --compact`
- [ ] PHP code formatted: `vendor/bin/pint --dirty --format agent`
- [ ] Frontend assets built without errors: `npm run build`
- [ ] No new Vite manifest errors

## Database

- [ ] Run migrations: `php artisan migrate`
- [ ] Verify `pass_distribution_links` table exists with columns: `id`, `pass_id`, `slug`, `status`, `last_accessed_at`, `accessed_count`, `expires_at`, `created_at`, `updated_at`, `deleted_at`
- [ ] Verify index on `slug` column (unique)
- [ ] Verify foreign key on `pass_id` referencing `passes.id`

## Routes

- [ ] Public route `/p/{slug}` accessible without auth
- [ ] Protected routes require authentication:
  - `GET /passes/{pass}/distribution-links` (index)
  - `POST /passes/{pass}/distribution-links` (store)
  - `PATCH /passes/{pass}/distribution-links/{distributionLink}` (update)
- [ ] Route model binding resolves correctly with `ScopedByRegion` scope

## Authorization

- [ ] PassPolicy gates enforce ownership: only the pass owner can create/view/update distribution links
- [ ] Non-owner authenticated users receive 403 Forbidden
- [ ] Unauthenticated users are redirected to login for protected routes

## Feature Verification

- [ ] Create a distribution link → slug auto-generated, status = `active`
- [ ] View pass via `/p/{slug}` → correct pass data rendered
- [ ] Access tracking: `last_accessed_at` and `accessed_count` update on each view
- [ ] Device detection: iOS Safari shows Apple Wallet button, Android shows Google Wallet
- [ ] QR code: scanning the generated QR code navigates to the correct `/p/{slug}` URL
- [ ] Disable link: PATCH status to `disabled` → public link returns 403
- [ ] Re-enable link: PATCH status to `active` → public link works again
- [ ] Expired pass: link still accessible but shows expiry message
- [ ] Voided pass: link returns 410 Gone

## Queue & Performance

- [ ] No N+1 queries on distribution pages (check with Laravel Debugbar or query log)
- [ ] Queue worker running for any async jobs

## Rollback Plan

- [ ] Rollback migration: `php artisan migrate:rollback --step=1` drops `pass_distribution_links`
- [ ] No destructive changes to existing `passes` table
