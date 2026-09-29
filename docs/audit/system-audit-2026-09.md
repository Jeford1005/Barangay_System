# System Audit — September 2026

Read-only review of the whole application (security, data integrity, operations,
UI/accessibility, dependencies) plus the Laravel 12 → 13 / PHP 8.2 → 8.3
upgrade. Findings are separated into what was **fixed in this pass** and what is
an **open recommendation**, so nothing is silently dropped.

> **Port note:** the codebase has since been moved back to **Laravel 12 /
> PHP 8.2** (this project's target runtime), so the upgrade narrative below is
> kept as the audit record but the version claims no longer describe HEAD.

Local database findings are from `php artisan data:quality-audit`, which is
read-only and changes no rows.

---

## 1. The Laravel 13 upgrade (completed)

| Item | Before | After |
|---|---|---|
| `laravel/framework` | 12.69.1 | 13.33.0 |
| `phpunit/phpunit` | 11.5.56 | 12.5.36 |
| `laravel/tinker` | 2.11.1 | 3.0.2 |
| PHP constraint | `^8.2` | `^8.3` |
| CI matrix | `php-version: '8.2'` (could not resolve) | `'8.3'` |

Laravel 13 needs PHP 8.3–8.5. XAMPP still bundles PHP 8.2.12, so **XAMPP can
no longer serve this application** until its Apache/PHP is pointed at a PHP 8.3
runtime. CLI, tests, and builds all run on the standalone PHP 8.3.33 install.

Upgrade-guide items checked against this codebase:

- `PreventRequestForgery` / CSRF — no `VerifyCsrfToken` references, no `::except()`,
  all 52 POST forms carry `@csrf`. Nothing to migrate.
- Session `serialization` — left at the framework default. The app stores no PHP
  objects in the session, so switching to `json` is safe and would harden
  against deserialization gadget chains, but it logs everyone out. Recommended as
  a deliberate, announced change, not a silent one.
- Cache `serializable_classes` — the app *does* cache Eloquent collections
  (`auth.purok-options`, `auth.household-options`). Enabling the hardening
  requires switching those to arrays first. Left as a follow-up.
- Cache prefix / session cookie names — already explicitly configured.
- `upsert` on MySQL/MariaDB, pagination view names, `array_first`/`array_last`,
  queue event renames, domain route precedence, `Str` factories, `Js::from` —
  none are used in this codebase.
- `symfony/polyfill-php85` global-function conflicts — no `array_first`,
  `array_last`, or `laravel/helpers`.

Verification on PHP 8.3.33 / Laravel 13.33.0: 351 tests / 1,578 assertions
green, `npm run build` green, Playwright green including the authenticated admin
shell with an axe scan, `composer audit` and `npm audit` both report zero
advisories, and Pint reports no style issue in any file this work touched.

---

## 2. Fixed in this pass

### 2.1 Data integrity and corruption

**Certificate request approval could mint two certificates.**
`CertificateRequestAdminController::approve()` checked `status === 'Pending'`
*outside* the transaction and then reused the already-loaded model inside it, with
no `lockForUpdate()` and no re-check. Two clerks (or a double-clicked button)
could both pass the check, both consume a control number, and the second
`update()` would orphan the first issuance — an `Issued` certificate with a valid
control number that no request points at.
Both `approve()` and `reject()` now re-read the row under a lock *inside* the
transaction and re-check the status, matching the pattern already used in
`ResidentRecordChangeController::approve()`.

**Certificate approval no longer checks the resident is active.**
A request submitted while the resident was active could be approved after the
profile was archived. The resident lookup now asserts `status === 'Active'`.

**Force-purging from the archive corrupted or 500'd.**
`ArchiveController::destroy()` only special-cased welfare. Purging a household
with members left every member flagged `is_household_head` with a null
`household_id` — a state the data-quality audit flags and that the resident edit
form cannot then save, because `HouseholdResidentSync` rejects a head with no
household. Purging a resident with certificate history hit a `restrictOnDelete`
foreign key and surfaced a raw `QueryException` (HTTP 500). Purging a linked
resident orphaned the user account.
`purgeBlocker()` now pre-flights all three and returns a form error instead.

**SQLite concurrency guards were decorative.**
`lockForUpdate()` compiles to an empty string on SQLite, and `busy_timeout` and
`journal_mode` were both `null`. Contention surfaced immediately as
`SQLITE_BUSY` with no retry. The connection now defaults to `busy_timeout=5000`,
`journal_mode=WAL`, `synchronous=NORMAL`, all env-overridable, and
`SequenceCounter::reserve()` uses `insertOrIgnore` with a fallback so two clerks
hitting a brand-new `(scope, year)` pair no longer collide on the unique index.
Transactions retry three times.

**Welfare had no state machine or cross-field money validation.**
`approved_amount` was validated independently of `requested_amount`, so an
approval could exceed the request. A `Denied` row could carry money, an approval
date, and a release date, and had no mandatory reason. All three are now rejected.

**The printed welfare report disagreed with the analytics page.**
`ReportController` summed `approved_amount` across *every* status, so `Requested`
and `Denied` rows were booked as disbursed money on official paper. Analytics
already filtered correctly. The report now counts only `Approved` and `Released`.

**Accountability gaps in the audit trail.**
`HouseholdController` and `PurokController` wrote no audit rows at all.
`ResidentController::store()` and `update()` — the highest-volume PII write paths
in the system — wrote none either. Resident create and update are now recorded
with the specific list of fields that changed.

**Seeder was not idempotent.**
`DatabaseSeeder` used `create()` against unique keys (`admin@barangay.local`,
`HH-001`, …) with no transaction, so a second `db:seed` aborted partway and left
a half-applied dataset. It is now transactional and keyed on natural keys.

**`system:health` threw when the database was down.**
Four queries sat outside the try/catch that exists for exactly that failure, so
the health report crashed instead of reporting "Database: FAILED".

**Backups were manual-only, worker-dependent, and could tear.**
Nothing in the scheduler ever created a backup — only an admin clicking a button,
which dispatches a queued job that stays `Queued` forever without a worker.
Backups now run daily at 03:00. `BackupService` used `File::copy()` on the live
SQLite file, which can capture a torn snapshot mid-write; it now uses
`VACUUM INTO`, which takes a read lock and produces a consistent file, falling
back to a copy if the runtime cannot. `queue:prune --hours=24` was added so jobs
do not accumulate when no worker is running.

### 2.2 Security

**Password-reset codes were serialized in plaintext into the `jobs` table.**
`ResetPasswordCodeNotification` implemented `ShouldQueue` with a public string
`$code`, and `QUEUE_CONNECTION=database`. Every unworked reset code sat in
`jobs.payload`. It is now sent synchronously, which also means the 15-minute TTL
starts when the mail is actually handed to the mailer, and a delivery failure
reaches `PasswordResetCodeService`, which withdraws the code instead of leaving
an unreachable token.

**Reset codes had no per-account attempt limit.**
The route throttle is IP-scoped, so a rotating attacker got a fresh budget every
time. Wrong guesses are now counted per email address; the token is withdrawn
after five failures within fifteen minutes.

**Login had no IP-level ceiling.**
The only limiter keyed on `email + IP`, so rotating the source address allowed
unlimited guesses against an unbounded number of addresses from one host, and the
60-second window was refreshed by each further attempt so the lockout never
engaged. `POST /login` now also carries `throttle:20,1`, and the per-account
limiter decays over 300 seconds.

**Resident photos were served from the unauthenticated `public` disk.**
Photos were stored on `storage/app/public`, symlinked to `/storage`, and
rendered as bare URLs. Anyone with the URL could fetch them with no session, no
CSRF token, and no role check; the URL leaked through page HTML, browser cache,
and the `Referer` header; and archiving a resident did not remove the file. This
is a personal-data disclosure under the Data Privacy Act.
New uploads go to the private `local` disk and are streamed by
`ResidentPhotoController`, which requires an admin, a staff member, or the
resident the photo belongs to. Photos taken before the move are still served, and
superseded files are deleted on both disks.

**No security headers.** `AddSecurityHeaders` now applies
`X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy: same-origin`,
`X-Permitted-Cross-Domain-Policies`, and `Cache-Control: no-store, private` to
HTML responses. A `Content-Security-Policy` is deliberately *not* set yet: the
shell still uses inline `<script>`, inline `<style>`, and `onclick` handlers, so
a policy needs nonces first.

**No `.htaccess` guarding the web root.** `public/.htaccess` now denies dotfiles
and directory listings. A project-root `.htaccess` was added as a second layer
for the common XAMPP misconfiguration where `DocumentRoot` is the project root
rather than `public/` — that mistake would otherwise expose `.env`, `vendor/`,
and `storage/` over HTTP.

**The mail-health test sender was an unlogged open relay.** Sending real mail
through the office's Gmail credentials to any address now writes an audit entry.

**`?fragment=1` was an unvalidated layout switch.** Any page could be stripped
of its `<head>`, sidebar, and scripts by appending the parameter to a link.
Fragment mode now also requires the `X-Requested-With: XMLHttpRequest` header the
CRUD dialog script already sends.

**Permission denials were silent.** A staff user hitting an administrator-only
route was bounced to the dashboard with no message, indistinguishable from normal
navigation. The redirect now carries an explanation.

### 2.3 Correctness and UI

**The application ran in UTC.** `config/app.php` hardcoded `'timezone' => 'UTC'`
with no `APP_TIMEZONE` anywhere. For a Philippine barangay that is an eight-hour
error across every report boundary (`?to=2026-09-26` meant up to
`2026-09-27 07:59` local, so records filed that morning were excluded),
`before_or_equal:today`, every "Issued" stamp on official paper, and every CSV
timestamp. The default is now `Asia/Manila`, env-overridable.

**Print CSS deleted real data.** `thead th:last-child, tbody td:last-child
{ display: none !important; }` assumed the last column is always "Actions". On
the approvals table it was the decision timestamp; on the corrections table it
was the status and review note; on the audit log it was the device. The blanket
rule is gone and the genuine action columns carry `no-print` explicitly.

**Two icons were missing from the registry.** `arrow-down-tray` (every Export
button) and `x-mark` (Void certificate) were not in `components/icon.blade.php`,
and the component rendered nothing at all when a name missed — no error, no
placeholder. Both are added, and an unknown name now throws in `local` and
`testing` so a typo fails loudly instead of shipping a blank button.

**The mobile layout could be permanently offset by 68px.** The rail-minimize
script wrote an inline `margin-left` on `#app-content` with no media query, so a
user who minimized the sidebar once on desktop had that offset applied on every
subsequent phone load, where the sidebar is an off-canvas drawer and the only
control that could undo it is hidden. The script is now breakpoint-aware, clears
the inline offset below `lg`, re-applies on resize, and keeps the button's
`aria-label` in sync with its title.

**The `verified` middleware is a no-op.** `routes/web.php` uses it on
`/dashboard`, but `MustVerifyEmail` is commented out on `User`, so
`EnsureEmailIsVerified` short-circuits for everyone. The real control is
administrator approval. Either implement verification or drop the alias.

---

## 3. Open recommendations

These are real but were not changed here, with the reason.

### 3.1 Needs a data-migration decision first

**7 live data defects** in the local database, from `php artisan data:quality-audit`:

- `residents.user_id`: user 1 is linked by resident ids 1, 2 and 5 (duplicate).
- 4 residents linked to non-resident accounts.
- Household 1 selects resident 1 as head, but that resident's `household_id` is
  null, and resident 1 is flagged as a head with no household.

The root cause is that `residents.user_id` has no unique constraint —
deliberately deferred, because adding it fails on the duplicates above. Run
`php artisan residents:check-account-integrity` and follow
`docs/operations/data-remediation.md` before the constraint is added.

**No unique constraint on `residents.user_id`.** `User::residentProfile()` is a
`belongsTo` that silently returns an arbitrary row if there are two, and
`ResidentPortalController` then edits whichever row it gets. The application
guards in code only.

**Archived household members.** Soft-deleting a household does not detach its
members, so a member's household silently renders as "None" in the edit form and
the next save of that resident either detaches them or, if they were the head,
fails validation. Fix by blocking the delete while members exist, or detaching
and clearing head flags in the same transaction.

**Two archive mechanisms for residents.** `residents` has `SoftDeletes`, but no
production code ever soft-deletes a resident — `ResidentController::archive()`
sets `status = 'Archived'`. `/archive/residents` is therefore permanently empty
and the archive nav counts are misleading. The archive tests call
`$resident->delete()` directly to set up state, which is why the suite is green
while the feature is dead. Pick one mechanism.

### 3.2 Deliberately not changed

**`middleware->authenticateSessions()`.** Recommended as a defence against a
stolen session outliving a password change, and it was implemented and tested.
It signs users out in this application's own approval flow: `AccountApprovalController`
and `UserAccountController` already call `revokeUserSessions()`, which clears
`remember_token` and deletes `sessions` rows, and the middleware then treats the
missing password hash as a session mismatch. It conflicts with a flow the app
already implements correctly by hand, so it was reverted rather than left in a
state that only passes because the tests were adjusted.

**`$fillable` still contains privilege columns.** `User::$fillable` lists
`user_type`, `status`, `approved_at`, `reviewed_by`, `suspended_at/by`, and
`suspension_reason`. Not currently exploitable — every write path passes an
explicit array or a validator limited to `name`/`email`, and registration
hard-codes `user_type` server-side. But it is one line away from self-promotion.
Removing them means moving every privileged write to `forceFill` across the
account-approval, role-change, and suspension controllers, which is a larger
change than this pass and should be reviewed on its own.

**Content-Security-Policy.** Blocked by inline scripts, inline styles, and
`onclick` handlers in the shell. Needs a nonce refactor first. Note that
`crud-dialogs.js` deliberately re-executes every `<script>` in a fetched
fragment, so this should be paired with retiring the
fragment-and-`innerHTML` pattern.

**`crud-dialogs.js` treats any redirect as success.** `fetch` follows redirects
transparently, so a 302 to the login page (expired session) reads as "Changes
saved successfully", and `data-open-on-success` then opens the login page in a new
tab. The `window.open` call also omits `'noopener'`.

**Tailwind Play CDN as the production fallback.** `app-layout` falls back to
`cdn.tailwindcss.com` when `public/build/manifest.json` is absent, and
`/public/build` is gitignored — so a deploy that forgets `npm run build` silently
serves third-party JavaScript on every page. Make the missing-build case a hard
error in `production`.

**`verified` on `/dashboard`** — see 2.3.

**`Rule::exists` ignores soft deletes.** `Rule::exists('residents','id')` and
`Rule::exists('documents','id')` query the table directly, so a soft-deleted
resident can still be chosen as household head or certificate recipient, and a
soft-deleted document type can still be requested. Add `whereNull('deleted_at')`.
Note the certificate *code* rule is deliberately left as a plain `Rule::unique`
because the database has a hard unique index on it and the rule must agree.

**`ExportService` quirks.** `money()` returns a string, so a negative adjustment
is exported as `"'-500.00"` and Excel treats it as text — and a test currently
asserts that as correct. The certificate CSV maps `created_at` to both "Issued At"
and "Created At", so one column is duplicated while headers and values still
align 1:1, which is why no test catches it. `fputcsv` is called with `\` as the
escape character, which is not RFC-4180.

**Registration confirms whether an email is registered.** The `unique:users,email`
message tells an anonymous visitor that a given citizen has an account, which
contradicts the deliberately enumeration-safe password-reset flow. Closing it
requires either creating the pending account regardless and resolving the
duplicate during approval, or accepting a generic message that leaves the user
without actionable feedback. That is a product decision.

**Resolved 2026-09-29 — the product decision went to actionable feedback.**
Password reset now states plainly that an address has no usable account and
holds the visitor on step 1 instead of advancing them to a code screen, so the
registration form's duplicate-email message no longer contradicts it: both
doors behave the same way. NFR3 in `requirements-validation-checklist.md` and
use-case UC4's alternate flow A3 were rewritten to match, the data-flow rule
was updated, and the tests asserting the old generic response were replaced.

**Query cost.** `nav-items` runs three `COUNT(*)` queries on every page render;
`ArchiveController::index` runs four more; `AnalyticsController` loads every
active resident and then does per-purok filtering in PHP with no caching.

**`households.num_members` is a manually entered integer** that is never
reconciled with actual members, and is used in the CSV export.

**`audit_logs.actor_id` has no foreign key** while the legacy `user_id` column
does, so `actor_id` can dangle.

---

## 4. Test and coverage gaps

- **The authenticated browser suite never ran.** `authenticated-shell.spec.js`
  skipped itself unless `E2E_ADMIN_EMAIL`/`E2E_ADMIN_PASSWORD` were set, CI set
  neither, and the `browser` job ran `migrate` with no `db:seed`, so no admin
  account existed. The entire authenticated admin shell had zero automated
  accessibility or functional browser coverage. **Fixed:** the job now seeds the
  database, sets both variables, and builds assets. The spec was also verified
  locally against a real account and passes with a clean axe scan.
- **PHPUnit ran with deprecations ignored.** `phpunit.xml` set no
  `failOnDeprecation`/`failOnWarning`/`failOnNotice`, so the exact signal you
  want from a just-upgraded application was being swallowed. **Fixed:** all three
  are now enabled, and the suite passes with them, confirming the upgraded
  codebase produces no deprecations, warnings, or notices.
- **CI never audited dependencies.** `composer audit` and
  `npm audit --omit=dev` are now pipeline steps.
- **Print output is untested.** Nothing renders `?print=1` and asserts sheet
  content, which is why the print-CSS data loss went unnoticed. The affected
  columns are fixed; a regression test is still worth adding.
- **No icon-registry test.** A loop over the registry compared against the
  `x-icon` names used in views would have caught the two missing icons. The
  component now throws on an unknown name in `local`/`testing`, which turns the
  silent case into a loud one but is not the same as an exhaustive test.
- **CSV headers are never compared to CSV values**, only the first cell and the
  row count.
- **No concurrency tests.** The sequence counter and the backup snapshot are
  only exercised single-threaded.
- **No `system:health` degraded-mode test** simulating a database failure.
- **Playwright is chromium-only, desktop-only.** No mobile viewport — which is
  how the 68px sidebar offset in §2.3 would have been caught.

---

## 5. Deployment checklist

1. XAMPP's bundled PHP **8.2.12** is the supported runtime (the codebase is on
   Laravel 12 / PHP ^8.2); no standalone PHP install is required.
2. Set `APP_ENV=production` and `APP_DEBUG=false`. The shipped `.env` has debug
   on, which renders stack traces and environment values.
3. Set `APP_TIMEZONE=Asia/Manila` if the deployment is not in the Philippines.
4. Set `SESSION_SECURE_COOKIE=true` behind TLS.
5. Run `php artisan storage:link` only if the public disk is still in use; new
   resident photos are on the private disk and need no symlink.
6. Configure a queue worker (`php artisan queue:work`) and a scheduler
   (`php artisan schedule:run` every minute). The daily backup at 03:00 depends
   on the scheduler; the backup job itself is queued, so it also needs a worker.
7. `npm run build` must run before deploy, or the app falls back to the Tailwind
   Play CDN.
8. Rotate the Google App Password and `APP_KEY` in `.env` before any
   non-local deployment, and keep the real env file out of the served tree.
9. Create the backup download step-up and the `residents.user_id` unique index
   only after the data remediation in `docs/operations/data-remediation.md`.
