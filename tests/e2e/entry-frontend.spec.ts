import { test, expect, type Page } from '@playwright/test';

/**
 * THE-114's "done when": an entry is reachable at its URL, appears in its term archive and in a
 * menu, all resolving correctly. Posts are entries of the core `post` type, and `post_category`
 * is the core taxonomy on them, served at /category/{slug}. Runs as the admin saved by
 * auth.setup.ts. Each run creates its own uniquely named category, post and menu, and deletes them.
 *
 * A menu only renders on the site when it's assigned to a theme location, and a location holds
 * one menu, so each browser project uses its own location of the default theme.
 */
const MENU_LOCATIONS: Record<string, string> = {
  chrome: 'Primary Navigation',
  firefox: 'Footer Menu',
};

test.describe('Entry frontend', () => {
  test('an entry resolves at its URL, in its term archive and in a menu', async ({ page }, testInfo) => {
    const location = MENU_LOCATIONS[testInfo.project.name];
    test.skip(location === undefined, `No menu location assigned to project ${testInfo.project.name}.`);

    const run = `${testInfo.project.name} ${Date.now()}`;
    const termName = `E2E category ${run}`;
    const postTitle = `E2E entry ${run}`;
    const menuPrefix = `E2E menu ${testInfo.project.name}`;
    const menuName = `${menuPrefix} ${Date.now()}`;

    // A menu left by an aborted run would still hold this project's location.
    await deleteMenusNamed(page, menuPrefix);

    try {
      // Term: adding one swaps the term list, which shows its generated slug.
      await page.goto('/admin/taxonomies/post_category');
      await page.locator('#term-name').fill(termName);
      await page.getByRole('button', { name: 'Add category' }).click();
      const termRow = page.locator('#termListContainer tr', { hasText: termName });
      await expect(termRow).toHaveCount(1);
      const termSlug = (await termRow.locator('td').first().innerText()).trim();

      // Entry: a published post filed under the term.
      await page.goto('/admin/posts/create');
      await page.locator('#title').fill(postTitle);
      await page.locator('#status').selectOption('published');
      await page.getByRole('checkbox', { name: termName }).check();
      await page.getByRole('button', { name: 'Create Post' }).click();
      await expect(page).toHaveURL(/\/admin\/posts\/edit\/\d+$/);
      const postSlug = await page.locator('#slug').inputValue();
      const postUrl = `/posts/${postSlug}`;

      // 1. Reachable at its URL.
      const single = await page.goto(postUrl);
      expect(single?.status()).toBe(200);
      await expect(page.getByRole('heading', { level: 1 })).toHaveText(postTitle);

      // 2. Listed in its term archive, linking back to its URL.
      const archive = await page.goto(`/category/${termSlug}`);
      expect(archive?.status()).toBe(200);
      await expect(page.getByRole('link', { name: postTitle })).toHaveAttribute('href', postUrl);

      // 3. In a menu: create one at this project's location and link the post from the editor.
      await page.goto('/admin/menus/create');
      await page.locator('#name').fill(menuName);
      await page.locator('#location').fill(location);
      await page.getByRole('button', { name: 'Create Menu' }).click();
      await expect(page).toHaveURL(/\/admin\/menus\/edit\/\d+$/);
      // The editor wires up its controls once the page has loaded.
      await page.waitForLoadState('load');

      await page.locator('#link-target-type').selectOption('post');
      await page.locator('#link-target-search').fill(postTitle);
      const panel = page.locator('[data-link-target-panel="post"]');
      await panel.locator('[data-link-target-item]', { hasText: postTitle }).getByRole('button', { name: 'Add' }).click();

      const menuItem = page.locator('#menu-item-tree li.menu-item');
      await expect(menuItem).toHaveCount(1);
      await expect(menuItem).toContainText('Post');
      await page.getByRole('button', { name: 'Save Menu' }).click();
      await expect(page.locator('#flash-messages [role="alert"]')).toHaveClass(/bg-green-50/);

      // Reloaded from the database, the item resolves to the post rather than showing as orphaned.
      await page.reload();
      await expect(page.locator('#menu-item-tree li.menu-item input')).toHaveValue(postTitle);
      await expect(page.locator('#menu-item-tree')).not.toContainText('Linked content missing');

      // The site renders the menu with a link to the post's URL.
      await page.goto('/');
      await expect(page.locator('ul.menu').getByRole('link', { name: postTitle })).toHaveAttribute('href', postUrl);
    } finally {
      await deleteMenusNamed(page, menuPrefix);
      await deleteListRow(page, `/admin/posts?q=${encodeURIComponent(postTitle)}`, postTitle);
      await deleteListRow(page, `/admin/taxonomies/post_category?q=${encodeURIComponent(termName)}`, termName);
    }
  });
});

async function deleteMenusNamed(page: Page, prefix: string): Promise<void> {
  await page.goto('/admin/menus');
  const rows = page.locator('#menuListContainer tr', { hasText: prefix });

  while ((await rows.count()) > 0) {
    const before = await rows.count();
    page.once('dialog', (dialog) => dialog.accept());
    await rows.first().getByRole('button', { name: 'Delete' }).click();
    await expect(rows).toHaveCount(before - 1);
  }
}

/**
 * Deletes the admin list row containing $text, if there is one. Delete buttons confirm first,
 * then swap the list.
 */
async function deleteListRow(page: Page, listUrl: string, text: string): Promise<void> {
  await page.goto(listUrl);
  const row = page.locator('tr', { hasText: text });

  if ((await row.count()) === 0) {
    return;
  }

  page.once('dialog', (dialog) => dialog.accept());
  await row.getByRole('button', { name: 'Delete' }).click();
  await expect(row).toHaveCount(0);
}
