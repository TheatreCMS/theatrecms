# Deployment Guide

TheatreCMS is a custom PHP application (Slim 4 + Doctrine ORM 3 + [delight-im/auth](https://github.com/delight-im/PHP-Auth) + Twig) rather than a mainstream framework, so it doesn't come with all the batteries a Laravel/Symfony deploy would have out of the box: there's no migration runner. This guide walks through provisioning a new production instance end-to-end, including the manual steps that gap requires.

**THIS IS ALPHA SOFTWARE** — see the [project README](README.md). Review the hardening steps below carefully before exposing an instance publicly.

## Requirements

- **PHP 8.2+** with extensions: `ctype`, `json`, `openssl`, `pdo_mysql`, `mbstring`, `xml`, `curl`, `intl`, `zip`, `opcache`
- **MariaDB 10.11** or **MySQL 8** (MariaDB matches the project's `.ddev/config.yaml` dev environment)
- **nginx** + **PHP-FPM**
- **Composer 2**
- `certbot` (or equivalent) for TLS

## 1. Provision the server

Install the packages above. Create a dedicated OS user (or reuse `www-data`) that will own the app's writable directories and run PHP-FPM.

## 2. Get the code onto the server

```bash
git clone <repo-url> /var/www/theatrecms
cd /var/www/theatrecms
composer install --no-dev --optimize-autoloader
```

No JS build step is required — `www/assets` and `www/themes/default` are pre-built and tracked in git.

## 3. Create `app/config.yaml`

This file is gitignored and **not shipped with real values**, so it must be created on every new instance. It's read by `app/settings.php` (database connection, Doctrine, Twig, active theme) and `src/Settings/SiteSettings.php` (site branding, editable later from `/admin/settings`).

Copy the committed example and fill in real values:

```bash
cp app/config.yaml.example app/config.yaml
```

```yaml
site:
    organization_name: "Your Org"
    name: "Your Theatre"
    site_url: 'https://your-domain.example'
    logo_url: /assets/images/logo.svg
    contact_email: 'admin@your-domain.example'
    social: { facebook: '', twitter: '', instagram: '' }
database:
    driver: pdo_mysql
    host: 127.0.0.1
    port: 3306
    dbname: theatrecms_prod
    user: theatrecms
    password: '<strong random password>'
    charset: utf8mb4
```

Restrict its permissions since it holds database credentials:

```bash
chmod 640 app/config.yaml
```

## 4. Create the database

```sql
CREATE DATABASE theatrecms_prod CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'theatrecms'@'127.0.0.1' IDENTIFIED BY '<same password as config.yaml>';
GRANT ALL PRIVILEGES ON theatrecms_prod.* TO 'theatrecms'@'127.0.0.1';
FLUSH PRIVILEGES;
```

## 5. Initialize the schema

There is no migration-tracking table in this project. The schema comes from three sources and **must be applied in this order**:

1. `vendor/delight-im/auth/Database/MySQL.sql` — DDL for the `users` table and 7 `users_*` auth tables, owned by `delight-im/auth`. The application reads these records through DBAL, while Doctrine excludes the auth tables from schema management.
2. The Doctrine schema tool — creates all content-entity tables from `src/Models/*` (Season, Production, Page, Post, Person, Venue, Sponsor, Menu, etc.).
3. `migrations/*.sql` — hand-written patch files with no runner, applied in filename/date order.

```bash
mysql -u theatrecms -p theatrecms_prod < vendor/delight-im/auth/Database/MySQL.sql
./doctrine orm:schema-tool:create
mysql -u theatrecms -p theatrecms_prod < migrations/20260325_create_posts_table.sql
mysql -u theatrecms -p theatrecms_prod < migrations/20260327_create_pages_table.sql
mysql -u theatrecms -p theatrecms_prod < migrations/20260702_create_menus_tables.sql
mysql -u theatrecms -p theatrecms_prod < migrations/20260709_rename_seasons_hero_image_to_featured_image.sql
mysql -u theatrecms -p theatrecms_prod < migrations/20260805_make_production_venue_nullable.sql
mysql -u theatrecms -p theatrecms_prod < migrations/20260820_add_position_to_production_works.sql
mysql -u theatrecms -p theatrecms_prod < migrations/20260820_add_ends_at_to_events.sql
mysql -u theatrecms -p theatrecms_prod < migrations/20260903_create_images_table.sql
mysql -u theatrecms -p theatrecms_prod < migrations/20260903_add_featured_image_id_to_content_tables.sql
```

`./doctrine` is the Doctrine ORM console script at the repo root; it boots the app container and reads `app/config.yaml` via `app/bootstrap.php`. When new migration files are added to `migrations/` in future releases, apply any not yet run, in date order, before starting the upgraded app.

**Do not apply `migrations/20260903_drop_featured_image_url_columns.sql` on a fresh install** — `orm:schema-tool:create` already builds the `productions`/`posts`/`seasons` tables without a `featured_image_url` column, so there is nothing for it to drop. That migration (and `./backfill-images`, the root-level script that registers pre-existing `www/uploads/` files as `images` rows and repoints old `featured_image_url` values at them) only apply when **upgrading** an existing instance that has data in those legacy columns. For an upgrade: apply the two migrations above, run `./backfill-images` (safe to run more than once), deploy the new application code, run `./backfill-images` once more to catch anything uploaded during the deploy window, then — once the instance is confirmed healthy — apply `20260903_drop_featured_image_url_columns.sql` in a follow-up step.

## 6. Harden settings for production

`app/settings.php` currently hardcodes several dev-only values regardless of environment. Edit them directly on the deploy target:

- `'displayErrorDetails' => false`
- `'doctrine' => ['dev_mode' => false, ...]` — this activates Doctrine's metadata cache at `var/doctrine`
- `'view' => ['cache_enabled' => true, 'debug' => false, ...]`

## 7. Create writable runtime directories

```bash
mkdir -p var/twig var/doctrine
chown -R www-data:www-data var/ www/uploads
chmod -R 750 var/ www/uploads
```

- `var/twig` — Twig template cache
- `var/doctrine` — Doctrine metadata cache (used once `dev_mode` is `false`)
- `var/sessions` — PHP session storage
- `www/uploads/` — user-uploaded images (`src/Services/ImageUploadService.php`)

## 8. Configure nginx + PHP-FPM

- Point the vhost's docroot at `www/` (the front controller is `www/index.php`, analogous to Laravel's `public/`), with a standard rewrite-all-to-`index.php` rule.
- Run the PHP-FPM pool as the same user that owns `var/` and `www/uploads`.
- Once DNS points at the server, obtain a certificate: `certbot --nginx`.

## 9. Create the first administrator

Use the `./create-admin` script at the repo root — it boots the app container (reading `app/config.yaml` the same way `app/bootstrap.php` does) and creates a user with the admin role directly via `UserRepository`, so it works before any admin exists and doesn't depend on `/admin/register`. It's idempotent: if an admin already exists it does nothing unless you pass `--force`.

```bash
./create-admin --email=admin@your-domain.example --username=admin --password='<strong password>'
```

Omit any of `--email`, `--username`, `--password` to be prompted for it interactively (password entry is hidden), or supply them via `THEATRECMS_ADMIN_EMAIL` / `THEATRECMS_ADMIN_USERNAME` / `THEATRECMS_ADMIN_PASSWORD` environment variables instead of flags.

Log in at `https://your-domain.example/admin/login` and confirm you can reach `/admin/users` (gated by the `MANAGE_USERS` capability) to verify the role took effect. Create any further users from that in-app UI from here on.

## 10. Lock down `/admin/register`

Nothing in the app gates this endpoint after the first admin exists — it has no capability check and no "first user only" logic, so it stays open to the public. Block it at the web server once bootstrap is complete:

```nginx
location = /admin/register { return 404; }
```

## Verification checklist

- `curl -I https://your-domain.example/` returns `200` and the homepage renders — confirms nginx, PHP-FPM, the database connection, and Doctrine metadata are all wired correctly.
- Log in at `/admin/login` with the bootstrapped admin credentials.
- From the admin UI, confirm each content area loads without SQL errors: Seasons/Productions, Pages, Posts, Menus, People, Settings (`/admin/settings`) — this exercises both the Doctrine-managed tables and the hand-migrated `migrations/*.sql` tables.
- Upload an image (exercises `www/uploads` permissions) and confirm theme assets load (exercises `www/themes/default`).
- Confirm `/admin/register` now returns 404.
- Tail the PHP-FPM/nginx error logs while testing to confirm `displayErrorDetails: false` isn't masking a real misconfiguration.
