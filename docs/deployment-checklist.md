# Deployment Checklist

- [ ] Run migrations in production
- [ ] Verify queue worker is running
- [ ] Verify scheduler runs daily expiry check
- [ ] Seed BusinessDomain and AccountTier data
- [ ] Confirm admin users are seeded
- [ ] Validate .env settings for mail, queue, and cache
- [ ] Confirm file storage permissions for certificate uploads
- [ ] Verify CSR generation endpoint returns a file
- [ ] Confirm certificate upload endpoints return 201
- [ ] Validate tier request endpoints are accessible
- [ ] Monitor queue failures during rollout
