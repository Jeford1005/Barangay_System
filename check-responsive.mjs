// Responsive audit across device viewports. Read-only: loads public pages,
// measures overflow / fold / tap targets, then closes. Never submits anything.
//
// The design lock is "no scrollbar when the content already fits". Scrolling
// is CORRECT once the card is taller than the viewport (landscape, 200% zoom)
// - locking that away would hide content. So a failure is specifically
// scrollable *empty* space: page taller than the viewport while the card
// still fits inside it.
import { chromium } from 'playwright';

const BASE = process.env.BROWSER_BASE_URL || 'http://127.0.0.1:8000';

const VIEWPORTS = [
  ['Tiny 5/SE1  320x568', 320, 568],
  ['Galaxy S20  360x640', 360, 640],
  ['iPhone SE   375x667', 375, 667],
  ['iPhone 14   390x844', 390, 844],
  ['iPhone XR   414x896', 414, 896],
  ['SE landscape 667x375', 667, 375],
  ['14 landscape 844x390', 844, 390],
  ['iPad        768x1024', 768, 1024],
  ['iPad land  1024x768', 1024, 768],
  ['Laptop     1280x800', 1280, 800],
  ['Desktop    1440x900', 1440, 900],
  ['Full HD    1920x1080', 1920, 1080],
  ['200% zoom   640x400', 640, 400], // = 1280x800 at browser zoom 200%
];

const PAGES = (process.env.PAGES || '/login,/register,/forgot-password,/reset-password').split(',');

const browser = await chromium.launch();
const locks = [];   // design-lock violations -> failing
const notes = [];   // softer findings, reported not failed

const probe = () => {
  const doc = document.documentElement;
  const main = document.querySelector('main');
  const wrapper = document.querySelector('main > div');
  // The wrapper holds the card *and* the footnote under it (mt-6 + 16px), so
  // it - not the card - is what has to fit the fold.
  const card = document.querySelector('main > div > div') || wrapper;

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

  return {
    innerW: window.innerWidth,
    innerH: window.innerHeight,
    scrollW: doc.scrollWidth,
    scrollH: Math.max(doc.scrollHeight, document.body.scrollHeight),
    cardH: card ? Math.round(card.getBoundingClientRect().height) : null,
    contentH: wrapper ? Math.round(wrapper.getBoundingClientRect().height) : null,
    mainH: main ? Math.round(main.getBoundingClientRect().height) : null,
    overflowing: [...new Set(overflowing)].slice(0, 6),
  };
};

const smallTargets = (page) => page.evaluate(() => {
  const out = [];
  for (const el of document.querySelectorAll('input:not([type="hidden"]), button, a, select')) {
    const r = el.getBoundingClientRect();
    if (r.width < 2 || r.height < 2) continue; // hidden
    if (r.height < 44) {
      out.push(`${el.tagName.toLowerCase()}[${(el.id || el.textContent || '').trim().replace(/\s+/g, ' ').slice(0, 24)}]=${Math.round(r.height)}px`);
    }
  }
  return [...new Set(out)];
});

for (const path of PAGES) {
  console.log(`\n=== ${path} ===`);
  let targetReported = false;

  for (const [name, w, h] of VIEWPORTS) {
    const ctx = await browser.newContext({ viewport: { width: w, height: h } });
    const page = await ctx.newPage();
    await page.goto(BASE + path, { waitUntil: 'load' });
    await page.waitForTimeout(200);

    const m = await page.evaluate(probe);

    // Page scrolls although the content fits -> empty space is scrollable.
    const contentH = m.contentH ?? m.cardH;
    const contentFits = contentH !== null && contentH + 24 <= m.innerH; // main's py-3
    const scrollIntoEmpty = m.scrollH > m.innerH + 1 && contentFits;
    const hOver = m.scrollW > m.innerW;
    const scrollReason = m.scrollH > m.innerH + 1
      ? (scrollIntoEmpty ? `SCROLLS +${m.scrollH - m.innerH}px (EMPTY!)` : `scrolls +${m.scrollH - m.innerH}px (content taller)`)
      : 'fits';

    console.log(
      `${name} | ${hOver ? 'H-OVERFLOW' : 'h-ok'} | ${scrollReason} | content ${contentH}px / vp ${m.innerH}px`
    );

    if (hOver) locks.push(`${path} @ ${w}x${h}: horizontal overflow — ${m.overflowing.join(', ') || '?'}`);
    if (scrollIntoEmpty) locks.push(`${path} @ ${w}x${h}: scrolls ${m.scrollH - m.innerH}px of empty space although content fits (${contentH}px + py-3 in ${m.innerH}px)`);

    // Tap targets: only meaningful on a touch-sized viewport, and only worth
    // printing once per page.
    if (!targetReported && w <= 390 && w >= 320) {
      const small = await smallTargets(page);
      if (small.length) {
        targetReported = true;
        notes.push(`${path} @ ${w}px: tap targets under 44px — ${small.join(', ')}`);
        console.log(`        <44px: ${small.join(', ')}`);
      }
    }

    await ctx.close();
  }
}

// ---- login with the reset dialog open ----------------------------------
console.log('\n=== /login with reset dialog open ===');
for (const [name, w, h] of VIEWPORTS.filter(([, vw]) => vw <= 844)) {
  const ctx = await browser.newContext({ viewport: { width: w, height: h } });
  const page = await ctx.newPage();
  await page.goto(BASE + '/login', { waitUntil: 'load' });
  await page.evaluate(() => document.querySelector('[data-open-forgot]').click());
  await page.waitForTimeout(400);

  const m = await page.evaluate(() => {
    const panel = document.querySelector('#forgot-panel');
    const dialog = document.getElementById('forgot-modal');
    return {
      innerH: window.innerHeight,
      scrollH: Math.max(document.documentElement.scrollHeight, document.body.scrollHeight),
      visible: !!dialog && !dialog.classList.contains('hidden'),
      panelH: panel ? Math.round(panel.getBoundingClientRect().height) : null,
      panelScrollable: panel ? panel.scrollHeight > panel.clientHeight + 1 : null,
      panelTop: panel ? Math.round(panel.getBoundingClientRect().top) : null,
      panelBottom: panel ? Math.round(panel.getBoundingClientRect().bottom) : null,
      inputH: (() => { const i = document.getElementById('forgot-email'); return i ? Math.round(i.getBoundingClientRect().height) : null; })(),
    };
  });

  const bodyScroll = m.scrollH > m.innerH + 1;
  const clipped = m.panelTop !== null && (m.panelTop < 0 || m.panelBottom > m.innerH);
  console.log(
    `${name} | visible=${m.visible} | panel ${m.panelH}px (internal scroll: ${m.panelScrollable}) | ` +
    `${bodyScroll ? `body +${m.scrollH - m.innerH}px` : 'body fits'} | email ${m.inputH}px`
  );
  if (clipped) locks.push(`dialog @ ${w}x${h}: panel clipped (top ${m.panelTop}, bottom ${m.panelBottom}, vh ${m.innerH})`);
  if (m.inputH !== null && m.inputH < 44) notes.push(`dialog @ ${w}px: email field only ${m.inputH}px tall`);

  await ctx.close();
}

await browser.close();

console.log('\n--- LOCK VIOLATIONS (must fix) ---');
if (!locks.length) console.log('none');
locks.forEach((l) => console.log('  FAIL  ' + l));

console.log('\n--- FINDINGS (reported, not a lock) ---');
if (!notes.length) console.log('none');
notes.forEach((n) => console.log('  NOTE  ' + n));

console.log(`\nRESULT: ${locks.length === 0 ? 'ALL PASS' : locks.length + ' LOCK VIOLATION(S)'}`);
process.exit(locks.length === 0 ? 0 : 1);
