# SRS — Use Case Descriptions

*Barangay Management System · Barangay Didduag. Companion to the Use Case Diagram. Each use case lists the main success flow and the alternate/exception flows. Statuses reflect implementation as of September 22, 2026 — all primary use cases are working code covered by feature tests.*

## Actors

| Actor | Description |
|---|---|
| Administrator | Barangay system administrator. Authenticated, `user_type = admin`, with full system access. |
| Staff | Barangay secretary or encoder handling day-to-day records. Authenticated, `user_type = staff`, with limited operational access. |
| Resident | Barangay resident with a self-service account. Authenticated, `user_type = resident`. |
| Guest | Unauthenticated visitor. |
| Mail System (external) | SMTP server that delivers notification and reset emails. |

## Use case index

| # | Use Case | Primary Actor | Status |
|---|---|---|---|
| UC1 | Sign in | Guest, Administrator, Official, Staff, Resident | Implemented |
| UC2 | Register resident account | Guest | Implemented |
| UC3 | Approve / reject account | Admin | Implemented |
| UC4 | Reset password with email code | Guest | Implemented |
| UC5 | Manage puroks | Administrator | Implemented |
| UC6 | Manage households | Administrator, Staff | Implemented |
| UC7 | Manage resident records | Administrator, Staff | Implemented |
| UC8 | Record blotter case | Administrator, Official, Staff | Implemented |
| UC9 | Update blotter disposition | Administrator, Official, Staff | Implemented |
| UC10 | Print blotter case sheet | Administrator, Official, Staff | Implemented |
| UC11 | Record welfare assistance request | Administrator, Staff (intake only) | Implemented |
| UC12 | Approve and release welfare assistance | Administrator, Official | Implemented |
| UC13 | Print resident directory | Administrator, Staff | Implemented |
| UC14 | View audit log / print extract | Admin | Implemented |
| UC15 | Check mail health / send test email | Admin | Implemented |
| UC16 | View own record (portal) | Resident | Implemented |
| UC17 | Update own contact info | Resident | Implemented |
| UC18 | Report Incident | Resident | Implemented |
| UC19 | Request welfare assistance | Resident | Implemented |
| UC20 | View officials directory | Resident | Implemented |
| UC21 | Request a profile correction | Resident | Implemented |

---

## UC1 — Sign in

**Primary actor:** Guest, Administrator, Official, Staff, Resident · **Trigger:** user opens `/login` · **Precondition:** account exists and is approved with verified email.

**Main success flow**
1. Guest opens the login page and picks the account-type tab: **Admin/Staff**, which covers administrators, officials and staff, or **Resident**.
2. Guest enters email and password and submits.
3. System validates credentials, regenerates the session, and records the sign-in session.
4. System redirects: administrator/official/staff → dashboard; resident → resident portal.

**Alternate flows**
- **A1 — Unverified email:** system refuses sign-in and asks the user to verify their email first.
- **A2 — Pending approval:** registered-but-unapproved accounts cannot sign in; the resident sees a "waiting for approval" message.
- **A3 — Wrong credentials:** system re-renders the form with "These credentials do not match" and throttles repeated attempts.
- **A4 — Forgot password:** guest opens the forgot-password dialog → UC4.

---

## UC2 — Register resident account

**Primary actor:** Guest · **Trigger:** "Apply for a resident account" on the login page · **Precondition:** none.

**Main success flow**
1. Guest completes the registration form (name, email, phone, address, password). The sign-up password stays visible in plain text — only **Confirm password** carries a visibility eye — and mobile-number fields use a digits-only keypad with the `09XX XXX XXXX` placeholder.
2. System validates input, creates a user account with `user_type = resident`, `approved = false`.
3. System sends an email verification link; the audit log records `account.registered`.
4. System informs the guest that approval is pending and emails follow after review.

**Alternate flows**
- **A1 — Email already used:** system rejects the duplicate with a field error.
- **A2 — Weak password:** rejected with the password-rule error.
- **A3 — Email verification link expired:** user requests a new verification email.
- **A4 — Dialog failure (network or server error):** the register dialog submits as JSON; a failed save opens the red error-card message box with **Try Again**, which silently resubmits the same form. Validation errors (422) stay inline in the dialog, success returns `{"ok": true}`, and the only native alert left is a defensive fallback for the unlikely case that the dialog markup failed to render.

---

## UC3 — Approve / reject resident account

**Primary actor:** Admin · **Trigger:** admin opens Account Approvals · **Precondition:** admin authenticated; at least one pending application.

**Main success flow (approve)**
1. Admin opens `/admin/approvals`; pending applications are listed with the linked resident profile (auto-matched when possible).
2. Admin clicks **Approve** for an applicant.
3. System marks the account approved, records `account.approved` in the audit log, and queues the approval email (UC3 sends "You're approved" with sign-in instructions).
4. Resident can now sign in.

**Alternate flows**
- **A1 — Reject with reason:** admin enters a required reason (5–500 chars) and confirms; system records `account.rejected`, emails the reason, and marks the application rejected.
- **A2 — Already decided:** the application disappears from the pending list; the decision history section shows who decided it and when.
- **A3 — Not an admin:** resident accounts are redirected to the dashboard by the role middleware.

---

## UC4 — Reset password with email code

**Primary actor:** Guest · **Trigger:** "Forgot password?" on the login page · **Precondition:** an account exists for the email.

**Main success flow**
1. Guest enters their email in the forgot-password dialog.
2. System generates a 6-digit code, stores it hashed with a 15-minute expiry, replaces any earlier code, and emails it (branded template with the barangay seal).
3. System shows the masked email hint (e.g. `a***@gmail.com`) and starts a resend cooldown; the audit log records `password_reset.code_requested` with IP.
4. Guest enters the code (clipboard auto-fill supported); system validates it before expiry.
5. Guest sets a new password; system records `password_reset.completed`, invalidates the code and other sessions, and the guest signs in with the new password.

**Alternate flows**
- **A1 — Wrong/expired code:** system rejects with `password_reset.failed_code` logged; guest may request a new code.
- **A2 — Resend during cooldown:** blocked until the countdown ends (visible timer).
- **A3 — Unknown or unusable account:** system refuses on step 1 with the reason (not found, awaiting approval, not approved, or suspended), issues no code, and never advances to the code screen. The typed address stays in the field so a typo can be corrected, and no resend cooldown starts because nothing was sent.
- **A4 — Mailer down:** the mail-health page/`php artisan mail:check` surfaces the failure; codes are unavailable until fixed.

---

## UC5 — Manage puroks

**Primary actor:** Admin · **Trigger:** Purok module in the navigation · **Precondition:** admin authenticated.

**Main success flow**
1. Admin opens `/puroks` — the index lists puroks with code and resident counts.
2. Admin clicks **Add Purok**, fills name/code, submits; system validates uniqueness and creates the purok.
3. Admin edits a purok's details; system updates and re-renders with a success banner.

**Alternate flows**
- **A1 — Duplicate name:** rejected with a field error.
- **A2 — Delete purok:** allowed with confirmation; linked residents are detached (`set null`) and appear under "No Purok Assigned" in reports.

---

## UC6 — Manage households

**Primary actor:** Admin · **Trigger:** Household module · **Precondition:** admin authenticated; puroks exist (optional link).

**Main success flow**
1. Admin opens `/households`; the index lists households with code, purok, head, and member count.
2. Admin clicks **Add Household**, fills household code, purok, house type, ownership, head of household; system creates it.
3. Admin edits or deletes a household; deletes are confirmed and soft-delete-safe.

**Alternate flows**
- **A1 — Invalid purok/head reference:** rejected server-side.
- **A2 — Delete with members:** members are unlinked, not deleted.

---

## UC7 — Manage resident records

**Primary actor:** Admin · **Trigger:** Resident module · **Precondition:** admin authenticated.

**Main success flow**
1. Admin opens `/residents`; the index supports search plus purok/household/status filters with pagination.
2. Admin clicks **Add Resident** and completes the 25-field profile (identity, birth, contact, education, occupation, residency/voter status, purok, household).
3. System validates (required core fields, phone length, valid FKs, no future birth date) and saves; the audit log records the change.
4. Admin archives a record instead of deleting it; archived records are hidden from lists but restorable.

**Alternate flows**
- **A1 — Validation failure:** form re-renders with inline field errors, previous input preserved.
- **A2 — Restore archived:** admin restores from the archived list; the record rejoins the directory.
- **A3 — Link account:** an approved resident user can be tied to their resident profile (`user_id`).

---

## UC8 — Record blotter case

**Primary actor:** Admin · **Trigger:** "Record Case" in the blotter module · **Precondition:** admin authenticated.

**Main success flow**
1. Admin opens the blotter form; complainant and respondent may be registered residents (dropdown) or walk-ins (free text).
2. Admin fills incident details — type, date/time (no future dates), narrative, parties, handling officer.
3. On submit, the system issues the next case number (`BLTR-YYYY-####`) inside a locked transaction so concurrent clerks can't collide, saves, and records `blotter.created` with user, IP, and case number.
4. The index shows the case with a status badge; the open-case counter updates.

**Alternate flows**
- **A1 — Invalid resident/officer reference:** rejected server-side.
- **A2 — Future incident date:** rejected with a field error.

---

## UC9 — Update blotter disposition

**Primary actor:** Admin · **Trigger:** Edit a case and change status to Resolved/Dismissed · **Precondition:** case exists.

**Main success flow**
1. Admin opens the edit form and sets status to Resolved or Dismissed.
2. Admin writes the disposition text and disposition date.
3. System enforces the integrity rule (closed cases require a disposition), saves, and logs `blotter.updated`.

**Alternate flows**
- **A1 — Closing without disposition:** rejected with a labeled field error.
- **A2 — Soft delete:** deleting a case keeps it recoverable and logs `blotter.deleted`.

---

## UC10 — Print blotter case sheet

**Primary actor:** Admin · **Trigger:** Print action on a case row or the edit page · **Precondition:** case exists.

**Main success flow**
1. Admin opens `/blotter/{id}/print` in a new tab.
2. System renders the official case sheet — letterhead with seal, case-meta strip, six numbered sections, narrative, disposition, handling, certification clause, and signature blocks.
3. Admin prints via the toolbar button (screen chrome is print-hidden).

**Alternate flows**
- **A1 — Missing data:** unrecorded values print as italic "Not provided" placeholders so the form stays presentable.
- **A2 — Access control:** guests are redirected to login; resident accounts are redirected to the dashboard.

---

## UC11 — Record welfare assistance request

**Primary actor:** Admin · **Trigger:** "Record Request" in the welfare module · **Precondition:** admin authenticated.

**Main success flow**
1. Admin fills the beneficiary (walk-in or registered resident), assistance type, program, and requested amount.
2. System validates (type/program required, amount ≥ 0, request date not in the future), normalizes the optional approved amount to the 0 default, saves, and records `welfare.created`.
3. The index shows the request with a status badge; pending counters update.

**Alternate flows**
- **A1 — Blank optional fields:** stored as defaults (0/NULL) without error — covered by a browser-accurate regression test.
- **A2 — Invalid beneficiary reference:** rejected server-side.

---

## UC12 — Approve and release welfare assistance

**Primary actor:** Admin · **Trigger:** Edit a request's status · **Precondition:** request exists with status Requested/Under Review.

**Main success flow (approve)**
1. Admin sets status to **Approved**, enters the approved amount (> 0) and approval date.
2. System enforces the workflow rule (approval requires date + positive amount) and saves.

**Main success flow (release)**
3. Later, admin sets status to **Released** with a release date.
4. System enforces release ≥ approval date and the full trail, then saves and logs `welfare.updated`.

**Alternate flows**
- **A1 — Approve with zero/missing amount:** rejected with field errors.
- **A2 — Release before approval date:** rejected.
- **A3 — Deny:** status Denied with remarks; no amount constraints apply.

---

## UC13 — Print resident directory

**Primary actor:** Admin · **Trigger:** Directory button on the resident index · **Precondition:** active residents exist (empty is handled gracefully).

**Main success flow**
1. Admin opens `/residents/directory`.
2. System renders the master list grouped by purok — summary-by-purok table with counts and grand total, then one roster per purok (name, sex, birth date, contact, voter) with per-section counts.
3. Certification clause ("true and correct list of the active residents… totaling N") with Prepared by / Punong Barangay signature lines.
4. Admin prints; repeated table headers keep multi-page rosters readable.

**Alternate flows**
- **A1 — Purok-less residents:** listed under "No Purok Assigned" so nobody is silently dropped.
- **A2 — Archived residents:** excluded from the directory.

---

## UC14 — View audit log / print extract

**Primary actor:** Admin · **Trigger:** Audit Log in the navigation · **Precondition:** admin authenticated.

**Main success flow**
1. Admin opens `/admin/audit-logs` — searchable (email/IP), filterable by event, newest first, paginated.
2. Admin prints a certification extract: a print-only Scope header (active filters, record counts, page), all five columns forced visible (IP/device included), compact type, and a certification footer with Prepared by / Punong Barangay lines.

**Alternate flows**
- **A1 — No records match:** empty state shown on screen; print shows the certified zero-row scope.

---

## UC15 — Check mail health / send test email

**Primary actor:** Admin · **Trigger:** Mail Check in the navigation or `php artisan mail:check` · **Precondition:** mail config present.

**Main success flow**
1. Admin opens `/admin/mail-health`; the page verifies SMTP connectivity and configuration with a plain-language diagnosis.
2. Admin enters a recipient and sends a test email; the result is displayed immediately.

**Alternate flows**
- **A1 — SMTP failure:** the page shows the failure reason and the Gmail setup checklist; password-reset codes are affected until resolved.

---

## UC16 — View own record (portal)

**Primary actor:** Resident · **Trigger:** resident signs in · **Precondition:** approved account linked to a resident profile.

**Main success flow**
1. Resident lands on `/my` after sign-in.
2. System shows the resident's own profile data (read-only) — identity, household, purok, status.

**Alternate flows**
- **A1 — No linked profile:** the portal explains that the account is not yet linked to a resident record and to contact the barangay office.

---

## UC17 — Update own contact info

**Primary actor:** Resident · **Trigger:** "Update contact info" in the portal · **Precondition:** resident signed in.

**Main success flow**
1. Resident edits phone/email on the portal form.
2. System validates and updates only the contact fields — residents cannot modify name, status, household, or purok.

**Alternate flows**
- **A1 — Invalid phone/email format:** rejected with inline errors.

---

## UC18 — Report Incident

**Primary actor:** Resident · **Trigger:** "Report Incident" in the portal sidebar · **Precondition:** approved account linked to an active resident profile.

**Main success flow**
1. Resident opens `/my/blotter` and enters the complaint type, incident date, the person or party involved, and a narrative of what happened.
2. System validates the entry, issues the next `BLTR-YYYY-####` case number, and creates an `Open` case with the resident set as the complainant and `reported_by_resident` set.
3. The case shows up in the office's normal `/blotter` queue marked *Resident-reported*, and the resident sees it under "Reports" with its live status and any office note.

**Alternate flows**
- **A1 — Missing field or future-dated incident:** rejected with inline errors; no case is created.
- **A2 — No linked profile or archived record:** the portal returns 403/404 and tells the resident to contact the barangay office.

---

## UC19 — Request welfare assistance

**Primary actor:** Resident · **Trigger:** "Request Assistance" in the portal sidebar · **Precondition:** approved account linked to an active resident profile.

**Main success flow**
1. Resident opens `/my/welfare` and chooses the assistance type, the program or assistance needed, and the amount, with an optional reason.
2. System records the request as `Requested`, linked to the resident as beneficiary and dated today.
3. The request appears in the office's normal `/welfare` queue; approval and release remain office-only actions.

**Alternate flows**
- **A1 — Missing field or a non-numeric amount:** rejected with inline errors; nothing is persisted.
- **A2 — No linked profile or archived record:** the portal returns 403/404 and tells the resident to contact the barangay office.

---

## UC20 — View officials directory

**Primary actor:** Resident · **Trigger:** "Officials" in the portal sidebar · **Precondition:** approved account; `officials` rows marked `Active` exist (an empty list is shown as an empty state, not an error).

**Main success flow**
1. Resident opens `/my/officials`.
2. System lists the serving officials ordered by position, each card showing name, position, office, and term dates.
3. Nothing can be done from the page: it is read-only reference data, so there are no create, edit, or delete controls to gate.

**Alternate flows**
- **A1 — No active officials:** the page renders with "No barangay officials are listed yet."
- **A2 — Office account or guest:** office users are bounced to the dashboard by the `resident` middleware; guests are sent to login.

---

## UC21 — Request a profile correction

**Primary actor:** Resident · **Trigger:** **Edit** on the Profile page's record card · **Precondition:** approved account linked to an active resident profile.

**Main success flow**
1. Resident opens `/my` and presses **Edit**; the correction form appears on the same page (`/my?edit=1`) prefilled with the current record.
2. Resident changes any of the editable details (occupation, religion, residency status, purok, household), adds an optional note, and submits.
3. System queues the request as `Pending` and records `resident.change_requested`; the record on file is untouched.
4. The request is listed under "Correction requests" on the Profile page with its status and stays cancellable while pending; the office approves or rejects it from the correction queue (FR25).

**Alternate flows**
- **A1 — Nothing selected:** submission is refused with "Select at least one detail to request a correction."
- **A2 — Cancel:** the resident cancels their own pending request from the Profile page; it becomes `Cancelled`.
- **A3 — Rejected:** the office's review note is shown on the Profile page as a staff note and the record stays unchanged.

---

*Traceability: each use case above maps to implemented routes (see `routes/web.php`) and is exercised by the feature suite (469 tests / 2201 assertions as of October 1, 2026).*
