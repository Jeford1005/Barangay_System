# SRS — Requirements Validation Checklist

*Barangay Management System · Barangay Didduag. Every requirement is validated against working code and, where shown, an automated feature test in the PHPUnit suite (411 tests / 1899 assertions passing as of September 29, 2026). A requirement is **Validated** only when both the implementation and its evidence exist.*

## Legend

✅ Validated · 🟡 Partially validated · ⬜ Not yet implemented

---

## 1. Functional Requirements

| # | Requirement | Priority | Implementation | Validation evidence | Status |
|---|---|---|---|---|---|
| FR1 | The system shall authenticate users with email and password, distinguishing administrator, official, staff, and resident accounts | High | `AuthenticatedSessionController`, role-specific login tabs (resident / office), `LoginRequest::candidateRoles()` | `Auth/LoginTest`, `Auth/StaffAccessTest`, `Auth/OfficialAccessTest`, `Auth/RegistrationTest` | ✅ |
| FR2 | The system shall allow visitors to apply for resident accounts that remain unapproved until reviewed | High | `RegisteredUserController` (`approved = false`) | `Auth/RegistrationTest` | ✅ |
| FR3 | The system shall let admins approve or reject applications and notify the applicant by email either way | High | `AccountApprovalController`, `AccountApproved/Rejected` mail | `Feature/Admin/AccountApprovalTest` | ✅ |
| FR4 | The system shall reset a password using a 6-digit code emailed to the account holder, valid 15 minutes, single-use, one active code at a time | High | `PasswordResetCodeController`, `ResetPasswordCodeNotification` | `Feature/Auth/PasswordResetTest` | ✅ |
| FR5 | The system shall rate-limit repeated code requests with a visible resend cooldown and show a masked email hint | Medium | cooldown in reset flow + dialog UI | `PasswordResetTest` (cooldown assertions) | ✅ |
| FR6 | The system shall maintain purok records (create, edit, delete) | High | `PurokController` | `Feature/Purok/PurokCrudTest` | ✅ |
| FR7 | The system shall maintain household records linked to puroks and household heads | High | `HouseholdController` | `Feature/Household/HouseholdCrudTest` | ✅ |
| FR8 | The system shall maintain 25-field resident profiles with search and purok/household/status filters | High | `ResidentController`, shared `_form` partial | `Feature/Resident/ResidentCrudTest` | ✅ |
| FR9 | The system shall archive resident records instead of hard-deleting, and allow restoration | Medium | SoftDeletes + archive/restore actions | `ResidentCrudTest` (archive tests) | ✅ |
| FR10 | The system shall record blotter cases with auto-issued unique case numbers `BLTR-YYYY-####` that cannot collide under concurrent saves | High | `BlotterController::getNextCaseNumber()` (locked transaction) | `BlotterCrudTest` (sequence tests) | ✅ |
| FR11 | Blotter parties may be registered residents or walk-ins; a closed case requires a written disposition | High | nullable FKs + workflow validation | `BlotterCrudTest` (walk-in + disposition tests) | ✅ |
| FR12 | The system shall produce a printable official blotter case sheet per case | Medium | `blotter/print` standalone document | `BlotterCrudTest::test_print_sheet_*` | ✅ |
| FR13 | The system shall record welfare assistance requests with type, program, and amounts | High | `WelfareController` | `Feature/Welfare/WelfareCrudTest` | ✅ |
| FR14 | Welfare approval requires an approval date and positive amount; release requires a date on/after approval | High | workflow validation in `WelfareController` | `WelfareCrudTest::test_approving_requires…`, `test_released_requires_full_trail` | ✅ |
| FR15 | The system shall log audit events (user, email, IP, user agent, timestamp, properties) for password resets, record changes, deletions, and account decisions | High | `AuditLog::record()` called by all controllers | `AuditLogTest`, per-module audit assertions | ✅ |
| FR16 | The system shall provide a searchable, event-filterable audit log page for admins | Medium | `AuditLogController` | `Feature/AuditLogTest` | ✅ |
| FR17 | The system shall verify SMTP configuration and send a test email on demand | Medium | `MailHealthController`, `mail:check` command, shared `MailHealthChecker` | mail-health feature coverage | ✅ |
| FR18 | Residents shall view their own record and update only their contact information | Medium | `ResidentPortalController` | portal feature coverage | ✅ |
| FR19 | The system shall print a resident directory grouped by purok with per-purok counts, active residents only | Medium | `ResidentController::directory()` | `ResidentCrudTest::test_directory_*` | ✅ |
| FR20 | The system shall print certification extracts of the audit log (scope header, all columns, signature blocks) | Medium | print-only blocks + scoped print CSS | `AuditLogTest::test_audit_page_prints_certification_footer_with_scope` | ✅ |
| FR21 | Index lists shall print cleanly (chrome hidden, bordered tables, repeating headers) | Low | global `@media print` stylesheet | selector-by-selector DOM verification | ✅ |
| FR22 | Welfare disbursement report with per-program totals | Medium | — | — | ⬜ (planned next) |
| FR23 | Residents shall report an incident from the portal; the report enters the office's own blotter queue as an Open case, identified as resident-reported | High | `ResidentBlotterController` (`/my/blotter`), `blotter.reported_by_resident` flag | `ResidentReportingTest` (filed case, queue reach, validation, own-reports-only) | ✅ |
| FR24 | Residents shall request welfare assistance from the portal; the request enters the office's welfare queue as `Requested` with the resident linked as beneficiary | High | `ResidentWelfareController` (`/my/welfare`) | `ResidentReportingTest` (submitted request, queue reach, validation, own-requests-only) | ✅ |
| FR25 | Barangay officials shall review and decide the three approval queues (welfare assistance, certificate requests, record corrections) and record blotter cases, while resident and household records stay read-only and every destructive or configuration action stays administrator-only | High | `User::ROLE_OFFICIAL`, the official branch of `User::hasPermission()`, route gates + controller guards + view gates per `docs/srs/access-control-matrix.md` | `Auth/OfficialAccessTest` (sign-in and permission set, 11 modules readable, 8 administrator-only modules denied, three decisions executed, clerical entry denied, 5 destructive actions denied, role assignment, resident portal denied) plus `audit:official` render pass | ✅ |
| FR26 | Residents shall see a read-only directory of the barangay officials currently serving — name, position, office and term — with no actions on it | Medium | `ResidentOfficialsController` (`/my/officials`), `Official::active()`, `resident.officials` sidebar entry under a `03 Barangay` section | `ResidentOfficialsTest` (listing, serving-only filter, position order, empty state, office users redirected, guests sent to login) | ✅ |

## 2. Nonfunctional Requirements

| # | Requirement | Target | Implementation | Validation evidence | Status |
|---|---|---|---|---|---|
| NFR1 | **Security — role-based access.** Guests, residents, and staff must not reach administrator-only modules; officials review and decide without entering clerical records; staff access is limited to approved operational permissions | 100% coverage of role/permission routes | `EnsureUserIsAdmin`, `EnsureUserHasPermission`, and resident middleware, plus the controller guards and view gates in `docs/srs/access-control-matrix.md` | CRUD suites plus `Auth/StaffAccessTest` and `Auth/OfficialAccessTest` positive/negative coverage | ✅ |
| NFR2 | **Security — credential handling.** Passwords hashed; reset codes stored hashed, expiring, single-use | bcrypt/argon2 + hashed TTL codes | Laravel hashing + `password_reset_tokens` | `PasswordResetTest` (expiry, single-use, replacement) | ✅ |
| NFR3 | **Security — account recovery feedback.** A reset request must never advance an unknown, pending, rejected, or suspended address to the code screen; the visitor is told why and held on step 1 | no code issued, no advance, without an eligible account | `PasswordResetCodeController::refusalReason()` + `refuse()`; cooldown starts only once a code is actually issued | `PasswordResetTest` (unknown address, mistyped address, rejected/suspended, JSON 422) and the `RegistrationTest` pending case | ✅ |
| NFR4 | **Accountability.** Every sensitive action traceable to a user, IP, device, and timestamp; never breaks the request it observes | no unlogged sensitive actions; audit failure must not 500 | `AuditLog::record()` swallows+reports exceptions | `AuditLogTest` order/permission tests | ✅ |
| NFR5 | **Usability — WCAG AA.** Text contrast ≥ 4.5:1; keyboard operable; focus visible and managed in dialogs; skip links | AA on all pages | contrast-checked palette (computed ratios), skip links, focus-trapped dialog, `<main>` landmarks | a11y audit with programmatic ratio checks + live keyboard walkthrough | ✅ |
| NFR6 | **Usability — touch targets.** Buttons, inputs and selects ≥ 44px on mobile (WCAG 2.5.5 AAA); standalone links ≥ 24px (WCAG 2.5.8 AA); links inside a sentence are exempt under WCAG's inline exception and native checkboxes under its user-agent exception | all interactive controls | `min-h-11` for controls, `min-h-6` for standalone links, `2.75rem` on the sidebar `.nav-row` and `.sidebar-toggle` | computed-style tap-target scans at 375×667 and 768×1024 over 33 office pages, 6 resident-portal pages, /login in both roles, both login dialogs, /register, /forgot-password and /reset-password — zero controls below target; only the exempt in-sentence links measure lower | ✅ |
| NFR7 | **Responsiveness.** Full functionality at 375–614px widths without horizontal overflow | no page-level overflow | hamburger nav, priority columns, responsive padding; wide print columns scroll inside `.sheet` rather than moving the page | DOM horizontal-overflow scans at 375×667 and 768×1024 over 33 office pages (including `/residents/directory` and the three `?print=1` document views) and 6 resident-portal pages, plus 13 viewports × 4 auth pages — zero page-level overflows | ✅ |
| NFR8 | **Reliability — data integrity.** Validation server-side on every form; workflow rules enforced; referential integrity with safe unlinking (`set null`) | no invalid state persisted | form requests/validators, FK constraints | validation assertions in every CRUD suite | ✅ |
| NFR9 | **Correctness under concurrency.** Case numbers unique under simultaneous submissions | zero duplicates | `lockForUpdate` inside the creation transaction | `BlotterCrudTest` sequence tests | ✅ |
| NFR10 | **Maintainability.** Shared components for repeated UI; no byte-identical copies of logic | single source of truth | `x-form.field`, `x-form.actions` (color prop), `x-table.actions`, `x-print-button`, `nav-items`, `_form` partials | dedup audit (~1,600 duplicate lines removed); suites green against components | ✅ |
| NFR11 | **Testability.** Automated regression suite covering CRUD, auth, workflows, print artifacts | suite green | 411 tests / 1899 assertions passing | `php artisan test` (2026-09-29) | ✅ |
| NFR12 | **Portability.** Runs on PHP 8.2 + SQLite (dev) and MySQL (production-ready via config) | env-driven | `.env` database config, migrations portable | suite runs on SQLite; MySQL documented in run doc | ✅ |
| NFR13 | **Performance.** Index pages paginate; dashboard counters aggregated, not per-row | ≤ 25 rows/page | pagination + aggregate queries | visible in controllers; manual response-time check | ✅ |
| NFR14 | **Print quality.** Printed documents use official-form layouts with letterhead, certification, and signatures where accountability requires them | barangay-ready paper output | case sheet, directory, audit extract, list printouts | live print-output verification + content-pinning tests | ✅ |

## 3. Validation methods used

1. **Automated feature testing** — every FR above maps to at least one PHPUnit feature test (411 tests / 1899 assertions green).
2. **Code review against requirements** — each FR traced to a controller/route; traceability note in `use-case-descriptions.md`.
3. **Programmatic UI audits** — DOM-level contrast math (WCAG ratios from the actual Tailwind v4 palette), overflow scans, computed-style measurements (tap targets, print-hidden elements). Reproducible with `npm run audit:responsive` (13 viewports × 4 auth pages), `npm run audit:targets` (auth tap targets) and `npm run audit:app` (33 office pages + 6 resident-portal pages × 2 viewports), each run against a local `php artisan serve`.
4. **Live end-to-end walkthroughs** — real browser flows for login, password reset (with clipboard auto-fill), approvals, blotter intake → case sheet, welfare intake → approval, and print output inspection.
5. **Regression discipline** — every defect found (NOT NULL 500, signed `diffInMinutes`, invisible mobile nav) received a test reproducing it before the fix.

## 4. Outstanding items

- **NFR3 revised 2026-09-29** — password reset deliberately gives up account-enumeration safety for actionable feedback: an unmatched address is now told "couldn't find an account" instead of receiving a generic "code sent" confirmation, and is held on step 1. The registration form's `unique:users,email` validation already disclosed the same fact, so the two doors now agree. Requirement, use-case A3, the data-flow rule and the tests were updated together so this checklist and the suite stay in step.
- **FR22** — welfare disbursement report page (planned next).
- **DFD Level 2** decompositions exist for the two highest-risk processes (auth/reset and welfare workflow); remaining processes can be decomposed on request.
- Formal **user-acceptance testing** with barangay staff is the remaining validation method before deployment.
