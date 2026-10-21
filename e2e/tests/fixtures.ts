import { test as base, expect, Page } from '@playwright/test';

export const EMAIL = process.env.E2E_EMAIL || 'test@acc.local';
export const PASSWORD = process.env.E2E_PASSWORD || 'password123';

export const ADMIN_EMAIL = process.env.E2E_ADMIN_EMAIL || EMAIL;
export const ADMIN_PASSWORD = process.env.E2E_ADMIN_PASSWORD || PASSWORD;

export const test = base.extend({
  page: async ({ page }, use) => {
    if (!EMAIL || !PASSWORD) test.skip(true, 'Kredensial E2E tidak diset');
    await use(page);
  },
});

export { expect };

export async function login(page: Page) {
  await page.waitForTimeout(3000); 
  await page.goto('/admin/login');
  await page.getByLabel(/email address/i).fill(EMAIL);
  await page.getByLabel(/password/i).fill(PASSWORD);
  await page.getByRole('button', { name: /sign in/i }).click();
  await expect(page).toHaveURL(/\/admin(?!\/login)/);
}

export async function loginAs(page: Page, email: string, password: string) {
  await page.waitForTimeout(3000);
  await page.goto('/admin/login');
  await page.getByLabel(/email address/i).fill(email);
  await page.getByLabel(/password/i).fill(password);
  await page.getByRole('button', { name: /sign in/i }).click();
  await expect(page).toHaveURL(/\/admin(?!\/login)/);
}