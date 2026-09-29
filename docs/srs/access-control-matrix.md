# SRS — Access Control Matrix

*Barangay Management System · Barangay Didduag. Four roles, one permission map.
This is the canonical statement of who may do what. The implementation is a
single method — `User::hasPermission()` — and every route gate, controller
guard and view gate reads from it, so this table and the running system cannot
drift apart without a test noticing.*

---

## Roles

| Role | Signs in on | What the role is for |
|---|---|---|
| **Administrator** | Office tab (`Admin/Staff`) | Owns the system. Every permission short-circuits to allowed, including configuration, accounts, exports, and every irreversible action. |
| **Official** | Office tab (`Admin/Staff`) | Reviews and decides. Reads every operational module, settles the three approval queues, records blotter cases. Never enters a clerical record, never deletes. |
| **Staff** | Office tab (`Admin/Staff`) | The clerical doer. Creates and edits residents, households, cases and assistance intake, issues certificates, prints reports. Never decides an approval, never deletes. |
| **Resident** | Resident tab | Self-service. Own record, own household summary, own requests and reports — enforced by ownership rather than by permission. |

`user_type` is a credential: the login tab selects which roles
`LoginRequest::candidateRoles()` will try, so an official account must be
signed in through the office tab exactly like an administrator's.

---

## Matrix

✓ allowed · — denied · *own* = own records only, via the resident portal

### Platform

| Capability | Admin | Official | Staff | Resident |
|---|:--:|:--:|:--:|:--:|
| Sign in, dashboard (`operations.access`, `dashboard.view`) | ✓ | ✓ | ✓ | — |
| Reports + printable report views (`reports.view`) | ✓ | ✓ | ✓ | — |
| Analytics (`analytics.view`) | ✓ | ✓ | ✓ | — |
| CSV exports (`exports.view`) | ✓ | — | — | — |
| Settings, accounts, approvals, audit log, mail health, certificate catalog, archive | ✓ | — | — | — |
| Sign out, change own password (reset flow) | ✓ | ✓ | ✓ | ✓ |

### Residents, households, puroks

| Capability | Admin | Official | Staff | Resident |
|---|:--:|:--:|:--:|:--:|
| View residents (`residents.view`) | ✓ | ✓ | ✓ | *own* |
| Create / edit residents (`residents.manage`) | ✓ | — | ✓ | — |
| Archive / restore residents | ✓ | — | — | — |
| View households (`households.view`) | ✓ | ✓ | ✓ | *own* |
| Create / edit households (`households.manage`) | ✓ | — | ✓ | — |
| Delete a household | ✓ | — | — | — |
| View puroks (`puroks.view`) | ✓ | ✓ | ✓ | — |
| Create / edit / delete puroks | ✓ | — | — | — |
| Resident photo stream (`residents.view`, or own photo) | ✓ | ✓ | ✓ | *own* |

### Blotter

| Capability | Admin | Official | Staff | Resident |
|---|:--:|:--:|:--:|:--:|
| View cases (`blotter.view`) | ✓ | ✓ | ✓ | *own reports* |
| Record / edit a case (`blotter.manage`) | ✓ | ✓ | ✓ | *report incident* |
| Print the case sheet (`blotter.manage`) | ✓ | ✓ | ✓ | — |
| Delete a case | ✓ | — | — | — |

### Welfare assistance

| Capability | Admin | Official | Staff | Resident |
|---|:--:|:--:|:--:|:--:|
| View requests (`welfare.view`) | ✓ | ✓ | ✓ | *own* |
| Record an intake (`welfare.intake`) | ✓ | — | ✓ | *request assistance* |
| Approve / release / deny (`welfare.approve`) | ✓ | ✓ | — | — |
| Delete a request | ✓ | — | — | — |

### Certificates and approvals

| Capability | Admin | Official | Staff | Resident |
|---|:--:|:--:|:--:|:--:|
| View issued certificates (`certificates.view`) | ✓ | ✓ | ✓ | — |
| Issue and print a certificate (`certificates.issue`) | ✓ | — | ✓ | — |
| Void a certificate | ✓ | — | — | — |
| Certificate request queue — view (`certificate-requests.view`) | ✓ | ✓ | ✓ | — |
| Certificate request queue — approve / reject (`certificate-requests.decide`) | ✓ | ✓ | — | *own requests* |
| Record correction queue — view (`resident-changes.view`) | ✓ | ✓ | ✓ | — |
| Record correction queue — approve / reject (`resident-changes.decide`) | ✓ | ✓ | — | *own corrections* |
| Submit a certificate request / a correction | — | — | — | ✓ |

---

## Enforcement

Three layers check the same permission, and a request has to pass all of them:

1. **Route middleware** (`bootstrap/app.php` aliases)
   `admin` → `EnsureUserIsAdmin`; `staff` and `permission:{name}` →
   `EnsureUserHasPermission`, the broad `staff` gate defaulting to
   `operations.access`; `resident` → `EnsureUserIsResident`. A denied request
   redirects to the dashboard *with* a message — a silent bounce would be
   indistinguishable from a broken link.
2. **Controller guard** — `abort_unless($user->hasPermission(…), 403)` on the
   actions worth defending twice. Middleware alone is not trusted, because a
   controller that only checks `isAdmin()` would reject an official the route
   had just admitted.
3. **View gate** — a button or link is rendered only when its permission
   passes, so the interface never offers an action that would be refused.
   Row actions are suppressed by adding `hidden`; toolbar actions are wrapped
   in `@if`.

### The `hidden` gate has to win its tie

Tailwind emits `.hidden{display:none}` *ahead of* `.inline` and
`.inline-flex`, and all three are single-class selectors — so an element
carrying `inline-flex … hidden` kept rendering, and the later rule won. That
made every row-action gate decorative: staff could see **Delete** as clearly as
an official once could. `resources/css/app.css` breaks the tie with
`.hidden { display: none !important; }`, which is why the gates above are
believed rather than assumed. `npm run audit:official` re-proves it against a
live page.

---

## Rules

1. **One destructive rule.** Every delete, archive, void, and account removal
   is administrator-only. No other role holds any of them, so there is exactly
   one thing to remember instead of one per module.
2. **One decision rule.** Approval, rejection, release, and fee override are
   `*.decide` / `welfare.approve`, held by administrators and officials. Staff
   may intake but never settle.
3. **Never offer a refused action.** A control rendered for a user must succeed
   when they press it; anything else is hidden, not disabled-by-hope.
4. **Read-only is still read-only.** Officials reach `/residents`,
   `/households`, and `/welfare` but arrive at pages with no create, edit, or
   delete control on them, because those routes would turn them away.

---

## Evidence

| Layer | Proof |
|---|---|
| Permissions, sign-in, queue decisions, denied routes | `tests/Feature/Auth/OfficialAccessTest.php` (13 tests) |
| Staff still cannot decide or delete | `tests/Feature/Auth/StaffAccessTest.php` |
| Rendered controls, sidebar, and both bounces | `npm run audit:official` → `check-official-ui.mjs` |
| Whole office + resident surface, both viewports | `npm run audit:app` (33 + 5 pages), `npm run audit:targets`, `npm run audit:responsive` |
| Schema accepts the fourth value on SQLite, MySQL, and Postgres | `database/migrations/2026_09_29_000002_add_official_role_to_users_table.php` |
