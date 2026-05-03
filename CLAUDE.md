# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

```bash
# Install dependencies
composer install
npm install

# Unit tests (PHP)
./vendor/bin/phpunit --configuration=phpunit.xml.dist tests/Unit
./vendor/bin/phpunit tests/Unit/UsersControllerTest.php        # single file
./vendor/bin/phpunit --filter testMethodName tests/Unit        # single test

# Static analysis & linting
composer stan                # phpstan at level 4 (or ./vendor/bin/phpstan)
./vendor/bin/phpcs           # PSR-12 + no long array syntax, targets src/

# Playwright e2e (start server first)
php -S 127.0.0.1:8080 -t www
npm run test:e2e
npx playwright test --project=chrome tests/e2e/home.spec.ts    # single spec/browser
```

## Architecture

HTTP enters at `www/index.php` → `app/bootstrap.php` (DI wiring, Doctrine, Twig, Auth) → Slim routes in `app/routes/`.

**Dependency injection**: `app/bootstrap.php` delegates to `TheatreCMS\DI\ServiceRegistrar`, which auto-discovers repository classes from `src/Repositories/` and manually maps controllers. Adding a new controller or repository only requires dropping the class in the right directory and wiring the controller in `ServiceRegistrar`. Do not duplicate container wiring in route files.

**Route files** (`app/routes/admin/*.php`): each file defines a Slim group for one resource (e.g., `/admin/seasons`), pulls services from `$group->getContainer()`, and applies `RequireTwigMiddleware` + `AuthMiddleware` to every route in the group. HTMX-aware endpoints check the `HX-Request` header and return a `_table.html.twig` partial instead of redirecting — see `app/routes/admin/productions.php` as the reference pattern.

**Controllers** subclass `BaseController`; use `$this->parseArgs()` to normalize request payloads. **Repositories** extend `BaseRepository` for shared `fetch()`/`query()`/`delete()`/`update()` helpers; add entity-specific creation logic in the subclass.

**Doctrine entities** in `src/Models/` are mapped via PHP 8 attributes. Delight Auth owns the `users` tables and is excluded from Doctrine schema management — do not run migrations against those tables.

**EditorJS** is the rich-content block editor for productions, posts, and pages. Content is stored as JSON and rendered as HTML via `EditorJsHtmlConverter` (called through the `editorjs_to_html` Twig filter). Add new block types in `src/Text/EditorJsHtmlConverter.php`.

**Theme system**: `ThemeManager` loads a theme from `/www/themes/<slug>/`, `TemplateResolver` maps template names to theme-specific overrides with fallback to `/templates/`, and `HookManager` provides hook registration/firing for extensibility. Active theme is set in `app/config.yaml`.

## Key conventions

- Register new services in `app/bootstrap.php` / `ServiceRegistrar` — route files must never instantiate services directly.
- Admin Twig templates live in `templates/admin/<resource>/`; keep template names in sync with route handlers. Shared layouts are in `templates/layouts/`.
- Doctrine connection settings live in `app/settings.php` (dev_mode toggles cache). Default DB: `pdo_mysql` at `db:3306`, database/user/password all `db`. DDEV sets this up automatically.
- PHPStan is configured at level 4 (`phpstan.neon.dist`) and ignores uninitialized Doctrine-managed `$id` properties — do not add suppressions for other patterns without discussion.
- Playwright specs target `http://127.0.0.1:8080`; use `tests/e2e/home.spec.ts` as the reference pattern for new specs.
- VS Code Xdebug: port `9003`, path map `/var/www/html` → `${workspaceFolder}`. Use the `DDEV: Enable/Disable Xdebug` tasks in `.vscode/tasks.json`.
