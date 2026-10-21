import { test as base, expect, Page } from '@playwright/test';

export const EMAIL = process.env.E2E_EMAIL || 'test@acc.local';
export const PASSWORD = process.env.E2E_PASSWORD || 'password123';

export const test = base.extend({
  page: async ({ page }, use) => {
    if (!EMAIL || !PASSWORD) test.skip(true, 'Kredensial E2E tidak diset');
    await use(page);
  },
});

export { expect };

// Fungsi bantuan untuk login
export async function login(page: Page) {
  await page.goto('/admin/login');
  await page.getByLabel(/email address/i).fill(EMAIL);
  await page.getByLabel(/password/i).fill(PASSWORD);
  await page.getByRole('button', { name: /sign in/i }).click();
  await expect(page).toHaveURL(/\/admin(?!\/login)/);
}