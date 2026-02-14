# Admin Guide: Account Creation and Wallet Setup

This guide covers approval workflows and production tier management.

## Account approvals

1. Open the admin approvals queue.
2. Review the user profile and email domain.
3. Approve or reject the account.

Approval outcomes:

- Approve: User gets an activation email and can proceed to wallet setup.
- Reject: User receives a rejection email.

## Business domain whitelist

- Add or remove domains in the BusinessDomain table.
- Clear the cache after changes to ensure new signups reflect updates.

## Production tier requests

1. Review production requests in the admin queue.
2. Validate Apple and Google credential status.
3. Approve or reject with a reason.

## Queue monitoring

- Use `php artisan queue:health` to check pending and failed jobs.
- Failed jobs are logged with context in application logs.

## Certificate issues

Common causes:

- Expired Apple certificates.
- Invalid Google service account JSON.
- Missing issuer ID or project ID.

Encourage users to renew certificates before expiry.
