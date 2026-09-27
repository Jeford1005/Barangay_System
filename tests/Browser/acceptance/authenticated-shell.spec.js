import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

const email = process.env.E2E_ADMIN_EMAIL;
const password = process.env.E2E_ADMIN_PASSWORD;

test.describe('authenticated admin shell', () => {
    test.skip(!email || !password, 'Set E2E_ADMIN_EMAIL and E2E_ADMIN_PASSWORD for authenticated acceptance tests.');

    test('admin can sign in and reach the dashboard without serious axe violations', async ({ page }) => {
        await page.goto('/login');
        await page.locator('#email').fill(email);
        await page.locator('#password').fill(password);
        await page.getByRole('button', { name: /sign in|log in/i }).click();
        await expect(page).toHaveURL(/dashboard/);
        await expect(page.locator('main')).toBeVisible();

        const results = await new AxeBuilder({ page }).analyze();
        const serious = results.violations.filter((violation) => ['serious', 'critical'].includes(violation.impact));
        expect(serious).toEqual([]);
    });
});
