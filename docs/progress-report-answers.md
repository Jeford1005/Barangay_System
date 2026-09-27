# Progress Report Answers — Barangay Management System

*Filled in as the student developer of the system. Instructor sections are intentionally left out. Replace the bracketed [ ] placeholders (names, section, dates) with your details.*

---

# FORM 1 — Software Project Progress Report

**Header**

- Software Project Title: **Barangay Management System — a web-based records and services system for the barangay hall (resident records, households, puroks, blotter, social welfare assistance)**
- Section: *[your section]*
- Instructor: *[your instructor]*
- Reporting Period: **September 1, 2026 to September 22, 2026**
- Date Submitted: **September 22, 2026**

## I. Project Team

| No. | Name of Member | Assigned Role | Major Responsibility |
|---|---|---|---|
| 1 | *[name]* | Project Manager/Leader | Overall coordination and scheduling; Git version control and commit discipline; integration of all modules into one working system |
| 2 | *[name]* | System Analyst(s) | Analyzed barangay office workflows (records keeping, blotter intake, welfare assistance); defined role-based access rules for admin/staff vs. residents |
| 3 | *[name]* | UI Designer | Designed the branded login/registration pages, dashboard, responsive mobile navigation, and the color-coded CRUD action system (green/blue/amber/red) |
| 4 | *[name]* | Programmer(s)/Developer(s) | Developed the Laravel 12 application: controllers, Blade views, middleware, authentication, email notifications, and printable report pages |
| 5 | *[name]* | Database Designer(s)/Developer(s) | Designed and implemented the database schema, migrations, model relationships, factories, and seeders; audit-log table design |

*(If the project is solo, keep row 1 with your name and write "Performed all roles" as the responsibility; contribution is 100%.)*

## II. Overall Project Progress

**Overall Completion: 85%**

| Project Phase/Activity | Status | % Complete |
|---|---|---|
| Project Planning | Completed | 100 |
| Requirements Analysis | Completed | 100 |
| System Design | Completed | 100 |
| Database Design | Completed | 100 |
| User Interface Design | Completed | 100 |
| System Development/Coding | Ongoing | 90 |
| Module Integration | Ongoing | 95 |
| Testing | Ongoing | 85 |
| Debugging | Ongoing | 85 |
| Documentation | Ongoing | 60 |
| Final Presentation/Defense | Not Started | 0 |

## III. System Development Progress

### A. Modules/Features Developed

| Module/Feature | Description | Status | % Complete |
|---|---|---|---|
| Authentication & Security | Login with account-type tabs, resident registration, password reset using a 6-digit code sent by email (with resend cooldown and masked-email hint), email verification | Completed | 100 |
| Role-Based Access & Account Approvals | Admin/staff vs. resident gating via middleware; residents self-register and admin approves or rejects, with an approval/rejection email notification | Completed | 100 |
| Resident Records Module | 25-field resident profile CRUD with search and filters by purok, household, and status | Completed | 95 |
| Household & Purok Modules | Full CRUD for households (linked to puroks) and purok organization | Completed | 100 |
| Blotter Records Module | Incident blotter with auto-issued case numbers (BLTR-YYYY-####), walk-in or registered parties, disposition-required-before-closing rule, and a printable official case sheet | Completed | 100 |
| Social Welfare Assistance | Assistance requests with types (financial, food, medical, etc.); workflow rules — approval requires a date and amount, release requires a full approval-to-release trail | Completed | 95 |
| Audit Log & Mail Health | Every sensitive action (password resets, record changes, deletions) logged with user, IP, and timestamp; built-in SMTP health check with a test-email sender | Completed | 100 |
| Resident Portal | Resident-facing page showing their own record and contact-info update | Ongoing | 70 |

### B. Database Development

| Database Component | Status | Remarks |
|---|---|---|
| Database Component (SQLite for development; MySQL-ready via config) | Completed | `.env`-driven; dev uses SQLite |
| Database Structure | Completed | Normalized Laravel migrations; one migration per table |
| Tables | Completed | users, residents, households, puroks, officials, blotter, welfare, audit_logs, password_reset_tokens, sessions, cache, jobs |
| Primary/Foreign Keys | Completed | FKs to residents/officials with `onDelete: set null` so walk-in parties and unlinking never break records |
| Relationships | Completed | Blotter → complainant/accused (residents), handling officer (officials), encoder (users); Welfare → beneficiary (residents); Household → purok |
| Queries | Completed | Scoped search/filter per module, pagination, dashboard counters, race-safe case-number sequencing inside a DB transaction with row locks |
| Data Validation | Completed | Server-side validation on every form (required fields, enum-backed statuses, no future dates, workflow integrity rules) |

## IV. User Interface / User Experience Progress

| Interface/Screen | Status | Remarks |
|---|---|---|
| Login (branded split-screen, forgot-password dialog with clipboard auto-fill) | Completed | Dark-blue barangay branding with official seal |
| Dashboard 1 — Admin dashboard | Completed | Live module counters |
| Dashboard 2 — Resident portal | Ongoing | Own-record view working; more self-service planned |
| Dashboard 3 — Audit log page | Completed | Searchable, filterable accountability trail |
| Transaction 1 — Resident records CRUD | Completed | Shared form partial; 44px touch targets |
| Transaction 2 — Household CRUD | Completed | |
| Transaction 3 — Purok CRUD | Completed | |
| Transaction 4 — Blotter intake & disposition | Completed | Case-number auto-assignment |
| Transaction 5 — Welfare assistance intake | Completed | Approval/release workflow validation |
| Transaction 6 — Account approvals | Completed | Approve/reject with email notifications |
| Report 1 — Printable blotter case sheet | Completed | Official-form layout with letterhead, certification, signature blocks |
| Report 2 — Printable index lists (all modules) | Completed | A4 landscape, repeating headers, app chrome hidden |
| Report 3 — Audit log printout | Completed | Uses the shared print stylesheet |
| Report 4 — Welfare disbursement summary | Not Started | Planned next |

Also delivered within this period: full mobile responsiveness (hamburger navigation, priority table columns on phones), WCAG AA contrast checks, keyboard/skip-link accessibility, focus-trapped dialogs, and print buttons on every index page.

## V. Problems and Challenges Encountered

| Problem/Challenge | Effect on Project | Solution/Action Taken | Status |
|---|---|---|---|
| Auth scaffolding had broken routes/missing controllers, so the app failed to boot | Blocked all development | Created the missing auth controllers or trimmed routes; verified with `php artisan route:list` | Resolved |
| Password-reset code expiry check never fired because Carbon 3's `diffInMinutes()` returns a signed value | Reset flow unusable | Fixed using `abs()` on the difference; added regression tests | Resolved |
| `welfare.approved_amount` NOT NULL violation when browsers submit an empty optional field (tests missed it because they omitted the key) | 500 error on real form submission | Normalized input to the column default (0) in the controller; added a regression test reproducing the browser behavior | Resolved |
| All module navigation links were hidden below 640px — mobile users could not reach any module | Mobile unusable | Added a hamburger menu built from a shared nav-items partial | Resolved |
| Heavy duplication: four near-identical create/edit view pairs, ~40 repeated field blocks, byte-identical JS | High maintenance risk; drift between copies | Extracted shared form partials and reusable Blade components; removed ~1,600 duplicated lines | Resolved |
| Gmail SMTP for real password-reset emails requires app-password setup by the user | Reset email untested in production config | Built a mail health-check page/command (`php artisan mail:check`) with a test-email sender, and documented the Gmail setup; log mailer works locally in the meantime | Ongoing |

---

# FORM 2 — SRS Project Progress Report

**Header**

- Project Title: **Barangay Management System**
- Section: *[your section]*
- Date of Report: **September 22, 2026**
- Instructor: *[your instructor]*

## I. Project Team

| No. | Name of Member | Role/Responsibility | % Contribution |
|---|---|---|---|
| 1 | *[name]* | Project Leader — coordination, integration, version control | *[e.g., 25]* |
| 2 | *[name]* | Requirements Analyst — barangay workflow analysis, access rules | *[e.g., 20]* |
| 3 | *[name]* | Documentation — SRS drafting, user manual | *[e.g., 20]* |
| 4 | *[name]* | System Designer — architecture, UI design, diagrams | *[e.g., 20]* |
| 5 | *[name]* | Programmer/Developer — Laravel implementation, database | *[e.g., 15]* |

*(Solo project: one row, 100%.)*

## II. Project Progress Summary

**Overall Project Completion: 85%**

| SRS Component / Activity | Status | % Complete | Remarks |
|---|---|---|---|
| 1. Project Background | Completed | 100 | Paper-based barangay records context established |
| 2. Problem Statement | Completed | 100 | Slow retrieval, no accountability trail, manual welfare/blotter tracking |
| 3. Project Objectives | Completed | 100 | Measurable objectives tied to each module |
| 4. Scope and Limitations | Completed | 100 | Web-based; excludes payroll/financial accounting |
| 5. Stakeholder Identification | Completed | 100 | Punong Barangay, secretary, encoders/staff, residents |
| 6. Requirements Elicitation | Completed | 100 | Interview, observation, document review |
| 7. Functional Requirements | Completed | 100 | Mapped to implemented modules (auth, approvals, 5 CRUD modules, audit, mail) |
| 8. Nonfunctional Requirements | Completed | 100 | Security, usability/accessibility, performance, portability, accountability |
| 9. User Requirements | Completed | 100 | Per-role needs defined |
| 10. System Requirements | Completed | 100 | PHP 8.2, Laravel 12, SQLite/MySQL, Tailwind CSS, Vite, SMTP |
| 11. Use Case Diagram | Completed | 100 | Admin/staff, resident, guest, mail-system actors |
| 12. Use Case Descriptions | Completed | 100 | 17 use cases with main + alternate flows (`docs/srs/use-case-descriptions.md`) |
| 13. Data Flow Diagram | Completed | 100 | Context (0), Level 1, and two Level 2 decompositions with balancing notes (`docs/srs/data-flow-diagrams.md`) |
| 14. ERD / Data Requirements | Completed | 100 | Matches the implemented schema |
| 15. Requirements Validation | Completed | 100 | Full checklist: every FR/NFR mapped to implementation + test evidence (`docs/srs/requirements-validation-checklist.md`) |
| 16. SRS Documentation | Ongoing | 90 | Draft assembled; final formatting pending |

## III. Accomplishments During This Period

*Reporting Period: September 1, 2026 to September 22, 2026*

1. Implemented the complete authentication flow: login, resident registration, account-approval emails, and password reset via a 6-digit email code with resend cooldown.
2. Built five admin modules — residents, households, puroks, blotter, and social welfare assistance — with role-based middleware gating.
3. Implemented accountability features: audit logging (user, IP, timestamp) for password resets and record changes, plus a searchable audit-log page.
4. Produced printable outputs: an official blotter case sheet with letterhead, certification, and signature blocks; print-ready versions of every index table.
5. Grew the automated PHPUnit suite to 166 tests / 643 assertions, all passing; added regression tests for every bug found.
6. Completed accessibility (WCAG AA contrast, keyboard navigation, focus management) and mobile-responsiveness passes, and removed ~1,600 lines of duplicated code.

## IV. Requirements Progress

### A. Requirements Elicitation

**Stakeholders Consulted:** Punong Barangay; Barangay Secretary; barboys/encoders (records staff); residents of the barangay.

**Elicitation Methods Used:**
- [x] Interview
- [x] Observation
- [ ] Questionnaire/Survey
- [ ] Focus Group
- [x] Document Review
- [ ] Other

**Date Conducted:** *[date of your consultation/visit]*

**Major Findings:**
- Resident records, household rosters, and purok lists are kept on paper and in spreadsheets, making searches slow and error-prone.
- The blotter is a handwritten logbook — case numbering is inconsistent and producing an official copy for a complainant means copying it by hand.
- Welfare assistance requests are tracked ad hoc; there is no record of approval amounts versus released amounts.
- Password/account resets currently require a personal visit to the hall; the barangay wants email-based self-service with proof of identity.
- Officials need an accountability trail (who changed what, when, from where) for records that may be requested during audits.

## V. Problems and Challenges Encountered

| Problem/Challenge | Action Taken | Status |
|---|---|---|
| Converting informal user interviews into testable functional requirements | Wrote each requirement as a module acceptance criterion; automated tests now assert them | Resolved |
| Keeping the SRS diagrams in sync with fast-moving implementation | ERD validated directly against migrations; DFD/use-case descriptions finalized last | Ongoing |
| Real email delivery depends on Gmail app-password configuration outside the system | Documented setup, built mail health check + test email | Ongoing |

## VII. Team Member Contribution

| Team Member | Tasks Completed | Contribution | Remarks |
|---|---|---|---|
| *[name]* | *[e.g., Auth modules, blotter module, test suite]* | ___% | |
| *[name]* | *[...]* | ___% | |
| *[name]* | *[...]* | ___% | |
| *[name]* | *[...]* | ___% | |
| *[name]* | *[...]* | ___% | |
