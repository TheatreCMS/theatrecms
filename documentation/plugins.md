# Plugins

A plugin is a Composer package that extends TheatreCMS without editing core: it can add services,
routes, hooks, capabilities, admin sidebar items, Doctrine entities, Twig templates, database
migrations and console commands.

**Installed means active.** There is no activation toggle: if Composer installed the package, the
plugin runs. Remove the package to turn the plugin off.

## Making a plugin

A plugin package has type `theatrecms-plugin` and names its plugin class under
`extra.theatrecms.plugin`:

```json
{
    "name": "acme/box-office",
    "type": "theatrecms-plugin",
    "autoload": {
        "psr-4": { "Acme\\BoxOffice\\": "src/" }
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

Plugins are found through Composer's installed-package data (`ComposerPluginDiscovery`) and run in
package-name order.

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

## Installing plugins locally

Until a plugin is published, install it from a local checkout with a Composer path repository.
THE-102 adds a git-ignored `composer.local.json` for this, so core's committed `composer.json`
doesn't change.
