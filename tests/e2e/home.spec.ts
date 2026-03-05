import { test, expect } from '@playwright/test';

test.describe('Public routes', () => {
  test('home page renders the greeting', async ({ page }) => {
    const response = await page.goto('/');
    await expect(response).toBeTruthy();
    await expect(page.locator('body')).toContainText('hello world');
  });
});
