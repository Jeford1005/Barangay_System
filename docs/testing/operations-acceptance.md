# Operations acceptance checklist

Run these checks against a disposable environment before deployment:

```bash
php artisan system:health
php artisan data:quality-audit
php artisan schedule:list
php artisan queue:work --once
```

- Configure a real SMTP provider and send a test message from `/admin/mail-health`; confirm delivery and the audit event.
- Run `php artisan queue:work` and verify a backup run moves from `Queued` to `Completed` in `backup_runs`; intentionally fail a test backup and verify the `Failed` status and audit event.
- Confirm the scheduler invokes the daily data-quality, queue-pruning, and hourly health commands.
- Verify backup files remain under `storage/app/private/backups`, are downloadable only by an administrator, and are not exposed from `public/`.
- Test the system on the production database engine, not only the in-memory SQLite test connection. Verify sequence allocation under concurrent issuance and the chosen full-text/search strategy.
- Run `npm run test:browser` with disposable credentials. The public shell test is always enabled; authenticated cases are enabled by `E2E_ADMIN_EMAIL` and `E2E_ADMIN_PASSWORD`.
- Verify a Staff account can perform operational intake and certificate issuance at the catalog fee, but cannot approve welfare, waive fees, archive/restore residents, delete records, export datasets, or open administrator settings.
