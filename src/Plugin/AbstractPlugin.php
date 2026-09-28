<?php

namespace TheatreCMS\Plugin;

use DI\Container;
use Slim\App;

/**
 * Base class for plugins: every hook is optional, so a plugin only overrides what it uses.
 */
abstract class AbstractPlugin implements PluginInterface
{
    public function register(Container $container): void
    {
    }

    public function boot(App $app): void
    {
    }

    public function entityPaths(): array
    {
        return [];
    }

    public function twigPaths(): array
    {
        return [];
    }

    public function migrationPaths(): array
    {
        return [];
    }

    public function commands(): array
    {
        return [];
    }

    public function capabilities(): array
    {
        return [];
    }

    public function adminMenuItems(): array
    {
        return [];
    }
}
