# SRS — Requirements Validation Checklist

*Barangay Management System · Barangay Didduag. Every requirement is validated against working code and, where shown, an automated feature test in the PHPUnit suite (171 tests / 673 assertions passing as of September 22, 2026). A requirement is **Validated** only when both the implementation and its evidence exist.*

## Legend

✅ Validated · 🟡 Partially validated · ⬜ Not yet implemented

---

## 1. Functional Requirements

| # | Requirement | Priority | Implementation | Validation evidence | Status |
|---|---|---|---|---|---|
| FR1 | The system shall authenticate users with email and password, distinguishing administrator, staff, and resident accounts | High | `AuthenticatedSessionController`, role-specific login tabs | `Auth/LoginTest`, `Auth/StaffAccessTest`, `Auth/RegistrationTest` | ✅ |
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

## 2. Nonfunctional Requirements

| # | Requirement | Target | Implementation | Validation evidence | Status |
|---|---|---|---|---|---|
| NFR1 | **Security — role-based access.** Guests, residents, and staff must not reach administrator-only modules; staff access is limited to approved operational permissions | 100% coverage of role/permission routes | `EnsureUserIsAdmin`, `EnsureUserHasPermission`, and resident middleware | CRUD suites plus `Auth/StaffAccessTest` positive/negative coverage | ✅ |
| NFR2 | **Security — credential handling.** Passwords hashed; reset codes stored hashed, expiring, single-use | bcrypt/argon2 + hashed TTL codes | Laravel hashing + `password_reset_tokens` | `PasswordResetTest` (expiry, single-use, replacement) | ✅ |
| NFR3 | **Security — account enumeration.** Password reset must not reveal whether an email exists | generic responses | uniform "code sent" messaging | `PasswordResetTest` assertions on generic responses | ✅ |
| NFR4 | **Accountability.** Every sensitive action traceable to a user, IP, device, and timestamp; never breaks the request it observes | no unlogged sensitive actions; audit failure must not 500 | `AuditLog::record()` swallows+reports exceptions | `AuditLogTest` order/permission tests | ✅ |
| NFR5 | **Usability — WCAG AA.** Text contrast ≥ 4.5:1; keyboard operable; focus visible and managed in dialogs; skip links | AA on all pages | contrast-checked palette (computed ratios), skip links, focus-trapped dialog, `<main>` landmarks | a11y audit with programmatic ratio checks + live keyboard walkthrough | ✅ |
| NFR6 | **Usability — touch targets.** Interactive controls ≥ 44px on mobile | all controls | `min-h-11` pattern app-wide | measured 44px via computed styles on key pages | ✅ |
| NFR7 | **Responsiveness.** Full functionality at 375–614px widths without horizontal overflow | no page-level overflow | hamburger nav, priority columns, responsive padding | live DOM overflow scans + screenshots at mobile width | ✅ |
| NFR8 | **Reliability — data integrity.** Validation server-side on every form; workflow rules enforced; referential integrity with safe unlinking (`set null`) | no invalid state persisted | form requests/validators, FK constraints | validation assertions in every CRUD suite | ✅ |
| NFR9 | **Correctness under concurrency.** Case numbers unique under simultaneous submissions | zero duplicates | `lockForUpdate` inside the creation transaction | `BlotterCrudTest` sequence tests | ✅ |
| NFR10 | **Maintainability.** Shared components for repeated UI; no byte-identical copies of logic | single source of truth | `x-form.field`, `x-form.actions` (color prop), `x-table.actions`, `x-print-button`, `nav-items`, `_form` partials | dedup audit (~1,600 duplicate lines removed); suites green against components | ✅ |
| NFR11 | **Testability.** Automated regression suite covering CRUD, auth, workflows, print artifacts | suite green | 171 tests / 673 assertions passing | `php artisan test` (2026-09-22) | ✅ |
| NFR12 | **Portability.** Runs on PHP 8.2 + SQLite (dev) and MySQL (production-ready via config) | env-driven | `.env` database config, migrations portable | suite runs on SQLite; MySQL documented in run doc | ✅ |
| NFR13 | **Performance.** Index pages paginate; dashboard counters aggregated, not per-row | ≤ 25 rows/page | pagination + aggregate queries | visible in controllers; manual response-time check | ✅ |
| NFR14 | **Print quality.** Printed documents use official-form layouts with letterhead, certification, and signatures where accountability requires them | barangay-ready paper output | case sheet, directory, audit extract, list printouts | live print-output verification + content-pinning tests | ✅ |

## 3. Validation methods used

1. **Automated feature testing** — every FR above maps to at least one PHPUnit feature test (171 tests / 673 assertions green).
2. **Code review against requirements** — each FR traced to a controller/route; traceability note in `use-case-descriptions.md`.
3. **Programmatic UI audits** — DOM-level contrast math (WCAG ratios from the actual Tailwind v4 palette), overflow scans, computed-style measurements (tap targets, print-hidden elements).
4. **Live end-to-end walkthroughs** — real browser flows for login, password reset (with clipboard auto-fill), approvals, blotter intake → case sheet, welfare intake → approval, and print output inspection.
5. **Regression discipline** — every defect found (NOT NULL 500, signed `diffInMinutes`, invisible mobile nav) received a test reproducing it before the fix.

## 4. Outstanding items

- **FR22** — welfare disbursement report page (planned next).
- **DFD Level 2** decompositions exist for the two highest-risk processes (auth/reset and welfare workflow); remaining processes can be decomposed on request.
- Formal **user-acceptance testing** with barangay staff is the remaining validation method before deployment.
