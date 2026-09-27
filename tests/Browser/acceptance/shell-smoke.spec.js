import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

test('public login shell is reachable and has no serious axe violations', async ({ page }) => {
    const response = await page.goto('/login');
    expect(response?.status()).toBe(200);
    await expect(page.locator('main')).toBeVisible();

    const results = await new AxeBuilder({ page }).analyze();
    const serious = results.violations.filter((violation) => ['serious', 'critical'].includes(violation.impact));
    expect(serious).toEqual([]);
});

test('health endpoint is available to the browser smoke suite', async ({ page }) => {
    const response = await page.goto('/up');
    expect(response?.status()).toBe(200);
});

test('the card is framed, not clipped, on a short laptop window', async ({ page }) => {
    // 1360x578 is a real page area once browser chrome is subtracted. The card
    // used to overflow this by about 11px, so the bottom edge sat on the fold
    // and looked like a page that refused to scroll.
    await page.setViewportSize({ width: 1360, height: 578 });
    await page.goto('/login');

    // Wait for the card, not for network quiet. The page pulls its webfont from
    // a third-party host, so `networkidle` can block on that request and time
    // the test out for reasons that have nothing to do with the layout.
    const card = page.locator('main > div');
    await expect(card).toBeVisible();

    const box = await card.boundingBox();
    const viewport = page.viewportSize();

    expect(box.y, 'no air above the card').toBeGreaterThan(0);
    expect(box.y + box.height, 'card is cut off at the bottom').toBeLessThan(viewport.height);

    const { scrollHeight, clientHeight } = await page.evaluate(() => ({
        scrollHeight: document.documentElement.scrollHeight,
        clientHeight: document.documentElement.clientHeight,
    }));
    expect(scrollHeight, 'nothing should be left to scroll').toBeLessThanOrEqual(clientHeight);
});

test('a cramped window can still scroll the whole card into view', async ({ page }) => {
    // Below the card's own height the page must scroll far enough to reveal the
    // submit button and the closing line, not strand them under the fold.
    await page.setViewportSize({ width: 1024, height: 420 });
    await page.goto('/login');

    // Same reason as above: wait for the card rather than for network quiet.
    const submit = page.locator('form#login-form button[type="submit"]');
    await expect(submit).toBeVisible();

    await page.evaluate(() => window.scrollTo(0, document.documentElement.scrollHeight));
    await page.waitForTimeout(100);

    const viewport = page.viewportSize();
    for (const selector of ['form#login-form button[type="submit"]', 'main']) {
        const box = await page.locator(selector).last().boundingBox();
        expect(box.y + box.height, `${selector} is not reachable`).toBeLessThanOrEqual(viewport.height + 1);
    }
});
