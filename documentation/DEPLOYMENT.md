# Deployment Guide

TheatreCMS is a custom PHP application (Slim 4 + Doctrine ORM 3 + [delight-im/auth](https://github.com/delight-im/PHP-Auth) + Twig) rather than a mainstream framework, so it doesn't come with all the batteries a Laravel/Symfony deploy would have out of the box: there's no migration runner. This guide walks through provisioning a new production instance end-to-end, including the manual steps that gap requires.

**THIS IS ALPHA SOFTWARE** — see the [project README](README.md). Review the hardening steps below carefully before exposing an instance publicly.

## Requirements

- **PHP 8.2+** with extensions: `ctype`, `json`, `openssl`, `pdo_mysql`, `mbstring`, `xml`, `curl`, `intl`, `zip`, `gd`, `opcache`
- **MariaDB 11.8** or **MySQL 8** (MariaDB 11.8 matches the project's `.ddev/config.yaml` dev environment)
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

## 5. Initialize or upgrade the schema

There is no migration-tracking table in this project. Keep an external record
of the last migration applied to each environment. Do not use the same schema
procedure for a fresh install and an upgrade.

### Fresh install

```bash
mysql -u theatrecms -p theatrecms_prod < vendor/delight-im/auth/Database/MySQL.sql
./doctrine orm:schema-tool:create
```

`./doctrine` is the Doctrine ORM console script at the repo root; it boots the
app container and reads `app/config.yaml` via `app/bootstrap.php`. The current
Doctrine metadata creates the complete content schema, including `media`,
`caption`, and `media_variants`. **Do not replay historical migrations after
`orm:schema-tool:create`**: many describe older versions of tables that Doctrine
has already created. The three September 2026 media transition migrations are
idempotent on a fresh schema, but are unnecessary for a fresh install.

### Upgrade an existing installation

Back up the database and `www/uploads`, then apply only migrations not already
recorded for that environment. For an installation that still has legacy
`featured_image_url` columns and no `images` table:

1. While the previous application release is still deployed, apply
   `20260903_create_images_table.sql` and
   `20260903_add_featured_image_id_to_content_tables.sql`.
2. Run that release's `./backfill-images` to register existing uploads and copy
   legacy URL references. It is safe to repeat before cutover.
3. Put the site in maintenance mode or otherwise stop uploads and content
   writes. Run the previous release's `./backfill-images` one final time.
4. Deploy the new code and run `composer install --no-dev --optimize-autoloader`.
   Keep the site stopped until the remaining database steps are complete.
5. Apply these transition migrations in order:

   ```bash
   mysql -u theatrecms -p theatrecms_prod < migrations/20260912_rename_images_table_to_media.sql
   mysql -u theatrecms -p theatrecms_prod < migrations/20260915_add_caption_to_media.sql
   mysql -u theatrecms -p theatrecms_prod < migrations/20260915_create_media_variants_table.sql
   ```

   The rename preserves rows and repoints existing featured-image foreign keys.
   All three files are idempotent for interrupted/repeated deployments.
6. Before generating variants, run the new release's `./backfill-images` once
   to register any source uploads that were not represented in the database.
7. Preview and then perform SEO filename renames:
   `./rename-media-filenames --dry-run`, followed by
   `./rename-media-filenames`. This command regenerates variants for renamed
   images, so it must run before the general regeneration pass.
8. Run `./regenerate-media-thumbnails` to fill every remaining registered image
   size. Re-run it with `--size=<name>` when a new size is registered.
9. Start the application and verify it. Once the deployment is healthy, apply
   `20260903_drop_featured_image_url_columns.sql` as a follow-up cleanup for
   installations that still have those legacy columns.

Installations that already completed part of this sequence should start at
their first unapplied step. The conditional transition migrations may safely
be re-run, but older migrations are not generally idempotent.

`./rename-media-filenames [--dry-run]` changes stored files from generated names
to slugs based on the original upload filename (for example,
`/uploads/3ddfb7a0765f10f8c7b6c495.jpg` to
`/uploads/pride-and-prejudice-poster.jpg`). Rows without a known original
filename are skipped. Old URLs stop resolving after a rename because this setup
has no redirect layer.

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
