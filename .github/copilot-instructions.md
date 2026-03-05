# Theatre CMS Copilot instructions

## Build, test, and lint commands

- `composer install` to install backend dependencies before running anything else.
- `npm install` to download Playwright and its TypeScript tooling for the e2e suite.
- Unit tests: `./vendor/bin/phpunit --configuration=phpunit.xml.dist tests/Unit`.  
  *Run a single test using the class/file path (e.g., `./vendor/bin/phpunit tests/Unit/UsersControllerTest.php`) or `--filter` with the test case name.*
- Static analysis: `composer stan` (runs `phpstan analyse -c phpstan.neon.dist --memory-limit=1G`). You can also run the same command directly with `./vendor/bin/phpstan`.
- Coding standard: `./vendor/bin/phpcs` (uses `phpcs.xml`, which enforces PSR-12 plus `Generic.Arrays.DisallowLongArraySyntax` and targets the `src` directory). Point it at a specific file when needed.
- Robo helpers (optional shortcuts): `./vendor/bin/robo tests` (phpunit via Robo) and `./vendor/bin/robo phpstan` (runs the same phpstan configuration).
- Playwright e2e: start a local server (e.g., `php -S 127.0.0.1:8080 -t www`) before running `npm run test:e2e`.  
  Use `npx playwright test --project=chrome tests/e2e/home.spec.ts` or `--project=firefox` to target a specific browser, or point at another spec file to run a single scenario.

## High-level architecture

- HTTP bootstrapping happens in `www/index.php`, which loads `app/bootstrap.php`, wires Slim to the DI container, and mounts the admin route files under `app/routes/admin`.
- `app/bootstrap.php` pulls settings from `app/settings.php`, configures Doctrine’s `EntityManager`, registers Twig/TwigMiddleware, Delight Auth, repositories, and controllers in the DI container, and shares them with the Slim application. Change or extend services here when adding new controllers, repositories, or middleware.
- Routing lives in `app/routes/admin/*.php`: each file defines a Slim route group for a domain (e.g., seasons, productions), pulls dependencies from the group container, and layers `RequireTwigMiddleware` plus `AuthMiddleware` for the admin UI. HTMX-compatible responses check the `HX-Request` header and return partial Twig templates (see `admin/productions` for pattern).
- Business logic is expressed through PSR-4 controllers, repositories, and Doctrine entities under `src/`. Repositories extend `BaseRepository`, share common CRUD helpers, and rely on Doctrine’s `EntityManager`. Controllers inherit from `BaseController` and use repositories, Twig, and Delight Auth to handle requests.
- Templates for the admin UI live under `templates/admin` (with shared layouts under `templates/layouts`). Twig rendering is central to both full pages and HTMX fragments.
- Domain models (seasons, productions, people, venues, works, sponsors, etc.) live in `src/Models` and are mapped to the database via Doctrine attributes.
- Tests live in `tests/Unit` (bootstrap via `tests/bootstrap.php`). They exercise repositories, controllers, and middleware; follow the same PSR-4 namespace (`Clubdeuce\TheatreCMS\Tests`). Use phpunit’s configuration file to carry the same bootstrap and suite setup locally as CI.
- Doctrine configurations are defined by `phpstan.neon.dist`, `phpunit.xml.dist`, and `phpcs.xml`, all in the repository root, making those files the authoritative source for analysis, linting, and testing settings.

## Key conventions

- Always register new repositories/controllers/middleware in `app/bootstrap.php` and expose them via the DI container so route files can fetch them.
- Route files read from `%APP%/routes/admin/…` and should follow the pattern of grouping `/admin/<resource>` endpoints, using `$group->getContainer()` to access Twig/repositories, and applying `RequireTwigMiddleware` plus `AuthMiddleware`. Prefer Twig templates for HTML responses, with HTMX partials returned when `HX-Request` is present.
- Repositories supply entity-specific creation helpers and rely on `BaseRepository` for shared fetch/query/delete/update logic. When adding new data operations, extend `BaseRepository` and keep exceptions explicit (e.g., `InvalidArgumentException` for missing IDs).
- Controllers subclass `BaseController`, expect their repository, and use Twig + Delight Auth to render pages or redirect. Use `$this->parseArgs()` from the base controller to normalize request payloads.
- Twig templates live in `templates/admin`, so keep routes in sync with existing Twig names and reuse common themes (e.g., `layouts/test.twig`).
- Doctrine uses the settings in `app/settings.php` (dev_mode toggles caching, `doctrine.connection` describes the DB). Keep `APP_ROOT` constants in sync with `www/index.php`/`bootstrap`.
- HTMX is supported for deletion/list updates: check the `HX-Request` header and render `_table.html.twig` partials instead of redirecting for AJAX flows.
- Playwright end-to-end tests live in `tests/e2e` with `playwright.config.ts` targeting Chrome (via the `chrome` channel) and Firefox; start the app at `http://127.0.0.1:8080` (e.g., `php -S 127.0.0.1:8080 -t www`) before running them and use `tests/e2e/home.spec.ts` as the reference pattern.
- There are no additional AI assistant configs (CLAUDE.md, AGENTS.md, etc.) in this repo, so this file is the source of truth for Copilot sessions.
