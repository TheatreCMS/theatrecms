import { defineConfig, devices } from '@playwright/test';

// The admin session saved by tests/e2e/auth.setup.ts, which every browser project starts with.
const ADMIN_STORAGE_STATE = 'playwright/.auth/admin.json';

/**
 * Point E2E_BASE_URL at the running site, e.g. https://dso-theatrecms.ddev.site under DDEV, and
 * set E2E_ADMIN_EMAIL and E2E_ADMIN_PASSWORD to an administrator account (see auth.setup.ts).
 */
export default defineConfig({
  testDir: './tests/e2e',
  timeout: 30_000,
  expect: {
    timeout: 5_000,
  },
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  use: {
    baseURL: process.env.E2E_BASE_URL ?? 'http://127.0.0.1:8080',
    // DDEV serves HTTPS with a local certificate.
    ignoreHTTPSErrors: true,
    trace: 'on-first-retry',
    viewport: {
      width: 1280,
      height: 720,
    },
  },
  projects: [
    {
      name: 'setup',
      testMatch: /.*\.setup\.ts/,
    },
    {
      name: 'chrome',
      dependencies: ['setup'],
      use: {
        ...devices['Desktop Chrome'],
        storageState: ADMIN_STORAGE_STATE,
      },
    },
    {
      name: 'firefox',
      dependencies: ['setup'],
      use: {
        ...devices['Desktop Firefox'],
        storageState: ADMIN_STORAGE_STATE,
      },
    },
  ],
});
