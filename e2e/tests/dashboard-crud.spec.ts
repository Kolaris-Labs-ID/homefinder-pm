import { test, expect, login } from './fixtures';

test.describe('CRUD Department', () => {
  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test('berhasil membuat department', async ({ page }) => {
    const deptName = `E2E Dept ${Date.now()}`;

    await page.goto('/admin/departments/create');
    
    await page.getByLabel(/name/i).fill(deptName);
    if (await page.getByLabel(/code/i).count() > 0) {
      await page.getByLabel(/code/i).fill(`DEPT-${Date.now()}`);
    }

    await page.getByRole('button', { name: 'Create', exact: true }).click();
    
    // 1. Tunggu sampai Filament selesai memproses dan me-redirect URL
    await page.waitForURL(/\/admin\/departments/, { timeout: 15_000 });
    
    // 2. Tunggu jaringan stabil (Livewire selesai)
    await page.waitForLoadState('networkidle');

    // 3. Pindah ke halaman list
    await page.goto('/admin/departments');

    // 4. Pastikan data muncul
    await expect(page.getByRole('cell', { name: deptName })).toBeVisible({ timeout: 10_000 });
  });
});