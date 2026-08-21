# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project overview

TheatreCMS ("avlt") is a PHP CMS for performing-arts organizations (theatres, dance troupes, opera companies, orchestras), built on Slim Framework, PHP-DI, and Doctrine ORM. It models domain content as seasons, productions, events, people, works, venues, and sponsors rather than generic CMS post types. It is alpha software.

It deliberately follows WordPress's core/plugin/theme architecture (a theme's `functions.php` registers hooks/menu locations, `HookManager` provides `add_filter`/`apply_filters`-style extensibility) but is a from-scratch implementation, not a WordPress fork.

## Commands

- `composer install` — install backend dependencies.
- `npm install` — install Playwright + TypeScript tooling for e2e tests and EditorJS frontend plugins.
- Unit tests: `./vendor/bin/phpunit --configuration=phpunit.xml.dist tests/Unit`
  - Single test file: `./vendor/bin/phpunit tests/Unit/UsersControllerTest.php`
  - Single test method: add `--filter <TestMethodName>`
- Static analysis: `composer stan` (or `./vendor/bin/phpstan analyse -c phpstan.neon.dist --memory-limit=1G`) — level 4, analyzes `src` only.
- Coding standard: `./vendor/bin/phpcs` — uses `phpcs.xml` (PSR-12 + `Generic.Arrays.DisallowLongArraySyntax`), targets `src`. Point at a specific file when needed.
- Robo shortcuts (optional): `./vendor/bin/robo tests`, `./vendor/bin/robo phpstan`.
- Playwright e2e: start a local server first (`php -S 127.0.0.1:8080 -t www`), then `npm run test:e2e`.
  - Single browser/spec: `npx playwright test --project=chrome tests/e2e/home.spec.ts` (or `--project=firefox`).
- `./doctrine` — Doctrine ORM console (schema tools etc.), wired to the app's `EntityManager` via `app/bootstrap.php`. Note: `migrations/` holds hand-written, timestamp-named raw `.sql` files, not Doctrine Migrations bundle output — apply them directly.
- `./create-admin` — creates the first admin user (flags or interactive prompt); safe to re-run, no-ops once an admin exists.

### Environment & database

- `app/config.yaml` is gitignored (DB credentials, site branding) — copy `app/config.yaml.example` to `app/config.yaml` before first run.
- Doctrine connection defaults live in `app/settings.php` (`pdo_mysql`, host `db`, port `3306`, db/user/pass `db`, `utf8mb4`).
- DDEV is configured (`.ddev/config.yaml`: PHP 8.4, nginx-fpm, docroot `www`, MariaDB 11.8, Node 24) as the primary local dev environment.
- The `users` / `users_*` tables are owned entirely by `delight-im/auth` and are explicitly excluded from Doctrine's schema-diffing (see the `setSchemaAssetsFilter` call in `app/bootstrap.php`) — never model them as Doctrine entities or include them in migrations generated from Doctrine.

## Architecture

### Request lifecycle

- `www/index.php` is the HTTP entry point: it loads `app/bootstrap.php` to get the DI container, wires it into Slim (`AppFactory`), requires the admin and frontend route files under `app/routes/admin/*.php` and `app/routes/frontend/*.php`, and defines a few inline routes (login/logout/register, `/admin`, `/`).
- `app/bootstrap.php` defines `APP_ROOT`, requires `app/hooks.php` and `app/menu-locations.php` (WP-style extensibility bootstraps), builds the DI `Container` from `app/settings.php`, registers the Doctrine `EntityManager` (with the `users*` table filter above), and delegates the rest of service registration to `TheatreCMS\DI\ServiceRegistrar::register()`. It also instantiates and installs the `HookManager`, `MenuLocationRegistry`, and `CapabilityRegistry` singletons (`setInstance`) and requires `app/capabilities.php`.
- `TheatreCMS\DI\ServiceRegistrar` (`src/DI/ServiceRegistrar.php`) is the single place that wires shared services, discovers/registers repositories, and maps controllers to their dependencies. Add new controllers/repositories/services here rather than inline in `bootstrap.php`.
- Routing lives in `app/routes/admin/*.php` and `app/routes/frontend/*.php`: each file defines a Slim route group for one domain (seasons, productions, people, works, posts, pages, menus, images, settings, etc.), pulls dependencies off the group container, and layers `RequireTwigMiddleware` + `AuthMiddleware` for admin routes.
- HTMX is a first-class citizen in the admin UI: handlers check the `HX-Request` header and return partial Twig templates (e.g. `_table.html.twig`, `admin/partials/_alert.html.twig`) instead of full pages/redirects for list updates and deletions.

### Controllers, repositories, models

- Controllers subclass `BaseController` (`src/Controllers/BaseController.php`), which provides generic CRUD actions (`store`, `create`, `get`, `getById`, `delete`), `parseArgs()` for normalizing request payloads, and shared helpers for the admin list views: `resolveListQuery()` (whitelists `q`/`sort`/`direction` query params), `buildPaginatedViewData()` / `buildPaginationData()` (pagination + search/sort state for Twig), `buildListRedirect()`, and `freshAlertResponse()` (renders an HTMX alert partial into the response body).
- Repositories extend `BaseRepository` (`src/Repositories/BaseRepository.php`), which implements `PaginatedRepositoryInterface` and provides `query()`, `fetchAll()`, `fetchPage()` (search/sort/paginate), `fetch()`, `fetchBySlug()`, `delete()`, `update()`, and slug generation (`generateUniqueSlug()`/`slugExists()`). Subclasses set `$entityClass` and override `applySearchFilter()` / `applyRequestedSort()` to opt individual entities into list-view search and column sorting (no-ops by default).
- Domain models live in `src/Models` and are mapped via Doctrine attributes (not XML/YAML config).
- Always register new repositories/controllers/middleware through `ServiceRegistrar` so route files can fetch them from the container.

### Theme / extensibility system (WordPress-inspired)

- `src/Theme/ThemeManager.php` loads an active theme's `functions.php` (analogous to WP theme functions), and `src/Theme/TemplateResolver.php` / `HookManager.php` (`add_filter`/`apply_filters`-style hooks exposed as globals via `app/hooks.php`) implement the extensibility layer. See `documentation/Theme/Architecture.md`, `hooks.md`, `ThemeManager.md`, `TemplateResolver.md` for the full design.
- `src/Theme/MenuLocationRegistry.php` and `src/Auth/CapabilityRegistry.php` follow the same singleton pattern (`setInstance`/`getInstance`, populated at bootstrap from `app/menu-locations.php` / `app/capabilities.php`) — this is the established pattern to follow for any new WP-style registry.
- Theme structure: `theme.json` (metadata), `functions.php` (hooks/menu-location registration, asset registration), `assets/{css,js}`, `templates/` (theme overrides layered on top of core Twig templates; `templates/layouts/admin.html.twig` always stays in core, never themed).
- Capabilities: authorization is capability-based (`CapabilityRegistry`, `AuthorizationService`), not a simple admin/non-admin flag — modeled on WordPress's `current_user_can()`, built on top of `delight-im/auth`'s role bitmask (`Role::*` constants), fail-closed for unregistered capabilities. See `documentation/capabilities-system-plan.md` for rationale and the full capability list.
- Menus: admin-manageable navigation menus (`Menu`/`MenuItem` Doctrine models, nested drag-and-drop tree, items link to Pages/Posts/Productions or custom URLs) with theme-registered locations (`register_menu_location()`) and a `render_menu()` Twig function, resolved via `src/Menus/MenuItemResolver.php`. See `documentation/menu-system-plan.md`.

### Templates

- Twig templates live under `templates/` (`templates/admin` for the admin UI, shared `templates/layouts`), rendered via `slim/twig-view`. Full pages and HTMX partials both go through Twig.
- `editorjs_to_html` is a custom Twig filter (`src/Text/EditorJsHtmlConverter.php`) that converts stored EditorJS block JSON (used for rich-text fields like production/page descriptions and person biographies) into sanitized HTML — pipe EditorJS-backed fields through it in templates rather than rendering raw JSON.

### Tests

- `tests/Unit` — PHPUnit tests for repositories, controllers, middleware, auth, and structured-data building; namespace `TheatreCMS\Tests` (per `composer.json` autoload-dev), bootstrapped via `tests/bootstrap.php`. `tests/Includes/TestCase.php` is the shared base test case; `tests/Unit/Test*.php` files (e.g. `TestPerson.php`, `TestVenue.php`) are Doctrine test fixtures/entities, not test cases themselves.
- `tests/Integration` — repository-level integration tests requiring a real database.
- `tests/e2e` — Playwright specs (`home.spec.ts` is the reference pattern), configured via `playwright.config.ts` to target Chrome and Firefox; require the app running at `http://127.0.0.1:8080`.
- `phpunit.xml.dist`, `phpstan.neon.dist`, and `phpcs.xml` in the repo root are the authoritative config for testing, static analysis, and linting respectively.

## Key conventions

- Route files follow the pattern: group `/admin/<resource>` endpoints, pull dependencies via `$group->getContainer()`, apply `RequireTwigMiddleware` + `AuthMiddleware`, prefer Twig HTML responses with HTMX partials returned when `HX-Request` is present.
- Repositories keep exceptions explicit (e.g. `InvalidArgumentException` for missing IDs) rather than silently failing.
- Keep `APP_ROOT` usage consistent between `www/index.php` and `app/bootstrap.php`.
