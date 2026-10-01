# Plugins

A plugin extends TheatreCMS without editing core: it can add services, routes, hooks, capabilities,
admin sidebar items, Doctrine entities, Twig templates, database migrations and console commands.

Like a theme, **a plugin is a folder**: `plugins/<name>/`, git-ignored in core. **Present means
active.** There is no activation toggle; remove the folder to turn the plugin off.

## Making a plugin

The folder has a `composer.json` of type `theatrecms-plugin` that names the plugin class under
`extra.theatrecms.plugin`. It declares its autoloading and any libraries it needs like any Composer
package:

```json
{
    "name": "acme/box-office",
    "type": "theatrecms-plugin",
    "autoload": {
        "psr-4": { "Acme\\BoxOffice\\": "src/" }
    },
    "require": {
        "some/library": "^2.0"
    },
    "extra": {
        "theatrecms": {
            "plugin": "Acme\\BoxOffice\\Plugin"
        }
    }
}
```

The class extends `TheatreCMS\Plugin\AbstractPlugin` and overrides only what it needs:

```php
namespace Acme\BoxOffice;

use DI\Container;
use Delight\Auth\Role;
use Slim\App;
use TheatreCMS\Admin\AdminMenuItem;
use TheatreCMS\Plugin\AbstractPlugin;

class Plugin extends AbstractPlugin
{
    public function register(Container $container): void
    {
        $container->set(TicketService::class, fn() => new TicketService());
        add_filter('theatrecms/the_content', [$this, 'appendTicketLink'], 20, 2);
    }

    public function boot(App $app): void
    {
        $app->get('/tickets/{id}', TicketController::class . ':show');
    }

    public function entityPaths(): array
    {
        return [__DIR__ . '/Models'];
    }

    public function twigPaths(): array
    {
        return ['box-office' => dirname(__DIR__) . '/templates']; // @box-office/...
    }

    public function migrationPaths(): array
    {
        return [dirname(__DIR__) . '/migrations'];
    }

    public function capabilities(): array
    {
        return [Role::ADMIN => ['manage_tickets']];
    }

    public function adminMenuItems(): array
    {
        return [new AdminMenuItem('Tickets', 'admin/tickets', 'manage_tickets', 'Box Office')];
    }
}
```

## Lifecycle

Plugins are found by scanning `plugins/*/composer.json` (`DirectoryPluginDiscovery`; the directory is
`settings['plugins']['dir']`) and run in package-name order. Folders without a valid manifest are
logged and skipped.

1. **Discovery** (`app/bootstrap.php`, right after core services are registered). Each plugin is
   instantiated and its declarations are collected: `entityPaths()`, `twigPaths()`,
   `migrationPaths()`, `commands()`, `capabilities()` and `adminMenuItems()`. This happens before
   anything builds the EntityManager or Twig, so plugin entities and templates are configured from
   the start. Keep constructors and these methods free of side effects.
2. **`register(Container)`** (`app/bootstrap.php`, after core registries exist and before the active
   theme's `functions.php`). Register DI services and hooks, and call core helpers such as
   `register_taxonomy()`. It runs for both web requests and console scripts. If it succeeds, the
   plugin's capabilities and admin menu items are applied. The `theatrecms/plugins_loaded` action
   fires once every plugin has registered; hook it when your plugin depends on another one.
3. **`boot(App)`** (`www/index.php`, web requests only). Register routes and middleware. It runs
   after core's routes and before the page catch-all, so plugin routes are never shadowed by pages.

## Extension points

| Method | Effect |
|---|---|
| `register()` | Anything that needs the container: services, hooks (`add_filter`/`add_action`), core registries |
| `boot()` | Routes and middleware on the Slim `App` |
| `entityPaths()` | Directories of Doctrine attribute-mapped entities, added to the EntityManager |
| `twigPaths()` | Template directories keyed by namespace, used as `@namespace/...`; avoid `core`, which is reserved |
| `migrationPaths()` | Directories of timestamp-named `.sql` migrations, applied by the migration runner (THE-103) |
| `commands()` | Console command classes for `bin/theatrecms` (THE-101) |
| `capabilities()` | Capabilities granted per `Delight\Auth\Role` constant, added on top of core's (see `documentation/capabilities-system-plan.md`) |
| `adminMenuItems()` | `AdminMenuItem`s for the admin sidebar. A new group label creates a new section between Appearance and Administration |

Themes and plugins can also add sidebar items from code with `register_admin_menu_item()`
(`app/admin-menu.php`). Core's own sidebar items live in `app/admin-menu-items.php`.

## Failures

A plugin that cannot be loaded, or that throws while declaring, registering or booting, is logged
through the PSR-3 logger (PHP's error log by default) and skipped. The site keeps running without
it. A plugin that fails in `register()` gets none of its capabilities or menu items, and is not booted.

## Installing plugins

Plugins can't bundle their own `vendor/`: two copies of a library core also uses (e.g. PHP-DI) would
load into one process and fail. Composer resolves every plugin's dependencies together with core's,
through a second, git-ignored Composer file so core's committed `composer.lock` never changes:

- `composer.local.json` (copied from `composer.local.json.example`) uses
  `wikimedia/composer-merge-plugin` to merge core's `composer.json` with every
  `plugins/*/composer.json`, including their autoloading.
- Its lock file is `composer.local.lock` (Composer names the lock after the JSON file), also
  git-ignored.
- It `replace`s `theatrecms/theatrecms`: in a local install core is the root project, so a plugin
  that requires `theatrecms/theatrecms` (to extend core classes, and so its own CI can install core)
  is satisfied without Composer installing a second copy of core into `vendor/`.
  `bin/composer-local` adds this to an existing `composer.local.json` that predates it.

To add, update or remove a plugin, change the `plugins/` folder, then run:

```sh
ddev exec bin/composer-local
```

`bin/composer-local` installs core from `composer.lock`, re-seeds `composer.local.lock` from it, and
runs `composer update --minimal-changes` against `composer.local.json`. Core's packages therefore
stay at exactly the versions in `composer.lock`, and only what the plugins need is added. Run it
again after pulling changes to `composer.lock`.

Plain `composer` commands still manage core's own dependencies and lock. They also drop plugin
dependencies from `vendor/`, so rerun `bin/composer-local` afterwards.

A plugin folder added without running `bin/composer-local` is found but can't be autoloaded; it is
logged ("run bin/composer-local after adding a plugin") and skipped.

Themes need none of this: a theme is a folder in `www/themes/` (see `documentation/Theme/ThemeManager.md`).
