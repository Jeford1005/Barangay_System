# Browser and accessibility acceptance

The repository includes a Playwright smoke/accessibility gate for the public shell:

```bash
npm run test:browser
```

The suite starts `php artisan serve` when `http://127.0.0.1:8000/up` is not already available. Set `BROWSER_BASE_URL` to test another environment. Browser binaries must be installed separately with `npx playwright install chromium`.

On Windows PowerShell, environment variables use a different syntax than bash:

```powershell
$env:BROWSER_BASE_URL = 'http://127.0.0.1:8000'
npm run test:browser
```

The same applies to the audit scripts' variables (`AUDIT_USER`,
`AUDIT_PASS`, `AUDIT_ROLE`, `PAGES`), e.g.
`$env:AUDIT_ROLE = 'resident'; npm run audit:app`.

## Clean tree required

`playwright.config.js` sets `reuseExistingServer: true` and the suite is
chromium-only. That means a stale server or a dirty working tree silently
pollutes results:

- **Stop any existing `php artisan serve` first** (or point `BROWSER_BASE_URL`
  at a server you started deliberately from the current checkout). Otherwise
  the run tests whatever old code that server booted with, not your tree.
- **Start from a clean tree** (`git status --short` shows nothing you did not
  intend). Uncommitted view/route/config edits change what the browser sees;
  untracked scratch files do not affect the run but make failures harder to
  attribute.
- The gate is **chromium-only, desktop-only** — a mobile-viewport regression
  (e.g. an off-canvas sidebar offset) passes here and is caught instead by
  `npm run audit:responsive`, `npm run audit:targets` and `npm run audit:app`.

The current automated gate covers the public login shell, landmark presence, a serious/critical axe scan, and the liveness endpoint. Authenticated acceptance cases for account linking, household/head forms, resident self-service, archive behavior, print output, and mobile overflow should be run against a disposable seeded database before deployment. Do not run the suite against production data.

## Confirmation dialogs

Destructive CRUD actions never raise the browser's native `confirm()` box; they open the shared `<x-confirm-dialog>`, driven by `resources/js/confirm-dialog.js`. A form opts in with attributes on the `<form>` itself:

| Attribute | Purpose |
|---|---|
| `data-confirm` | The question, e.g. `Delete household HH-001?` |
| `data-confirm-title` | Heading. Defaults to `Are you sure?`. |
| `data-confirm-accept` | Confirm button label. Defaults to `Confirm`. |
| `data-confirm-tone` | `danger` (red, the default) or `primary` (sky) — sky is for reversible actions such as reactivate, send reset code and clear caches. |
| `data-confirm-dismiss` | Dismiss button label. Defaults to `Cancel`. |

Element hooks inside the dialog use a separate `data-confirm-dialog-*` namespace so they cannot collide with the form attributes above.

Acceptance:

- The destructive action opens the dialog; the native `confirm()` never fires.
- The message matches `data-confirm` verbatim, and the confirm button carries the tone colour (red for `danger`, sky for `primary`).
- Focus lands on the dismiss button, so a reflex Enter keypress cannot destroy a record.
- Escape, the dismiss button and a backdrop click all close without submitting, and the page scroll lock is released.
- Confirming re-submits the *same* form, so CSRF, `@method` spoofing and server-side validation are untouched.
- With JavaScript disabled the form submits normally — this is progressive enhancement, not a replacement pipeline.

### Never point an acceptance script at production

An ad-hoc Playwright run against the live project is how `HH-001` was soft-deleted during this work. The script guarded with `request.method() === 'DELETE'`, but the delete forms actually send `POST` with a spoofed `_method=DELETE`, so the guard never matched and the real submission went through. Two rules follow:

1. Intercept `POST` bodies containing `_method=DELETE`, not just the `DELETE` verb.
2. Prefer a disposable seeded database. `HH-001` was recoverable only because `Household` uses `SoftDeletes`; a hard-deleted record would not have been. The restore was `deleted_at = NULL` plus the `archive.restored` audit row the controller writes.
