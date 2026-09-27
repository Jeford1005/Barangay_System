import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

// Administrators and staff share one tab. The account's stored role still
// decides what it reaches; the tab only says which side of the door is being
// knocked on, so there are two choices, not three.
const ROLES = [
    { id: 'office', name: 'Admin/Staff' },
    { id: 'resident', name: 'Resident' },
];

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

test('the office group and resident are offered as tabs', async ({ page }) => {
    await page.goto('/login');

    await expect(page.getByRole('tab')).toHaveCount(2);

    for (const role of ROLES) {
        await expect(page.getByRole('tab', { name: role.name })).toHaveCount(1);
    }

    // Exactly one tab is selected, and it is the office group by default.
    await expect(page.locator('[role="tab"][aria-selected="true"]')).toHaveCount(1);
    await expect(page.getByRole('tab', { name: 'Admin/Staff' })).toHaveAttribute('aria-selected', 'true');
});

test('the tab group is operable from the keyboard', async ({ page }) => {
    await page.goto('/login');

    // A tablist is one tab stop; the arrow keys move within it.
    await page.getByRole('tab', { name: 'Admin/Staff' }).focus();
    await expect(page.getByRole('tab', { name: 'Admin/Staff' })).toBeFocused();

    await page.keyboard.press('ArrowRight');
    await expect(page.getByRole('tab', { name: 'Resident' })).toBeFocused();
    await expect(page.getByRole('tab', { name: 'Resident' })).toHaveAttribute('aria-selected', 'true');

    await page.keyboard.press('Home');
    await expect(page.getByRole('tab', { name: 'Admin/Staff' })).toHaveAttribute('aria-selected', 'true');
});

test('the chosen role is what actually reaches the server', async ({ page }) => {
    // A rejected login re-renders from the submitted input, so whichever tab
    // comes back selected is proof of what was posted — not just a DOM state.
    for (const role of ROLES) {
        await page.goto('/login');
        await page.getByRole('tab', { name: role.name }).click();
        await page.locator('#email').fill('nobody@example.com');
        await page.locator('#password').fill('wrong-password');
        await page.locator('form#login-form button[type="submit"]').click();

        await expect(page.getByRole('tab', { name: role.name })).toHaveAttribute('aria-selected', 'true');
        await expect(page.locator('#email')).toHaveValue('nobody@example.com');

        // The rejected password is never replayed back into the form.
        await expect(page.locator('#password')).toHaveValue('');
    }
});

test('only the selected role shows its sign-up line', async ({ page }) => {
    await page.goto('/login');

    const visibleCopies = async () =>
        page.locator('[data-role-copy]:not(.hidden)').evaluateAll((els) =>
            els.map((el) => el.getAttribute('data-role-copy')),
        );

    // Exactly one closing line at a time. Two at once contradicts itself:
    // "create an account" and "sign up with the office" are different asks.
    for (const role of ROLES) {
        await page.getByRole('tab', { name: role.name }).click();
        const shown = await visibleCopies();
        expect(shown, `role ${role.id} showed ${shown.length} closing lines`).toHaveLength(1);
        expect(shown[0].split(' ')).toContain(role.id);
    }
});

test('the role still reaches the server without scripting', async ({ browser }) => {
    // The tabs are inert without JS, so the <noscript> select must carry the
    // role on its own. This is the whole reason the hidden field ships unnamed.
    const context = await browser.newContext({ javaScriptEnabled: false });
    const page = await context.newPage();

    await page.goto('/login');
    await expect(page.getByRole('tab')).toHaveCount(0);

    await page.selectOption('#login-user-type-noscript', 'office');
    await page.locator('#email').fill('nobody@example.com');
    await page.locator('#password').fill('wrong-password');
    await page.locator('form#login-form button[type="submit"]').click();

    // The rejected login re-renders with the role that was actually posted.
    await expect(page.locator('#login-user-type-noscript')).toHaveValue('office');

    await context.close();
});

test('the role is posted exactly once', async ({ page }) => {
    await page.goto('/login');
    await page.getByRole('tab', { name: 'Resident' }).click();

    const posted = await page.locator('form#login-form').evaluate((form) => {
        const values = [...new FormData(form).entries()].filter(([key]) => key === 'user_type');
        return values.map(([, value]) => value);
    });

    // One field, one value. Two would leave the server guessing which the user
    // meant, and the switcher and the no-script fallback would both be posting.
    expect(posted).toEqual(['resident']);
});
