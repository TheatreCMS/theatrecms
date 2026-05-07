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

namespace TheatreCMS\Plugin;

use DI\Container;
use Slim\App;

abstract class AbstractPlugin implements PluginInterface
{
    /**
     * Default no-op register. Override only if the plugin needs DI services.
     */
    public function register(Container $container): void
    {
    }

    /**
     * Default no-op boot. Override only if the plugin needs routes or middleware.
     */
    public function boot(App $app): void
    {
    }
}
