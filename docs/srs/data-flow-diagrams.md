# SRS — Data Flow Diagrams

*Barangay Management System · Barangay Didduag. Yourdon/DeMarco notation: processes are numbered circles, external entities are squares, data stores are open-ended rectangles (D1…), data flows are labeled arrows. Diagrams below are text-rendered so they can be redrawn 1:1 in draw.io/Visio. Current as of September 22, 2026.*

## Level 0 — Context Diagram

Single process view of the whole system with its external entities.

```
                    registration data, login            +---------------------------+
   +-------------+  credentials, reset requests       |                           |
   |  Guest /    | ---------------------------------> |                           |
   |  Applicant  | <--------------------------------- |                           |
   +-------------+   verification link, reset code,   |                           |
                     approval notice                  |                           |
                                                     |      0                    |
   +-------------+   record edits, print requests     |  Barangay Management      |
   | Administrator / Staff | ---------------------------------> |  System                   |
   | Staff       | <--------------------------------- |  (records, blotter,       |
   +-------------+   printable forms/directories,    |   welfare, security)      |
                     audit extracts, dashboards       |                           |
                                                     |                           |
   +-------------+   own-portal requests, contact    |                           |
   | Resident    | ---------------------------------> |                           |
   +-------------+   updates                         |                           |
        ^  ^-------+ own record view, save confirm  |                           |
        |                                          +---------------------------+
        |                                                        |
        | notifications: approval, rejection, reset codes        v
        |                                          +---------------------------+
   +-------------+                                 |   Mail System (SMTP)      |
   |   Email     | <-------------------------------|   (external)              |
   +-------------+   outgoing email                +---------------------------+
```

**Flows in:** credentials/registration/reset requests, record edits, print requests, contact updates.
**Flows out:** views and printable documents, confirmations, notifications (via SMTP), audit records.

---

## Level 1 — Major processes

```
External entities: Guest, Admin, Resident, Mail System
Data stores:       D1 users          D2 residents      D3 households/puroks
                   D4 blotter        D5 welfare        D6 audit_logs
                   D7 password_reset_tokens

  Guest ---> (1.0 Authenticate &                 ---> D1 users
              Manage Accounts)  |  <---  D7 password_reset_tokens
                 |             |
                 |             +--> (1.1 Send Email) ---> Mail System
                 v
  Admin  ---> (2.0 Manage Resident Records)      <--> D2 residents
                 |                                <--> D3 households/puroks
                 +----> (2.5 Audit)               ---> D6 audit_logs
                 |
                 +---> (3.0 Manage Blotter)       <--> D4 blotter
                 |           +----> (2.5 Audit)   ---> D6 audit_logs
                 |
                 +---> (4.0 Manage Welfare)       <--> D5 welfare
                 |           +----> (2.5 Audit)   ---> D6 audit_logs
                 |
                 +---> (5.0 Generate Reports)     <--- D2, D3, D4, D5
                 |
                 +---> (6.0 Review Accounts)      <--> D1 users
                             +----> (1.1 Send Email)

  Resident ---> (7.0 Resident Portal)            <--- D2 residents
                             +----> (2.5 Audit)  ---> D6 audit_logs
```

| Process | Purpose | Inputs | Outputs |
|---|---|---|---|
| 1.0 Authenticate & Manage Accounts | Sign-in, registration, password reset with email code | credentials, registration data, reset requests | sessions, verified/approved accounts, reset codes |
| 1.1 Send Email | Compose and dispatch all system email | notification payloads | outgoing mail to SMTP |
| 2.0 Manage Resident Records | Resident/household/purok CRUD with validation | record edits | validated records, list/filter views |
| 2.5 Audit | Record accountability events for every sensitive action | event + actor + IP + properties | D6 rows |
| 3.0 Manage Blotter | Case intake, numbering, disposition | case data | numbered cases, status badges |
| 4.0 Manage Welfare | Assistance requests with approve/release workflow | request data | validated requests, totals |
| 5.0 Generate Reports | Printable case sheets, directory, audit extracts, list printouts | query results + scope | print documents |
| 6.0 Review Accounts | Approve/reject resident applications | decision + reason | account status, decision email |
| 7.0 Resident Portal | Read-only own record, contact self-service | portal requests | own-record view, contact updates |

---

## Level 2 — 1.0 Authenticate & Manage Accounts (password reset path)

```
  Guest --email-----------------> (1.2 Verify Identity)  <--- D1 users
              |                        |
              |                        +--generate 6-digit code, hash, 15-min TTL---> D7
              |                        +--mask email hint, cooldown timer---> Guest
              v
        (1.1 Send Email) ----reset code email----> Mail System

  Guest --code + new password---> (1.3 Validate Reset)
              |                        |  <--- D7 (hash, expiry, single-use)
              |                        +--success: update password---> D1
              |                        +--log completed/failed----> D6
              v
        (1.1 Send Email) ----confirmation----> Mail System
```

**Rules enforced inside 1.2–1.3:** one active code per account (new request replaces the old); code expires in 15 minutes; single use; failed attempts audited (`password_reset.failed_code`); generic response prevents account enumeration.

---

## Level 2 — 4.0 Manage Welfare (approve/release workflow)

```
  Admin --request data--> (4.1 Validate Request)
                              |  <--- D2 residents (beneficiary check)
                              +--normalize approved_amount default---> D5 welfare
                              +--audit welfare.created---------------> D6
  Admin --status=Approved----> (4.2 Enforce Approval Rule)
                              |   rule: approval_date required AND approved_amount > 0
                              +--pass---> D5 welfare ; fail---> field errors
  Admin --status=Released----> (4.3 Enforce Release Rule)
                              |   rule: release_date >= approval_date, full trail present
                              +--pass---> D5 welfare ; fail---> field errors
                              +--audit welfare.updated---------------> D6
  Admin --open reports page--> (4.4 Aggregate) --pending count, approved sums---> Admin
```

---

## Balancing notes

- Level 1 balances with the context diagram: every context flow terminates at one of the processes above.
- Every write process (2.0, 3.0, 4.0, 6.0, 7.0) sends a flow to **2.5 Audit** → D6, matching the implemented `AuditLog::record()` calls.
- D7 is accessed only by processes 1.2/1.3 — reset codes never flow to other processes.
- 5.0 Generate Reports reads from D2–D5 but never writes them (print paths are read-only), matching the read-only print controllers.
