import { test, expect } from './fixtures';

test.describe('Admin Login', () => {
  test('halaman login menampilkan branding Homefinder', async ({ page }) => {
    await page.goto('/admin/login');
    await expect(page).toHaveTitle(/Homefinder/);
    await expect(page.getByText(/partnershub/i)).toHaveCount(0);
  });

  test('login gagal dengan password salah', async ({ page }) => {
    await page.goto('/admin/login');
    await page.getByLabel(/email address/i).fill('salah@test.local');
    await page.getByLabel(/password/i).fill('salah-total');
    await page.getByRole('button', { name: /sign in/i }).click();
    await expect(page.getByText(/do not match/i)).toBeVisible();
  });

  test('login berhasil masuk dashboard', async ({ page }) => {
    await page.goto('/admin/login');
    await page.getByLabel(/email address/i).fill(process.env.E2E_EMAIL || 'test@acc.local');
    await page.getByLabel(/password/i).fill(process.env.E2E_PASSWORD || 'password123');
    await page.getByRole('button', { name: /sign in/i }).click();
    await expect(page).toHaveURL(/\/admin(?!\/login)/);
  });
});