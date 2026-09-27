# Data remediation runbook

1. Take and verify a private backup.
2. Run `php artisan data:quality-audit --format=json` and save the report.
3. Review duplicate `residents.user_id` links with the barangay registrar. Confirm the authoritative resident for each account; do not merge or delete rows automatically.
4. Review household-head findings and correct them through the household/resident forms so the service can synchronize the head flag.
5. Re-run the audit until it exits `0`.
6. Only after the report is clean, add the database-level unique constraint for non-null `residents.user_id` in a separately reviewed migration.

Never use the browser archive purge action for welfare assistance history. Resident/account unlinking is explicit and suspends the account; it does not delete resident or historical case data.

## Staff role rollout

The `staff` role is additive. The migration only expands the allowed role values; it does not demote existing administrators or residents. Review the known sample account and any current office accounts before assigning `staff` through the administrator user directory. Do not bulk-update all existing `admin` accounts.

Staff can handle operational records and welfare intake, but administrator approval is required for welfare decisions, certificate fee overrides, resident archiving/restoring, record deletion, account management, and system maintenance. CSV exports remain administrator-only because they may contain full resident and case datasets.
