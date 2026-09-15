import { test, expect } from '@playwright/test';

// Note: unlike home.spec.ts, these specs don't seed their own Event fixtures —
// there's no existing seeding mechanism in tests/e2e/tests/Includes to build on
// (see tests/Includes/TestCase.php). Assertions here are limited to structure/
// behavior that doesn't depend on specific event data existing in the DB the
// suite runs against. A follow-up that adds fixture seeding could extend this
// to cover clicking a real event and verifying its modal content.

test.describe('Calendar page', () => {
  test('renders the month grid', async ({ page }) => {
    const response = await page.goto('/calendar');
    await expect(response).toBeTruthy();
    await expect(page.locator('#calendar-mount .fc-daygrid')).toBeVisible();
  });

  test('month navigation updates the visible month', async ({ page }) => {
    await page.goto('/calendar');
    const title = page.locator('.fc-toolbar-title');
    const initialTitle = await title.textContent();

    await page.locator('.fc-next-button').click();
    await expect(title).not.toHaveText(initialTitle ?? '');
  });

  test('toggling to list view switches visible panels without a full reload', async ({ page }) => {
    await page.goto('/calendar');

    await expect(page.locator('#calendar-mount')).toBeVisible();

    await page.getByRole('button', { name: 'List' }).click();

    await expect(page.locator('#calendar-mount')).toBeHidden();
    await expect(page.locator('#event-list-content')).toBeVisible();
  });
});
