# Barangay Management System — Barangay Bidduang

A clean rewrite of the Barangay Management System for **Barangay Bidduang, Municipality of Pamplona, Cagayan**.
Laravel 12 · PHP 8.2 · MySQL · Tailwind CSS 4 · Vite.

Logic, features and data model follow the original Barangay_System2 project
(improved and re-architected); the visual language is our own — the official
seal, the Cagayan palette (sky `#5CB2DE`, gold `#E8B93B`, green `#2F8F5B`,
navy `#08234A`, coral `#FF6B5C`) and a CSP-ready UI with no inline scripts
or styles.

## Running locally

```bash
composer install
npm install && npm run build
php artisan migrate --seed
php artisan serve        # http://127.0.0.1:8000
```

Configuration lives in `.env` (MySQL database `barangay_management`, mail for
password-reset links).

### Performance notes

- **OPcache is enabled** in `C:\xampp\php\php.ini` (shared by CLI and Apache).
  Without it every request re-parses the whole framework (~1s/page); with it
  pages render in ~6–300 ms. Restart Apache / the dev server after editing
  `php.ini`.
- Routes, events and views are cached (`route:cache`, `event:cache`,
  `view:cache`). **Config is deliberately NOT cached in development**: a
  cached config bakes `.env` values and overrides `phpunit.xml`, which makes
  the test suite run `RefreshDatabase` against the live MySQL database
  instead of the test database. Run `php artisan optimize` only when
  deploying, and `php artisan optimize:clear` before running tests.
- After changing any route file run `php artisan route:cache` again (or you
  will keep seeing the old definitions).
- `php artisan serve` is single-threaded — prefer the Apache URL
  `http://localhost/Barangay_Management_System` for daily use.

## Seeded accounts

All passwords are `password` (change them before any real use):

| Email                   | Role    | Status |
|-------------------------|---------|--------|
| admin@barangay.local    | admin   | active |
| staff@barangay.local    | staff   | active |
| resident@barangay.local | resident| active |

Seeded data: 5 puroks (P1–P5), 3 document types (CLR ₱50, COR ₱30, IND ₱0),
2 officials, 3 households, 3 residents.

## Roles & access

Roles are exactly `admin | staff | resident`; statuses
`pending | active | rejected | suspended`.

| Capability | Admin | Staff | Resident |
|--------------------------------------|:-----:|:-----:|:--------:|
| Sign in / manage own password | ✅ | ✅ | ✅ |
| Full-record self-registration (dialog on login) | — | — | creates **pending** application |
| Residents, households, puroks (view/manage) | ✅ | ✅ | — |
| Blotter, welfare intake | ✅ | ✅ | — |
| Issue certificates, review request queue | ✅ | ✅ (view + approve blocked) | — |
| Reports & analytics | ✅ | ✅ | — |
| Accounts, officials, certificate types, audit, exports, archive | ✅ | ❌ | — |
| Own profile + online certificate requests | — | — | ✅ |

Access is enforced by route middleware aliases — `admin`, `permission:x`
(office capability list, admin bypasses), `resident` — plus the global
`EnsureAccountIsActive` middleware: pending, rejected and suspended accounts
can sign out but see a status page instead of any application screen, and
cannot sign in at all (`LoginRequest` refuses them). Denials redirect to the
dashboard with a flash message (no bare 403 pages).

Privileged columns (`role`, `status`, `approved_at`, `reviewed_by`,
`suspended_*`, `resident_id`, `rejection_reason`) are **not** mass-assignable —
controllers write them with `forceFill()` only after an authorization check.

## Registration → approval → profile

1. A resident opens **Create an account** on the sign-in page and submits the
   full record (name parts, birth date, sex, civil status, phone, address,
   purok, household, email, password). Validation lands in the named
   `register` error bag so errors never paint under the sign-in form.
2. This creates a **pending** `users` row plus a `resident_applications` row —
   registration never claims or mutates an existing resident record.
3. The administrator reviews the submitted details on **Accounts** (details
   dialog), then **Approves** (account becomes active, application marked
   Approved, and the resident profile is created/linked in the same
   transaction — with an email-match guard so no duplicate is ever created)
   or **Rejects** with an optional reason (stored for the applicant).
4. The resident signs in and lands on `/my` with a complete portal profile.

Staff and official accounts are created by the administrator only.

## Screens

- **Sign-in** — single screen, no scrolling: sign-in form, *Create an account*
  dialog and *Reset password* dialog (both centered modals, `Esc`/backdrop
  close, focus trap), password eye-toggles, red right-aligned reset link.
- **/dashboard** — office workspace for admin + staff (module counts,
  pending-approval badges); residents are redirected to `/my`.
- **Residents** — searchable table, directory (printable), full profile page,
  create/edit with photo upload, archive/restore (admin).
- **Households** — auto numbers (HH-001…), head assignment, member counts,
  show/edit, admin delete.
- **Puroks** — auto codes (P#…), admin CRUD, occupied-purok delete refusal.
- **Blotter** — control numbers (BLTR-YYYY-0001), complainant/respondent
  narrative records, status workflow, printable blotter sheet.
- **Certificates** — issue register with fee status, control numbers
  (CLR/COR/IND-YYYY-0001), resident snapshots, fee override (admin only),
  void (admin), standalone A4 print page.
- **Certificate requests** — resident online requests (`/my/requests`) flow
  into the office queue; approval issues the certificate, rejection records a
  reason.
- **Welfare** — assistance requests with status workflow
  (Requested → Under Review → Approved/Denied → Released), amount rules.
- **Reports** — population, blotter and welfare reports with date ranges and
  CSV-friendly printing; **Analytics** — KPIs + CSS-only charts.
- **Portal (`/my`)** — profile with photo, contact updates, certificate
  requests, download of issued certificates.
- **Admin extras** — accounts (create/approve/reject/suspend/reactivate/role),
  officials CRUD (Punong Barangay protected), certificate-type catalog,
  audit trail with before/after diffs, CSV exports (formula-injection safe),
  archive with restore/purge.

## Project structure

```
app/Http/Controllers/          module controllers (residents, households,
                               puroks, certificates, blotter, welfare,
                               reports, analytics, portal) + Admin\*, Auth\*
app/Http/Middleware/           EnsureUserIsAdmin, EnsureUserHasPermission,
                               EnsureUserIsResident, EnsureAccountIsActive
app/Http/Requests/             FormRequests per module (validation)
app/Models/                    User (permissions), Resident, Household, Purok,
                               Official, Document, CertificateIssuance,
                               CertificateRequest, SequenceCounter, Blotter,
                               Welfare, ResidentApplication, AuditLog
routes/                        web.php, auth.php + routes/modules/*.php
resources/views/               auth/, dashboard/, residents/, households/,
                               puroks/, certificates/, blotter/, welfare/,
                               reports/, analytics/, resident/, admin/,
                               archive/, errors/, layouts/
database/                      migrations, seeders (re-runnable)
```

Rules of the house (inherited from the original system's lessons):

- no inline `<script>` / `<style>` (CSP-ready) — behavior lives in
  `resources/js/app.js`; standalone printable pages and themed error pages
  (403/404/419/500 via `public/css/errors.css`) are self-contained by design
- validation lives in FormRequests (or a named error bag for the login-page
  registration dialog); controllers stay thin
- control numbers are reserved atomically (`SequenceCounter` + row lock) and
  are never reused
- audit trail (`AuditLog`) records created/updated/deleted/approved/rejected/
  issued/voided events with before/after payloads
- every screen is reachable from a role-aware nav; no dead links

## Tests

```bash
php artisan test
```

Ships a focused auth suite (login, logout, password-reset dialog flows) —
15 tests. Feature modules are verified end-to-end through the live HTTP
surface rather than committed throwaway test files.
