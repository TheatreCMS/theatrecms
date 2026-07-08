# WordPress-Style Menu System for TheatreCMS

## Context

TheatreCMS currently has no way for admins to manage site navigation — the header nav (`templates/partials/header.html.twig`, or theme-specific `www/themes/avlt/templates/partials/nav.html.twig`, which is currently an **empty placeholder file**) is hardcoded. The goal is a WordPress-style menu builder: admins create named menus, add items that link to internal content (Pages, Posts, Productions) or custom URLs, arrange them into a nested drag-and-drop tree, and assign a menu to a "location" that the active theme declares (mirroring WP's `register_nav_menu()` / `wp_nav_menu()`).

This fits naturally into the existing architecture: TheatreCMS already has a WordPress-inspired theme system (`src/Theme/ThemeManager.php` loads a theme's `functions.php` like WP does, and `src/Theme/HookManager.php` is a WP-style `add_filter`/`apply_filters` system exposed via global functions in `app/hooks.php`). The menu system extends this same pattern with a `register_menu_location()` global function and a `render_menu()` Twig function.

**Stack** (confirmed by reading the code — this is Slim Framework 4 + Doctrine ORM 3 + Twig, *not* Laravel): PHP-DI container, Twig views via `slim/twig-view`, HTMX for progressive enhancement, Tailwind (CDN) + Flowbite for admin UI styling. No JS framework and no drag-and-drop library exist yet — one small addition (SortableJS via CDN `<script>`, matching how HTMX/Tailwind/Flowbite are already loaded) is needed for the nested tree editor.

**Confirmed decisions** (from user):
1. Menu items can link to Pages, Posts, Productions, or a custom URL+label.
2. Full nested drag-and-drop tree, unlimited depth (closest to real WordPress UX).
3. Menu locations are theme-registered (theme's `functions.php` declares available locations; admin picks from that list).

## Data Model

Doctrine has no native polymorphic association without extra packages, so `MenuItem` uses explicit `link_type` (enum) + `target_id` (soft FK, no DB constraint since it can point at 3 different tables) + `custom_url` columns rather than three separate nullable FKs.

- **`src/Enums/MenuItemType.php`** — backed string enum `PAGE|POST|PRODUCTION|CUSTOM`, same shape as existing `src/Enums/PostStatus.php` (`label()`/`labels()` helpers).
- **`src/Models/Menu.php`** — `id`, `name`, `location` (nullable string, theme-registered slug, **not** a FK — locations are runtime-registered, not a DB table), `createdAt`/`modifiedAt` (same `DateTimeImmutable` + `touchModified()` pattern as `Page`/`Post`), `items` (`OneToMany` to `MenuItem`, `mappedBy: 'menu'`, `cascade: ['persist','remove']`, `orphanRemoval: true`, ordered by `position` — mirrors `Work::$workCreators`'s `orphanRemoval` pattern). Does **not** extend `ModelBase` — a menu isn't URL-addressable content, so it doesn't need a public unique slug.
- **`src/Models/MenuItem.php`** — `id`, `menu` (`ManyToOne`), `parent` (self-referential nullable `ManyToOne`, `onDelete: 'CASCADE'` at the DB level so deep nested branches cascade-delete), `children` (`OneToMany mappedBy: 'parent'`, `orphanRemoval: true`, `#[OrderBy(['position' => 'ASC'])]`), `position` (int), `label` (nullable override — if null, resolved from the linked entity at render time), `linkType` (`MenuItemType` enum column via `enumType:`, same pattern as `Post::$status`), `targetId` (nullable int), `customUrl` (nullable, length 2048).
- **`src/Menus/MenuItemResolver.php`** (new `TheatreCMS\Menus` namespace) — resolves a `MenuItem`'s URL and display label by looking up `PageRepository`/`PostRepository`/`ProductionRepository` by `targetId`. Production URLs use the real existing route shape `/seasons/{seasonSlug}/{productionSlug}` (verified in `app/routes/frontend/seasons.php`); Page URLs use `/{slug}`. Returns `null` from `resolveUrl()` if the target was deleted (orphaned) — callers skip rendering that item (and don't recurse into its children) on the public site, but the admin tree editor still shows orphaned items with a "Linked content missing" badge and a Remove button.

## Menu Location Registry (theme-registered locations)

- **`src/Theme/MenuLocationRegistry.php`** — singleton-style service (`setInstance`/`getInstance`, same shape as `src/Theme/HookManager.php`) holding a `slug => label` map via `register()`/`all()`/`has()`/`label()`.
- **`app/menu-locations.php`** (new, alongside existing `app/hooks.php`) — defines the global `register_menu_location(string $slug, string $label): void` function that themes call, delegating to `MenuLocationRegistry::getInstance()->register(...)`.
- **`app/bootstrap.php`** — add `require_once APP_ROOT . '/app/menu-locations.php';` next to the existing `require_once APP_ROOT . '/app/hooks.php';`, and `MenuLocationRegistry::setInstance($container->get(MenuLocationRegistry::class));` next to the existing `HookManager::setInstance($hookManager);` call. This runs before `ThemeManager::loadFunctions()` (called lazily inside the `Twig::class` factory), so the registry is ready when a theme's `functions.php` calls `register_menu_location()`.
- Example usage in `www/themes/avlt/functions.php` (currently empty): `register_menu_location('primary', 'Primary Navigation'); register_menu_location('footer', 'Footer Menu');`
- The admin Menu create/edit form's location `<select>` is populated from `MenuLocationRegistry::all()`, injected into `MenuController` as a constructor dependency.
- If a menu's stored `location` no longer matches any registered slug (theme changed), no destructive action is taken — the admin list/edit UI shows an "(inactive location)" indicator, and `render_menu()` simply won't find that menu since lookup is location-driven, not the other way around.

## Twig Rendering

- **`src/Twig/MenuExtension.php`** — registered exactly like the existing `EditorJsExtension` (`src/Twig/EditorJsExtension.php`, added via `$twig->addExtension(...)` inside the `Twig::class` factory in `ServiceRegistrar`). Exposes two Twig functions:
  - `render_menu(location)` — looks up the `Menu` assigned to that location via `MenuRepository::findByLocation()`; if none, returns empty output (graceful no-op, like WP with no fallback callback). Otherwise recursively renders nested `<ul>/<li>` via a reusable core template.
  - `get_menu(location)` — returns the resolved nested array (`{label, url, children}`) for themes that want full control over markup, without HTML.
- **`templates/partials/_menu_items.html.twig`** (new, core template, self-including recursive partial) renders the nested list, skipping items with a `null` resolved URL (orphaned target) and not recursing into their children.

## Controller, Routes, and the Tree-Save Flow

- **`src/Controllers/MenuController.php`** — standard CRUD (`index`, `create`, `store`, `edit`, `destroy`) following `src/Controllers/PageController.php`'s shape (HTMX-aware responses via `_alert.html.twig`, non-HTMX redirects), plus one additional action, `saveTree`, for the tree editor's save. Constructor injects `MenuRepository`, `EntityManagerInterface`, `Twig`, `MenuLocationRegistry`, and `PageRepository`/`PostRepository`/`ProductionRepository` (to populate the "add item" panels — mirrors `ProductionController`'s multi-repo injection).
- **`src/Repositories/MenuRepository.php`** — extends `BaseRepository` (auto-discovered by `ServiceRegistrar::discoverRepositoryClasses()`, zero manual wiring needed). No separate `MenuItemRepository`: items are always managed as part of their owning menu's tree, mirroring how `Production`'s child entities (`Sponsorship`, `ProductionPerson`) have no repository of their own. Key method: `saveTree(Menu $menu, array $itemsPayload): void`.
- **`app/routes/admin/menus.php`** (new) — same `$app->group('/admin/menus', ...)` shape as `app/routes/admin/pages.php`, with routes for `index`/`create`/`store`/`edit`/`destroy` plus `POST /admin/menus/{id}/items` → `saveTree`. Registered in `www/index.php` via `require ROUTES_DIR . '/admin/menus.php';` added to the existing block of route requires (after `pages.php`).

**Tree save payload**: the whole tree is staged client-side (adding items from the left panel, dragging to reorder/nest) with zero server round-trips until one "Save Menu" click. On submit, JS serializes the live DOM into a flat JSON array (each row: `clientId`, `id` (null if new), `parentClientId` (references another row's `clientId`, enabling new parent+child to be created together), `position`, `label`, `linkType`, `targetId`/`customUrl`), written into a hidden form field — the same pattern `templates/admin/pages/edit.html.twig` already uses for serializing EditorJS content into a hidden field before HTMX submit.

**Reconciliation** (`MenuRepository::saveTree`): wrapped in a Doctrine transaction — clear all existing items on the menu (relying on `orphanRemoval: true` to issue deletes), then two passes over the payload: first create all `MenuItem` rows (without parent), then set `parent` on each using the `clientId` map. This delete-and-recreate approach is simpler and more robust than diffing; item IDs changing across saves is fine since nothing else references `menu_items.id`. The controller validates payload shape (non-empty `customUrl` for CUSTOM type, valid numeric `targetId` otherwise, and a cycle-guard on `parentClientId` chains) before calling `saveTree`.

## Admin UI

- **`templates/layouts/admin.html.twig`** — add `{ label: 'Menus', path: 'admin.menus', url: base_path ~ 'admin/menus' }` to the existing `nav_items` array (~line 64-77).
- **`templates/admin/menus/index.html.twig` / `_list.html.twig` / `_table.html.twig`** — same structural pattern as `templates/admin/pages/`, but no pagination (menu counts will be small — use `fetchAllOrderedByName()` directly). Columns: Name, Location (with an "(inactive location)" badge when applicable), Item count, Actions.
- **`templates/admin/menus/create.html.twig`** — Name + Location `<select>` (populated from the registry); on success redirects straight to `/admin/menus/edit/{id}` (an empty menu isn't useful on its own, matching WP's flow).
- **`templates/admin/menus/edit.html.twig`** (the novel piece) — two-column layout inside the existing admin card:
  - **Left panel**: Flowbite tabs (Flowbite JS/CSS already loaded globally in `admin.html.twig`, so `data-tabs-toggle` works with no extra wiring) for Pages / Posts / Productions / Custom Link, each entity tab a scrollable list with an "Add" button per row (`data-link-type`, `data-target-id`, `data-label` attributes read by client JS); Custom Link tab has URL + Label inputs.
  - **Right panel**: Name/Location fields (editable inline, saved together with the tree in the same `saveTree` POST) + the tree itself — a `<ul id="menu-item-tree">` server-rendered from current items, each `<li>` with a drag handle, resolved label, type badge, Remove button, and an always-present (even if empty) nested `<ul class="menu-item-children">` so SortableJS always has a valid nesting target. Single "Save Menu" button submits via HTMX.
  - **SortableJS** loaded via CDN only in this template's `{% block scripts %}` (not globally). Nested-sortable pattern: instantiate a `Sortable` instance on every `<ul class="menu-item-children">` (including the root list), all sharing one `group` name — dragging an item into a nested `<ul>` reparents/indents it; dragging it back out un-nests it. Depth is implicit from DOM position.

## Migration

New **`migrations/20260702_create_menus_tables.sql`** (following the existing dated-SQL-file convention seen in `migrations/20260327_create_pages_table.sql`): `menus` table (`id`, `name`, `location` with a `UNIQUE` index, timestamps) and `menu_items` table (`id`, `menu_id` FK `ON DELETE CASCADE`, `parent_id` self-referential FK `ON DELETE CASCADE`, `position`, `label_override`, `link_type`, `target_id` — intentionally **no** FK constraint since it's a soft reference across 3 possible tables, `custom_url`, timestamps). Same conventions as existing tables: `INT UNSIGNED AUTO_INCREMENT` PK, `InnoDB`, `utf8mb4`.

## DI Wiring (`src/DI/ServiceRegistrar.php`)

1. `MenuRepository` — free, auto-discovered.
2. `MenuLocationRegistry` — new `$container->set(...)` singleton factory in `registerSharedServices()`, alongside `HookManager::class`.
3. `MenuItemResolver` — new factory in `registerSharedServices()` injecting the three content repositories, registered before the `Twig::class` closure that consumes it.
4. `MenuExtension` — added via `$twig->addExtension(...)` inside the existing `Twig::class` factory closure, next to `EditorJsExtension`.
5. `MenuController` — new factory entry in `controllerFactories()`.
6. `src/Controllers/BaseController.php` — extend the `$repository`/`repository()` union type to include `MenuRepository` (mechanical, one line per signature, matching how every other repository was added).

## Verification

1. Add `register_menu_location('primary', 'Primary Navigation')` (and `'footer'`) to `www/themes/avlt/functions.php`; confirm `app/settings.php`'s active theme is set appropriately for testing.
2. Create a menu via `/admin/menus`, assign it to "Primary Navigation," confirm redirect to the edit/tree screen.
3. Add a Page, Post, Production, and Custom Link item; drag one item to nest inside another; reorder via drag; click Save Menu.
4. Reload the edit page (full GET) and confirm the nested structure, order, and parent/child relationships persisted exactly.
5. Add `{{ render_menu('primary') }}` to a theme template (e.g. the currently-empty `www/themes/avlt/templates/partials/nav.html.twig`), load the public site, confirm correct nested `<ul>/<li>` output with correct hrefs per item type and correct labels (override vs. resolved entity title).
6. Confirm `render_menu('footer')` with no assigned menu renders nothing and doesn't error.
7. Delete a Page that's linked as a menu item; confirm the public render silently skips that item, while the admin tree editor still shows it with an orphaned-content warning and a working Remove button.
8. Attempt to assign a second menu to an already-used location; confirm a clear validation error rather than silent reassignment.
9. Test 4+ levels of nesting to confirm the recursive rendering and save/reload round-trip handle depth correctly.
