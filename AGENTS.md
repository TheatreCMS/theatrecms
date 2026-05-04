# AGENTS.md — TheatreCMS

## Commands

```bash
composer install && npm install

# Tests
./vendor/bin/phpunit                                    # all unit tests
./vendor/bin/phpunit tests/Unit/FooTest.php             # single file
./vendor/bin/phpunit --filter testMethodName            # single method

# Lint / static analysis
composer stan                                           # PHPStan level 4
./vendor/bin/phpcs                                      # PSR-12 + no long arrays

# E2E (start server first)
php -S 127.0.0.1:8080 -t www
npm run test:e2e

# BlockNote editor (after editing www/assets/js/blocknote-editor.js)
npm run build:blocknote
```

## Architecture

`www/index.php` → `app/bootstrap.php` → `ServiceRegistrar` (DI) → Slim routes in `app/routes/admin/` and `app/routes/frontend/`.

- **All DI wiring lives in `ServiceRegistrar`.** Drop new repositories in `src/Repositories/` (auto-discovered) and wire controllers manually in the registrar. Never instantiate services in route files.
- **Controllers** extend `BaseController`; call `$this->parseArgs()` for request payloads.
- **Repositories** extend `BaseRepository` (`fetch()`, `query()`, `delete()`, `update()`).
- **Admin routes** are Slim groups with `RequireTwigMiddleware` + `AuthMiddleware`. HTMX endpoints check `HX-Request` and return `_table.html.twig` partials — see `app/routes/admin/productions.php` as the pattern.

## Key constraints

- **Delight Auth** owns the `users` table — do NOT run Doctrine migrations against it.
- **EditorJS** content is stored as JSON. Rendered via `editorjs_to_html` Twig filter. Add block types in `src/Text/EditorJsHtmlConverter.php`.
- **Themes** load from `www/themes/<slug>/` with fallback chain: active theme → default → `templates/`. Active theme set in `app/config.yaml`.
- **Admin Twig templates** live in `templates/admin/<resource>/`.
- **BlockNote** is bundled via esbuild. Source: `www/assets/js/blocknote-editor.js` → output: `www/assets/js/blocknote-editor.bundle.js` (IIFE, exposes `BlockNoteEditor.mountBlockNoteEditor` globally). After editing the source, run `npm run build:blocknote`. Templates call `BlockNoteEditor.mountBlockNoteEditor()` — no import needed. CSS/fonts are local in `www/assets/css/`.

## Environment

- **DDEV** is the primary dev environment: PHP 8.4, MariaDB 11.8, nginx-fpm, docroot `www/`.
- **Default DB** (non-DDEV): host `db`, port `3306`, db/user/password all `db` — settings in `app/settings.php`.
- **Xdebug**: port `9003`, path map `/var/www/html` → `${workspaceFolder}`. Use VS Code tasks to toggle via DDEV.

## Testing notes

- PHPUnit config (`phpunit.xml.dist`) sources from `tests/bootstrap.php`, covers `src/`.
- Playwright targets `http://127.0.0.1:8080` — not the DDEV URL. Server must be running separately.
- Test namespaces: `TheatreCMS\Tests\` maps to `tests/`.
