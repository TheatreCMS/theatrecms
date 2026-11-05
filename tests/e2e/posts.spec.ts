import { test, expect, type Page } from '@playwright/test';

/**
 * Posts are entries of the core `post` type, served by the generic EntryController, so this
 * covers the entry admin screens (THE-113) as well as the post frontend. Runs as the admin
 * saved by auth.setup.ts. Each run creates its own uniquely titled post and deletes it.
 */
test.describe('Posts', () => {
  test('an admin creates, edits, views and deletes a post', async ({ page }, testInfo) => {
    const title = `E2E post ${testInfo.project.name} ${Date.now()}`;
    const editedTitle = `${title} edited`;

    // Create: the form submits through HTMX, and success redirects to the edit screen.
    await page.goto('/admin/posts/create');
    await page.locator('#title').fill(title);
    await page.locator('#status').selectOption('published');
    await page.locator('#excerpt').fill('A short summary.');
    await typeIntoEditor(page, 'Body text written by Playwright.');
    await page.getByRole('button', { name: 'Create Post' }).click();
    await expect(page).toHaveURL(/\/admin\/posts\/edit\/\d+$/);

    // Edit: saving swaps a success alert into the page.
    await page.locator('#title').fill(editedTitle);
    await page.getByRole('button', { name: 'Save Changes' }).click();
    await expect(page.locator('#flash-messages [role="alert"]')).toHaveClass(/bg-green-50/);
    const slug = await page.locator('#slug').inputValue();

    // Frontend: the published post is served at /posts/{slug} with its content.
    await page.goto(`/posts/${slug}`);
    await expect(page.locator('body')).toContainText(editedTitle);
    await expect(page.locator('body')).toContainText('Body text written by Playwright.');

    // List and delete: the delete button asks for confirmation, then swaps the list.
    await page.goto(`/admin/posts?q=${encodeURIComponent(editedTitle)}`);
    const row = page.locator('tr', { hasText: editedTitle });
    await expect(row).toHaveCount(1);
    page.once('dialog', (dialog) => dialog.accept());
    await row.getByRole('button', { name: 'Delete' }).click();
    await expect(row).toHaveCount(0);

    const response = await page.goto(`/posts/${slug}`);
    expect(response?.status()).toBe(404);
  });
});

/**
 * Types into the EditorJS field's first paragraph block, once the editor has rendered it.
 */
async function typeIntoEditor(page: Page, text: string): Promise<void> {
  const paragraph = page.locator('#editorjs .ce-paragraph').first();
  await paragraph.click();
  await page.keyboard.type(text);
}
