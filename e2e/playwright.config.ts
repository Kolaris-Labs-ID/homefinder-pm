import { defineConfig } from '@playwright/test';

export default defineConfig({
  testDir: './tests',
  timeout: 30_000,
  fullyParallel: false, // Mencegah jalan bersamaan
  workers: 1,           // Kunci hanya 1 worker (anti rate-limit)
  retries: 1,
  use: {
    baseURL: process.env.E2E_BASE_URL ?? 'http://127.0.0.1:8000',
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
  },
  webServer: {
    command: process.env.CI
      ? 'php artisan serve --host=127.0.0.1 --port=8000'
      : 'composer run dev',
    url: 'http://127.0.0.1:8000/admin/login',
    cwd: '..',
    reuseExistingServer: !process.env.CI,
    timeout: 120_000,
    stdout: 'pipe',
    stderr: 'pipe',
  },
});