// Tap-target audit (NFR6): every visible interactive control, in every state.
// Read-only. Reports controls under 44px; in-sentence links are exempt per
// WCAG 2.5.8's inline exception and are flagged as such for a human to judge.
import { chromium } from 'playwright';

const BASE = process.env.BROWSER_BASE_URL || 'http://127.0.0.1:8000';
const W = 375;
const H = 667;

const browser = await chromium.launch();

const scan = (page) => page.evaluate(() => {
  const out = [];
  for (const el of document.querySelectorAll('input:not([type="hidden"]), button, a, select')) {
    const r = el.getBoundingClientRect();
    if (r.width < 2 || r.height < 2) continue; // display:none / not rendered
    if (r.height < 44) {
      out.push({
        tag: el.tagName.toLowerCase(),
        label: (el.id || el.textContent || '').trim().replace(/\s+/g, ' ').slice(0, 30),
        h: Math.round(r.height),
        w: Math.round(r.width),
        // Is the target surrounded by sentence text? (WCAG inline exception)
        inSentence: !!(el.parentElement && el.parentElement.childElementCount > 0 &&
          [...el.parentElement.childNodes].some(
            (n) => n.nodeType === 3 && n.textContent.trim().length > 3
          )),
      });
    }
  }
  return out;
});

const STATES = [
  { path: '/login', name: 'login / office role (default)' },
  { path: '/login', name: 'login / resident role', act: async (p) => { await p.click('[data-role-tab="resident"]'); await p.waitForTimeout(150); } },
  { path: '/login', name: 'login / forgot-password dialog', act: async (p) => { await p.evaluate(() => document.querySelector('[data-open-forgot]').click()); await p.waitForTimeout(350); } },
  { path: '/login', name: 'login / register dialog', act: async (p) => {
      await p.evaluate(() => document.querySelector('[data-role-tab="resident"]').click());
      await p.waitForTimeout(150);
      await p.evaluate(() => document.querySelector('[data-open-register]').click());
      await p.waitForTimeout(350);
    } },
  { path: '/register', name: 'register page' },
  { path: '/forgot-password', name: 'forgot-password page' },
  { path: '/reset-password', name: 'reset-password page' },
];

let bad = 0;

for (const s of STATES) {
  const ctx = await browser.newContext({ viewport: { width: W, height: H } });
  const page = await ctx.newPage();
  await page.goto(BASE + s.path, { waitUntil: 'load' });
  await page.waitForTimeout(250);
  if (s.act) await s.act(page);

  const hits = await scan(page);
  console.log(`\n=== ${s.name} (${s.path} @ ${W}x${H}) ===`);
  if (!hits.length) {
    console.log('  PASS  every visible control >= 44px');
  } else {
    for (const t of hits) {
      const exempt = t.inSentence ? '  [inline in sentence - WCAG 2.5.8 exempt]' : '';
      if (!t.inSentence) bad++;
      console.log(`  ${t.inSentence ? 'NOTE' : 'FAIL'}  ${t.tag} "${t.label}" = ${t.h}px (w${t.w})${exempt}`);
    }
  }
  await ctx.close();
}

await browser.close();
console.log(`\nRESULT: ${bad === 0 ? 'ALL PASS (non-exempt controls >= 44px)' : bad + ' NON-EXEMPT CONTROL(S) UNDER 44px'}`);
process.exit(bad === 0 ? 0 : 1);
