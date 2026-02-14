# Database Backup and Restore

These procedures ensure user data and region integrity remain intact.

## Backup

1. Verify access to the database host.
2. Run a consistent backup:

```bash
pg_dump --format=custom --file=backup.dump --no-owner --no-acl your_database
```

3. Store the dump in a secure location.

## Restore

1. Create a new database.
2. Restore from the dump:

```bash
pg_restore --no-owner --no-acl --dbname=your_database backup.dump
```

## Region integrity

- Do not modify the `users.region` column during restore.
- Avoid cross-region data moves without a migration plan.

## Sensitive data

- Certificate passwords and private keys are encrypted at rest.
- Ensure the application key is preserved to decrypt stored secrets.

## Verification

- Confirm counts for `users`, `apple_certificates`, and `google_credentials`.
- Validate a sample user can access their certificates after restore.
