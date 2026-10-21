import { test, expect } from '@playwright/test';

const EMAIL = process.env.E2E_EMAIL;
const PASSWORD = process.env.E2E_PASSWORD;

test('halaman login menampilkan branding Homefinder', async ({ page }) => {
  await page.goto('/admin/login');
  await expect(page).toHaveTitle(/Homefinder/);
  await expect(page.getByText(/partnershub/i)).toHaveCount(0);
});

test('login gagal dengan password salah', async ({ page }) => {
  test.skip(!EMAIL, 'set E2E_EMAIL');
  await page.goto('/admin/login');
  await page.getByLabel('Email address').fill(EMAIL!);
  await page.getByLabel('Password').fill('salah-total');
  await page.getByRole('button', { name: 'Sign in' }).click();
  await expect(page.getByText(/do not match/i)).toBeVisible();
});

test('login berhasil masuk dashboard', async ({ page }) => {
  test.skip(!EMAIL || !PASSWORD, 'set E2E_EMAIL dan E2E_PASSWORD');
  await page.goto('/admin/login');
  await page.getByLabel('Email address').fill(EMAIL!);
  await page.getByLabel('Password').fill(PASSWORD!);
  await page.getByRole('button', { name: 'Sign in' }).click();
  await expect(page).toHaveURL(/\/admin(?!\/login)/);
});
