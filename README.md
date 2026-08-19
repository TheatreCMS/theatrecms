# Theatre CMS

[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/TheatreCMS/theatrecms/badges/quality-score.png?b=main)](https://scrutinizer-ci.com/g/TheatreCMS/theatrecms/?branch=main)
[![Build Status](https://scrutinizer-ci.com/g/TheatreCMS/theatrecms/badges/build.png?b=main)](https://scrutinizer-ci.com/g/TheatreCMS/theatrecms/build-status/main)

Copyright (C) 2026  TheatreCMS Team

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

## Playwright end-to-end testing

- Make sure the PHP application is reachable via `http://127.0.0.1:8080` (for example: `php -S 127.0.0.1:8080 -t www` in the project root).
- Install the Playwright dependencies (`npm install`) and browsers (`npx playwright install chromium firefox`) before running the suite.
- Execute `npm run test:e2e` to run every spec in `tests/e2e` (which targets Chrome and Firefox via `playwright.config.ts`). To run a single browser or spec, use `npx playwright test --project=chrome tests/e2e/home.spec.ts` or `--project=firefox` as needed.

## Environment & database

- `app/config.yaml` is gitignored (it holds database credentials and site branding); copy `app/config.yaml.example` to `app/config.yaml` before first run and adjust as needed. See [`documentation/DEPLOYMENT.md`](documentation/DEPLOYMENT.md) for the full production setup.
- Doctrine’s default connection settings live in `app/settings.php` (`driver=pdo_mysql`, host `db`, port `3306`, database `db`, user `db`, password `db`, charset `utf8mb4`). Point these values at your local MySQL instance and ensure a matching schema/database exists before starting the app.
- For a quick local setup, launch MySQL with `docker run --name theatre-db -e MYSQL_ROOT_PASSWORD=root -e MYSQL_DATABASE=db -e MYSQL_USER=db -e MYSQL_PASSWORD=db -p 3306:3306 -d mysql:8 && docker exec theatre-db mysql -uroot -proot -e "CREATE DATABASE IF NOT EXISTS db;"`. Update `app/settings.php` if you change the host, credentials, or port.
- Keep the database service running whenever you bootstrap Doctrine-managed repositories so migrations and repository lookups can connect successfully.
- Once the schema is in place (see [`documentation/DEPLOYMENT.md`](documentation/DEPLOYMENT.md)), create the first admin user with `./create-admin` (flags or an interactive prompt for email/username/password; safe to re-run, it no-ops once an admin exists).

## Dependency injection wiring

- `app/bootstrap.php` now delegates registration to `TheatreCMS\DI\ServiceRegistrar`, which registers shared services, discovers repository classes from `src/Repositories`, and maps controllers to their required dependencies.
- `ServiceRegistrar` centralizes Twig/theme configuration and shared helpers so new controllers or repositories can simply be added under `src/Controllers` / `src/Repositories` without duplicating container wiring.

## VS Code workspace setup

- This repository includes workspace recommendations in `.vscode/extensions.json`.
- When VS Code prompts you with workspace recommendations, use `Install All` to install the suggested extension set for this project.
- If you miss the prompt, run `Extensions: Show Recommended Extensions` from the command palette.
- `unwantedRecommendations` only suppresses suggestion noise for this workspace; it does not uninstall any global extensions.

### Xdebug and DDEV alignment

- The `Listen for Xdebug` launch configuration in `.vscode/launch.json` expects Xdebug on port `9003` with `/var/www/html` mapped to `${workspaceFolder}`.
- The launch config runs pre/post debug tasks from `.vscode/tasks.json`:
    - `DDEV: Enable Xdebug`
    - `DDEV: Disable Xdebug`
- If debugging does not attach, verify DDEV is running and that Xdebug is enabled for the current debug session.
