// Official-role UI audit. The routes and permission map are already covered by
// OfficialAccessTest; this script checks what actually *renders*, which a
// feature test cannot: an action the tier may not take must be absent from the
// page, not merely rejected on submit. It also guards the `hidden` gate itself
// — Tailwind emits `.hidden{display:none}` ahead of `.inline`/`.inline-flex`,
// so without the tie-break in resources/css/app.css those gates are decorative.
//
// Usage: node check-official-ui.mjs   (dev server on :8000, seeded official)
import { chromium } from 'playwright';

const BASE = 'http://127.0.0.1:8000';
const USER = process.env.AUDIT_USER || 'official@barangay.local';
const PASS = process.env.AUDIT_PASS || 'password';

const failures = [];
const check = (label, ok, detail = '') => {
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${label}${detail ? ` — ${detail}` : ''}`);
  if (!ok) failures.push(label);
};
const skip = (label, why) => console.log(`SKIP  ${label} (${why})`);

const browser = await chromium.launch();
const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
const page = await ctx.newPage();

// Same login flow as check-app.mjs: fire the tab's own handler, submit the
// form directly, then wait for the form to leave rather than trusting a URL.
await page.goto(`${BASE}/login`, { waitUntil: 'load' });
await page.evaluate(() => document.querySelector('[data-role-tab="office"]').click());
await page.fill('#email', USER);
await page.fill('#password', PASS);
await page.evaluate(() => document.getElementById('login-form').submit());
try {
  await page.waitForFunction(() => !document.querySelector('form[action*="login"]'), { timeout: 20000 });
} catch {
  console.log(`LOGIN FAILED at ${page.url()}`);
  await browser.close();
  process.exit(1);
}
console.log(`authenticated as ${USER} -> ${page.url()}`);

// Visible means rendered AND not CSS-hidden: delete buttons stay in the DOM
// under `hidden`, so counting matches alone would give false failures.
const shown = async (text) => {
  const loc = page.getByText(text, { exact: true });
  const n = await loc.count();
  for (let i = 0; i < n; i += 1) {
    if (await loc.nth(i).isVisible()) return true;
  }
  return false;
};

const visit = async (path) => {
  await page.goto(`${BASE}${path}`, { waitUntil: 'load', timeout: 20000 });
};

// The app rewrites the address bar to the bare domain on purpose, so a page is
// identified by its heading rather than by location.
const heading = async () =>
  page.evaluate(() => document.querySelector('h1')?.textContent.trim() || '(no h1)');
const inSidebar = async (suffix) =>
  page.evaluate((s) => !!document.querySelector(`#sidebar a[href$="${s}"]`), suffix);

// Row actions only exist when a row exists; an empty database has nothing to
// gate, so those assertions wait rather than fail spuriously.
const withRows = async (label, fn) => {
  if ((await page.locator('tbody tr').count()) === 0) return skip(label, 'no rows');
  check(label, await fn());
};
const withPending = async (label, fn) => {
  if ((await page.getByText('Pending', { exact: true }).count()) === 0) {
    return skip(label, 'no pending rows');
  }
  check(label, await fn());
};

await visit('/dashboard');
check('dashboard loads', (await heading()) === 'Dashboard');
check('sidebar hides Administration', !(await inSidebar('/admin/settings')));
check('sidebar shows office modules', await inSidebar('/residents'));

// View-only modules: no create, no edit, no export.
await visit('/residents');
check('residents: no Add Resident', !(await shown('Add Resident')));
await withRows('residents: no row Edit', async () => !(await shown('Edit')));

await visit('/households');
check('households: no Add Household', !(await shown('Add Household')));
check('households: no Export', !(await shown('Export')));
await withRows('households: no row Edit', async () => !(await shown('Edit')));

await visit('/certificates');
check('certificates: no Issue Certificate', !(await shown('Issue Certificate')));
check('certificates: no Export', !(await shown('Export')));

// Welfare: officials decide but never enter intake.
await visit('/welfare');
check('welfare: no Record Request', !(await shown('Record Request')));
check('welfare: no Export', !(await shown('Export')));
await withRows('welfare: decision Edit shown', () => shown('Edit'));
await withRows('welfare: Delete hidden', async () => !(await shown('Delete')));

// Blotter: the one operational module officials may write to.
await visit('/blotter');
check('blotter: Record Case shown', await shown('Record Case'));
await withRows('blotter: Print shown', () => shown('Print'));
await withRows('blotter: Delete hidden', async () => !(await shown('Delete')));

// The two approval queues are the official's core surface.
await visit('/admin/certificate-requests');
await withPending('certificate queue: Approve shown', () => shown('Approve'));

await visit('/admin/resident-changes');
await withPending('correction queue: Approve shown', () => shown('Approve'));

// Administrator-only modules and the resident portal both bounce.
await visit('/admin/settings');
check('settings bounces to dashboard', (await heading()) === 'Dashboard');

await visit('/my');
check('resident portal bounces to dashboard', (await heading()) === 'Dashboard');

await browser.close();

console.log(failures.length ? `\n${failures.length} FAILED: ${failures.join(' | ')}` : '\nALL PASS');
process.exit(failures.length ? 1 : 0);
