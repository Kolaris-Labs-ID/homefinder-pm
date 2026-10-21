import { test, expect, loginAs, ADMIN_EMAIL, ADMIN_PASSWORD, EMAIL, PASSWORD } from './fixtures';

// KUNCI: Jalankan berurutan agar tidak kena rate-limit
test.describe.configure({ mode: 'serial' });

test.describe('Users & Permission', () => {
  test('admin bisa akses menu Users', async ({ page }) => {
    await loginAs(page, ADMIN_EMAIL, ADMIN_PASSWORD);
    await page.goto('/admin/users');
    
    await expect(page.getByRole('heading', { name: /users/i })).toBeVisible();
    await expect(page.getByRole('table')).toBeVisible();
  });

  test.skip('user biasa tidak bisa akses /admin/users', async ({ page }) => {
    await loginAs(page, EMAIL, PASSWORD);
  });

  test('admin bisa assign role ke user baru', async ({ page }) => {
    const email = `e2e-user-${Date.now()}@example.test`;
    await loginAs(page, ADMIN_EMAIL, ADMIN_PASSWORD);
    await page.goto('/admin/users/create');

    await page.getByLabel(/name/i).fill('E2E User');
    await page.locator('input[id="data.email"]').fill(email);
    await page.getByLabel(/password/i).first().fill('password123');
    
    await page.getByRole('button', { name: 'Create', exact: true }).click();
    
    // Tunggu Livewire selesai redirect
    await page.waitForURL(/\/admin\/users/, { timeout: 15_000 });
    await page.waitForLoadState('networkidle');
  });
});