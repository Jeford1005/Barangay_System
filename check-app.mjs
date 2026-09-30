// Responsive + tap-target audit of the SIGNED-IN application.
// Read-only: authenticates once, then only ever GETs list/create pages.
// Never submits a form, never touches export/download/cache endpoints.
import { chromium } from 'playwright';

const BASE = process.env.BROWSER_BASE_URL || 'http://127.0.0.1:8000';
const USER = process.env.AUDIT_USER || 'admin@barangay.local';
const PASS = process.env.AUDIT_PASS || 'password';

const OFFICE_PAGES = [
  '/dashboard',
  '/residents',
  '/residents/directory',
  '/residents/create',
  '/households',
  '/households/create',
  '/blotter',
  '/blotter/create',
  '/certificates',
  '/certificates/create',
  '/welfare',
  '/welfare/create',
  '/puroks',
  '/puroks/create',
  '/reports',
  '/reports/population',
  '/reports/blotter',
  '/reports/welfare',
  // Print variants all render through components/report-print.blade.php, whose
  // fixed pt column widths inside a 186mm sheet are exactly the pattern that
  // overflowed /residents/directory. Reachable without a record ID via ?print=1.
  '/reports/population?print=1',
  '/reports/blotter?print=1',
  '/reports/welfare?print=1',
  '/analytics',
  '/archive',
  '/archive/residents',
  '/admin/users',
  '/admin/settings',
  '/admin/approvals',
  '/admin/audit-logs',
  '/admin/certificate-types',
  '/admin/certificate-types/create',
  '/admin/certificate-requests',
  '/admin/resident-changes',
  '/admin/mail-health',
];

// The resident portal is the most phone-relevant surface: run with
// AUDIT_ROLE=resident and a resident login to scan it instead of the office pages.
// Only real GET pages: /my/photo is an image endpoint that 404s until a photo is
// uploaded, and /my/contact is PUT-only, so neither is a page to walk.
// /my?edit=1 is the Profile page with its correction form revealed.
const RESIDENT_PAGES = ['/my', '/my?edit=1', '/my/requests', '/my/blotter', '/my/welfare', '/my/officials'];

const PAGES = process.env.AUDIT_ROLE === 'resident' ? RESIDENT_PAGES : OFFICE_PAGES;

const VIEWPORTS = [
  ['mobile  375x667', 375, 667],
  ['tablet  768x1024', 768, 1024],
];

const probe = () => {
  const doc = document.documentElement;
  const overflowing = [];
  for (const el of document.querySelectorAll('body *')) {
    const r = el.getBoundingClientRect();
    if (r.width === 0 && r.height === 0) continue;
    if (r.right > window.innerWidth + 1 || r.left < -1) {
      overflowing.push(
        el.tagName.toLowerCase() + (el.id ? '#' + el.id : '') +
        (typeof el.className === 'string' && el.className ? '.' + el.className.trim().split(/\s+/)[0] : '')
      );
    }
  }

  const small = [];
  for (const el of document.querySelectorAll('input:not([type="hidden"]), button, a, select')) {
    const r = el.getBoundingClientRect();
    if (r.width < 2 || r.height < 2) continue;
    // WCAG 2.5.8 "user agent": a native checkbox/radio keeps the browser's own size.
    if (el.matches('input[type="checkbox"], input[type="radio"]')) continue;
    // WCAG 2.5.8 "inline": the target sits inside a sentence of text.
    const inSentence = !!(el.parentElement &&
      [...el.parentElement.childNodes].some((n) => n.nodeType === 3 && n.textContent.trim().length > 3));
    if (inSentence) continue;
    // NFR6: links must clear WCAG AA (24px); buttons, inputs and selects take the AAA target (44px).
    const need = el.tagName === 'A' ? 24 : 44;
    if (r.height < need) {
      small.push({
        tag: el.tagName.toLowerCase(),
        label: (el.id || el.textContent || '').trim().replace(/\s+/g, ' ').slice(0, 26),
        h: Math.round(r.height),
        need,
      });
    }
  }

  return {
    innerW: window.innerWidth,
    scrollW: doc.scrollWidth,
    overflowing: [...new Set(overflowing)].slice(0, 8),
    small,
    title: document.title,
  };
};

const browser = await chromium.launch();
const ctx = await browser.newContext({ viewport: { width: VIEWPORTS[0][1], height: VIEWPORTS[0][2] } });
const page = await ctx.newPage();

// ---- authenticate (once) ------------------------------------------------
// `user_type` is a credential (`in:office,resident`): LoginRequest only tries
// the roles the submitted tab allows, so with the wrong tab selected even
// correct resident credentials fail as "Invalid email or password."
// Firing the page's own click handler avoids Playwright's actionability wait.
const TAB = process.env.AUDIT_ROLE === 'resident' ? 'resident' : 'office';
await page.goto(BASE + '/login', { waitUntil: 'load' });
await page.evaluate((role) => document.querySelector(`[data-role-tab="${role}"]`).click(), TAB);
await page.fill('#email', USER);
await page.fill('#password', PASS);
await page.evaluate(() => document.getElementById('login-form').submit());
try {
  await page.waitForFunction(() => !document.querySelector('form[action*="login"]'), { timeout: 20000 });
} catch {
  const why = await page.evaluate(() => {
    const e = document.querySelector('[role="alert"], .text-red-600');
    return e ? e.textContent.trim().slice(0, 200) : 'no error node';
  });
  console.log(`LOGIN FAILED at ${page.url()} — ${why}`);
  await browser.close();
  process.exit(1);
}
console.log(`authenticated as ${USER} -> ${page.url()}`);

// A few pages only exist at a record-scoped URL (certificate/case sheets are
// reached from a row on their list page). Pull the first matching link so they
// get scanned too - they render the same fixed-pt document inside a 186mm sheet
// that overflowed /residents/directory.
const SEEDS = process.env.AUDIT_ROLE === 'resident'
  ? [['/my/requests', '/certificate']]
  : [['/certificates', '/print'], ['/blotter', '/print'], ['/admin/certificate-requests', '/print']];

for (const [list, suffix] of SEEDS) {
  try {
    await page.goto(BASE + list, { waitUntil: 'load', timeout: 20000 });
    const hrefs = await page.evaluate(() =>
      [...document.querySelectorAll('a[href]')].map((a) => new URL(a.href).pathname));
    const hit = hrefs.find((p) => p.endsWith(suffix));
    if (hit && !PAGES.includes(hit)) {
      PAGES.push(hit);
      console.log(`  record-scoped page discovered: ${hit}`);
    } else if (!hit) {
      console.log(`  no ${suffix} link on ${list} (nothing to scan)`);
    }
  } catch (e) {
    console.log(`  discovery skipped for ${list}: ${String(e.message).slice(0, 70)}`);
  }
}

const locks = [];
const notes = [];

for (const [vname, w, h] of VIEWPORTS) {
  await page.setViewportSize({ width: w, height: h });
  console.log(`\n########## ${vname} ##########`);

  for (const path of PAGES) {
    // Pace the walk: the office/resident groups are throttled at 60/min,
    // and ~40 pages × 2 viewports back-to-back would 429 (false failures).
    await page.waitForTimeout(1000);
    let status = 0;
    try {
      const resp = await page.goto(BASE + path, { waitUntil: 'load', timeout: 20000 });
      status = resp ? resp.status() : 0;
    } catch (e) {
      locks.push(`${path} @ ${w}px: failed to load (${String(e).slice(0, 80)})`);
      console.log(`  ${path.padEnd(34)} LOAD ERROR`);
      continue;
    }

    await page.waitForTimeout(200);

    if (status >= 400) {
      if (status === 403 || status === 404) {
        notes.push(`${path} @ ${w}px: HTTP ${status} (skipped — no access or no id)`);
        console.log(`  ${path.padEnd(34)} ${status} (skipped)`);
      } else {
        locks.push(`${path} @ ${w}px: HTTP ${status}`);
        console.log(`  ${path.padEnd(34)} HTTP ${status}`);
      }
      continue;
    }

    const m = await page.evaluate(probe);
    const hOver = m.scrollW > m.innerW;
    const short = m.small;

    if (hOver) {
      locks.push(`${path} @ ${w}px: horizontal overflow — ${m.overflowing.join(', ') || '?'}`);
      console.log(`  ${path.padEnd(34)} H-OVERFLOW  ${m.overflowing.join(', ')}`);
    } else if (short.length) {
      notes.push(`${path} @ ${w}px: ` + short.map((t) => `${t.tag}["${t.label}"]=${t.h}px (needs ${t.need})`).join(', '));
      console.log(`  ${path.padEnd(34)} under target  ` + short.map((t) => `${t.label}=${t.h}<${t.need}`).join(', '));
    } else {
      console.log(`  ${path.padEnd(34)} ok`);
    }
  }
}

await browser.close();

console.log('\n--- LOCK VIOLATIONS (horizontal overflow / load failures) ---');
if (!locks.length) console.log('none');
locks.forEach((l) => console.log('  FAIL  ' + l));

console.log('\n--- FINDINGS (controls under 44px / links under 24px; in-sentence links and native checkboxes exempt) ---');
if (!notes.length) console.log('none');
notes.forEach((n) => console.log('  NOTE  ' + n));

console.log(`\nRESULT: ${locks.length === 0 ? 'ALL PASS' : locks.length + ' LOCK VIOLATION(S)'}`);
process.exit(locks.length === 0 ? 0 : 1);
