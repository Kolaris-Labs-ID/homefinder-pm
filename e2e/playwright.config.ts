import { defineConfig } from '@playwright/test';

export default defineConfig({
  testDir: './tests',
  timeout: 30_000,
  retries: 1,
  use: {
    baseURL: process.env.E2E_BASE_URL ?? 'http://127.0.0.1:8001',
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
  },
});
