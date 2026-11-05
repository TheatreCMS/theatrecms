import { test as setup, expect } from '@playwright/test';

/**
 * Logs in once as an administrator and saves the session for the browser projects
 * (see `storageState` in playwright.config.ts). Credentials come from E2E_ADMIN_EMAIL and
 * E2E_ADMIN_PASSWORD; create the account with `bin/theatrecms user:create-admin`.
 */
// Must match `storageState` in playwright.config.ts.
const ADMIN_STORAGE_STATE = 'playwright/.auth/admin.json';

setup('log in as an administrator', async ({ page }) => {
  const email = process.env.E2E_ADMIN_EMAIL;
  const password = process.env.E2E_ADMIN_PASSWORD;

  if (!email || !password) {
    throw new Error('Set E2E_ADMIN_EMAIL and E2E_ADMIN_PASSWORD to an administrator account.');
  }

  await page.goto('/admin/login');
  await page.locator('#email').fill(email);
  await page.locator('#password').fill(password);
  await page.getByRole('button', { name: 'Sign in' }).click();

  await expect(page).toHaveURL(/\/admin$/);
  await page.context().storageState({ path: ADMIN_STORAGE_STATE });
});
