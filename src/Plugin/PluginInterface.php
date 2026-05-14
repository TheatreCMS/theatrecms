<?php

/**
 *
 * Copyright (C) 2026  TheatreCMS Team (https://theatrecms.dev)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */

declare(strict_types=1);

namespace TheatreCMS\Plugin;

use DI\Container;
use Slim\App;

interface PluginInterface
{
    /**
     * Returns the plugin slug — must match the plugin's directory name
     * and the slug field in plugin.json.
     */
    public function getSlug(): string;

    /**
     * Phase 1 — DI registration.
     *
     * Called after the core container is built, before the Slim App is created.
     * Register services, repositories, and controllers here.
     */
    public function register(Container $container): void;

    /**
     * Phase 2 — Application boot.
     *
     * Called after the Slim App is created and all core routes are loaded.
     * Register routes, middleware, and hook callbacks here.
     */
    public function boot(App $app): void;
}
