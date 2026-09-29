<?php

namespace TheatreCMS\Plugin;

use DI\Container;
use Slim\App;
use TheatreCMS\Admin\AdminMenuItem;

/**
 * A TheatreCMS plugin: a Composer package of type `theatrecms-plugin` whose `composer.json`
 * names this class under `extra.theatrecms.plugin`. Installed plugins are always active.
 *
 * Lifecycle (see PluginManager):
 *
 *  1. Discovery: the declarative methods below (entity paths, Twig paths, migration paths,
 *     commands, capabilities, admin menu items) are collected before any plugin code runs,
 *     so Doctrine and Twig are configured before they are first built.
 *  2. register(): called during `app/bootstrap.php`, after core services and registries exist
 *     and before the active theme's `functions.php` loads. Register DI services and hooks, and
 *     call core registration helpers such as `register_taxonomy()` here. Runs for web requests
 *     and console commands alike.
 *  3. boot(): called in `www/index.php` once the Slim App exists, after core routes and before
 *     the page catch-all. Register routes and middleware here. Not called for console commands.
 *
 * Extend AbstractPlugin rather than implementing this directly; it supplies empty defaults.
 */
interface PluginInterface
{
    public function register(Container $container): void;

    public function boot(App $app): void;

    /**
     * Directories of Doctrine attribute-mapped entities to add to the EntityManager.
     *
     * @return string[]
     */
    public function entityPaths(): array;

    /**
     * Twig template directories, keyed by namespace (e.g. `['dso' => __DIR__ . '/templates']`,
     * used as `@dso/blocks/accordion.html.twig`).
     *
     * @return array<string, string>
     */
    public function twigPaths(): array;

    /**
     * Directories of hand-written, timestamp-named `.sql` migrations, applied by the migration runner.
     *
     * @return string[]
     */
    public function migrationPaths(): array;

    /**
     * Symfony Console command class names to add to `bin/theatrecms`.
     *
     * @return class-string[]
     */
    public function commands(): array;

    /**
     * Capabilities to grant, keyed by `Delight\Auth\Role` constant. Granted on top of core's
     * role capabilities (see `app/capabilities.php`), never replacing them.
     *
     * @return array<int, string[]>
     */
    public function capabilities(): array;

    /**
     * Items to add to the admin sidebar.
     *
     * @return AdminMenuItem[]
     */
    public function adminMenuItems(): array;
}
